<?php

namespace Tests\Feature;

use App\Mail\ArticleCreatedMail;
use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\PendingMail;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class ArticleMailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mail.moderator_address' => null]);
    }

    public function test_creating_an_article_notifies_each_moderator_but_not_readers(): void
    {
        Mail::fake();
        $moderators = User::factory()->moderator()->count(2)->create();
        $reader = User::factory()->create();
        $this->actingAs($moderators->first());

        $this->post('/articles', ['title' => 'Mail test', 'description' => 'Article text'])
            ->assertRedirect('/articles')->assertSessionHas('status');

        $article = Article::where('title', 'Mail test')->firstOrFail();
        Mail::assertSent(ArticleCreatedMail::class, 2);
        foreach ($moderators as $moderator) {
            Mail::assertSent(ArticleCreatedMail::class, fn (ArticleCreatedMail $mail): bool => $mail->hasTo($moderator->email) && $mail->article->is($article)
            );
        }
        Mail::assertNotSent(ArticleCreatedMail::class, fn (ArticleCreatedMail $mail): bool => $mail->hasTo($reader->email)
        );
    }

    public function test_a_configured_moderator_address_overrides_database_recipients(): void
    {
        Mail::fake();
        config(['mail.moderator_address' => 'notifications@example.com']);
        $this->actingAs(User::factory()->moderator()->create());

        $this->post('/articles', ['title' => 'Mail test', 'description' => 'Text'])->assertRedirect('/articles');

        Mail::assertSent(ArticleCreatedMail::class, 1);
        Mail::assertSent(ArticleCreatedMail::class, fn (ArticleCreatedMail $mail): bool => $mail->hasTo('notifications@example.com')
        );
    }

    public function test_unauthorized_and_invalid_requests_do_not_send_email(): void
    {
        Mail::fake();
        $this->post('/articles', ['title' => 'New', 'description' => 'Text'])->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create());
        $this->post('/articles', ['title' => 'New', 'description' => 'Text'])->assertForbidden();
        $this->actingAs(User::factory()->moderator()->create());
        $this->post('/articles', ['title' => '', 'description' => 'Text'])->assertSessionHasErrors('title');

        Mail::assertNothingSent();
        $this->assertDatabaseCount('articles', 0);
    }

    public function test_updating_and_deleting_articles_do_not_send_creation_notifications(): void
    {
        Mail::fake();
        $article = Article::factory()->create();
        $this->actingAs(User::factory()->moderator()->create());

        $this->put(route('articles.update', $article), ['title' => 'Changed', 'description' => 'Text'])
            ->assertRedirect('/articles');
        $this->delete(route('articles.destroy', $article))->assertRedirect('/articles');

        Mail::assertNothingSent();
    }

    public function test_the_message_has_the_article_details_and_link_and_escapes_html(): void
    {
        $article = Article::factory()->create([
            'title' => 'New article',
            'description' => '<script>alert(1)</script>',
        ]);
        $mail = new ArticleCreatedMail($article);
        $mail->build();

        $this->assertSame('Новая статья: New article', $mail->subject);
        $html = $mail->render();
        $this->assertStringContainsString($article->title, $html);
        $this->assertStringContainsString(e($article->description), $html);
        $this->assertStringNotContainsString($article->description, $html);
        $this->assertStringContainsString(route('articles.show', $article), $html);
    }

    public function test_smtp_failure_keeps_the_article_and_shows_a_warning(): void
    {
        $moderator = User::factory()->moderator()->create();
        $this->actingAs($moderator);
        $pendingMail = Mockery::mock(PendingMail::class);
        Mail::shouldReceive('to')->once()->with($moderator->email)->andReturn($pendingMail);
        $pendingMail->shouldReceive('send')->once()->with(Mockery::type(ArticleCreatedMail::class))
            ->andThrow(new TransportException('SMTP connection failed'));

        $this->post('/articles', ['title' => 'Saved despite SMTP error', 'description' => 'Text'])
            ->assertRedirect('/articles')->assertSessionHas('warning');

        $this->assertDatabaseHas('articles', ['title' => 'Saved despite SMTP error']);
        $this->get('/articles')->assertOk()->assertSee('Статья сохранена, но не все уведомления отправлены.');
    }

    public function test_failure_for_one_recipient_does_not_skip_the_other_moderators(): void
    {
        $moderators = User::factory()->moderator()->count(2)->create();
        $this->actingAs($moderators->first());
        $failedMail = Mockery::mock(PendingMail::class);
        $successfulMail = Mockery::mock(PendingMail::class);
        Mail::shouldReceive('to')->once()->with($moderators[0]->email)->andReturn($failedMail);
        Mail::shouldReceive('to')->once()->with($moderators[1]->email)->andReturn($successfulMail);
        $failedMail->shouldReceive('send')->once()->andThrow(new TransportException('SMTP rejected recipient'));
        $successfulMail->shouldReceive('send')->once();

        $this->post('/articles', ['title' => 'New', 'description' => 'Text'])
            ->assertRedirect('/articles')->assertSessionHas('warning');
        $this->assertDatabaseCount('articles', 1);
    }
}
