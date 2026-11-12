<?php

namespace Tests\Feature;

use App\Jobs\VeryLongJob;
use App\Mail\ArticleCreatedMail;
use App\Models\Article;
use App\Models\MailMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\PendingMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class ArticleQueueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'queue.default' => 'database',
            'mail.default' => 'preview',
            'mail.moderator_address' => 'moderator@example.test',
        ]);
    }

    public function test_mail_is_saved_only_after_the_database_queue_worker_runs(): void
    {
        $this->actingAs(User::factory()->moderator()->create());

        $this->post('/articles', ['title' => 'Queued article', 'description' => 'Queue test'])
            ->assertRedirect('/articles')->assertSessionHas('status');

        $this->assertDatabaseCount('articles', 1);
        $this->assertDatabaseCount('jobs', 1);
        $this->assertDatabaseCount('mail_messages', 0);
        $this->artisan('queue:work', ['connection' => 'database', '--once' => true])->assertSuccessful();

        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('failed_jobs', 0);
        $this->assertDatabaseCount('mail_messages', 1);
        $message = MailMessage::firstOrFail();
        $this->assertSame('Новая статья: Queued article', $message->subject);
        $this->assertSame(['moderator@example.test'], $message->recipients);
        $this->get(route('mailbox.index'))->assertOk()->assertSee($message->subject);
    }

    public function test_each_moderator_gets_a_separate_job(): void
    {
        Queue::fake();
        Mail::fake();
        config(['mail.moderator_address' => null]);
        $moderators = User::factory()->moderator()->count(2)->create();
        $this->actingAs($moderators->first());

        $this->post('/articles', ['title' => 'New', 'description' => 'Text'])->assertRedirect('/articles');

        Queue::assertPushed(VeryLongJob::class, 2);
        foreach ($moderators as $moderator) {
            Queue::assertPushed(VeryLongJob::class, fn (VeryLongJob $job): bool => $job->recipient === $moderator->email && $job->article->title === 'New'
            );
        }
        Mail::assertNothingSent();
    }

    public function test_failed_delivery_is_retried_without_deleting_the_article(): void
    {
        $this->actingAs(User::factory()->moderator()->create());
        $this->post('/articles', ['title' => 'Saved article', 'description' => 'Text'])->assertRedirect('/articles');
        $pendingMail = Mockery::mock(PendingMail::class);
        Mail::shouldReceive('to')->twice()->with('moderator@example.test')->andReturn($pendingMail);
        $pendingMail->shouldReceive('send')->twice()->with(Mockery::type(ArticleCreatedMail::class))
            ->andThrow(new TransportException('Temporary delivery failure'));

        $this->artisan('queue:work', ['connection' => 'database', '--once' => true])->assertSuccessful();

        $this->assertDatabaseCount('jobs', 1);
        $this->assertDatabaseCount('failed_jobs', 0);
        $job = DB::table('jobs')->first();
        $this->assertSame(1, $job->attempts);
        $this->assertGreaterThan(time(), $job->available_at);
        $this->assertDatabaseHas('articles', ['title' => 'Saved article']);

        DB::table('jobs')->where('id', $job->id)->update(['attempts' => 2, 'available_at' => time()]);
        $this->artisan('queue:work', ['connection' => 'database', '--once' => true])->assertSuccessful();

        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('failed_jobs', 1);
        $this->assertDatabaseHas('articles', ['title' => 'Saved article']);
    }

    public function test_a_job_for_a_deleted_article_is_discarded_without_sending_mail(): void
    {
        Mail::fake();
        $article = Article::factory()->create();
        VeryLongJob::dispatch($article, 'moderator@example.test');
        $article->delete();

        $this->artisan('queue:work', ['connection' => 'database', '--once' => true])->assertSuccessful();

        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('failed_jobs', 0);
        Mail::assertNothingSent();
    }

    public function test_unauthorized_and_invalid_requests_do_not_create_jobs(): void
    {
        $this->post('/articles', ['title' => 'New', 'description' => 'Text'])->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create());
        $this->post('/articles', ['title' => 'New', 'description' => 'Text'])->assertForbidden();
        $this->actingAs(User::factory()->moderator()->create());
        $this->post('/articles', ['title' => '', 'description' => 'Text'])->assertSessionHasErrors('title');

        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('articles', 0);
    }
}
