<?php

namespace Tests\Feature;

use App\Mail\DailyStatisticsMail;
use App\Models\Article;
use App\Models\ArticleView;
use App\Models\Comment;
use App\Models\MailMessage;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\PendingMail;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class DailyStatisticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_counts_the_moscow_day_and_sends_only_to_moderators(): void
    {
        Mail::fake();
        config(['statistics.timezone' => 'Europe/Moscow']);
        $moderators = User::factory()->moderator()->count(2)->create();
        $reader = User::factory()->create();
        $article = Article::factory()->create();

        foreach (['2026-11-16 20:59:59', '2026-11-16 21:00:00', '2026-11-17 09:00:00', '2026-11-17 20:59:59', '2026-11-17 21:00:00'] as $timestamp) {
            ArticleView::forceCreate([
                'article_id' => $article->id,
                'url' => route('articles.show', $article),
                'created_at' => $timestamp,
            ]);
        }
        Comment::factory()->for($article)->create(['created_at' => '2026-11-16 21:00:00']);
        Comment::factory()->approved()->for($article)->create(['created_at' => '2026-11-17 15:00:00']);
        Comment::factory()->rejected()->for($article)->create(['created_at' => '2026-11-17 20:59:59']);
        Comment::factory()->for($article)->create(['created_at' => '2026-11-16 20:59:59']);
        Comment::factory()->for($article)->create(['created_at' => '2026-11-17 21:00:00']);

        $this->artisan('statistics:send-daily', ['--date' => '2026-11-17'])->assertSuccessful();

        Mail::assertSent(DailyStatisticsMail::class, 2);
        foreach ($moderators as $moderator) {
            Mail::assertSent(DailyStatisticsMail::class, fn (DailyStatisticsMail $mail): bool => $mail->hasTo($moderator->email) && $mail->viewsCount === 3 && $mail->commentsCount === 3
                && $mail->date === '2026-11-17' && $mail->timezone === 'Europe/Moscow'
            );
        }
        Mail::assertNotSent(DailyStatisticsMail::class, fn (DailyStatisticsMail $mail): bool => $mail->hasTo($reader->email));
    }

    public function test_default_report_date_uses_moscow_even_before_utc_midnight(): void
    {
        Mail::fake();
        config(['statistics.timezone' => 'Europe/Moscow']);
        $this->travelTo(CarbonImmutable::parse('2026-11-16 22:30:00', 'UTC'));
        User::factory()->moderator()->create();

        $this->artisan('statistics:send-daily')->assertSuccessful();

        Mail::assertSent(DailyStatisticsMail::class, fn (DailyStatisticsMail $mail): bool => $mail->date === '2026-11-17' && $mail->viewsCount === 0 && $mail->commentsCount === 0
        );
    }

    public function test_report_is_delivered_to_the_local_mailbox_without_smtp_or_a_queue_worker(): void
    {
        config(['mail.default' => 'preview']);
        $moderator = User::factory()->moderator()->create();

        $this->artisan('statistics:send-daily', ['--date' => '2026-11-17'])->assertSuccessful();

        $this->assertDatabaseCount('mail_messages', 1);
        $this->assertDatabaseCount('jobs', 0);
        $message = MailMessage::firstOrFail();
        $this->assertSame([$moderator->email], $message->recipients);
        $this->assertSame('Статистика сайта за 2026-11-17', $message->subject);
        $this->assertStringContainsString('Просмотры статей', $message->html_body);
        $this->assertStringContainsString('Новые комментарии', $message->html_body);
        $this->actingAs($moderator)->get(route('mailbox.show', $message))->assertOk()
            ->assertSee('Статистика сайта за 2026-11-17');
    }

    public function test_invalid_dates_do_not_send_reports(): void
    {
        Mail::fake();
        User::factory()->moderator()->create();

        foreach (['not-a-date', '2026-02-30'] as $date) {
            $this->artisan('statistics:send-daily', ['--date' => $date])->assertFailed();
        }
        Mail::assertNothingSent();
    }

    public function test_missing_moderators_are_reported_without_sending_mail(): void
    {
        Mail::fake();
        User::factory()->create();

        $this->artisan('statistics:send-daily')->assertFailed();

        Mail::assertNothingSent();
    }

    public function test_failure_for_one_moderator_does_not_skip_another(): void
    {
        $moderators = User::factory()->moderator()->count(2)->create();
        $failedMail = Mockery::mock(PendingMail::class);
        $successfulMail = Mockery::mock(PendingMail::class);
        Mail::shouldReceive('to')->once()->with($moderators[0]->email)->andReturn($failedMail);
        Mail::shouldReceive('to')->once()->with($moderators[1]->email)->andReturn($successfulMail);
        $failedMail->shouldReceive('send')->once()->andThrow(new TransportException('Delivery failed'));
        $successfulMail->shouldReceive('send')->once();

        $this->artisan('statistics:send-daily')->assertFailed();
    }

    public function test_the_report_command_is_scheduled_every_minute(): void
    {
        $event = collect(app(Schedule::class)->events())->first(fn ($event): bool => str_contains($event->command ?? '', 'statistics:send-daily')
        );

        $this->assertNotNull($event);
        $this->assertSame('* * * * *', $event->expression);
    }
}
