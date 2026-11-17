<?php

namespace Tests\Feature;

use App\Events\NewArticleEvent;
use App\Models\Article;
use App\Models\User;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ArticleBroadcastTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_saved_article_dispatches_the_broadcast_event(): void
    {
        Event::fake([NewArticleEvent::class]);
        $this->actingAs(User::factory()->moderator()->create());

        $this->post('/articles', ['title' => 'Новая статья', 'description' => 'Текст'])
            ->assertRedirect('/articles');

        Event::assertDispatched(NewArticleEvent::class, function (NewArticleEvent $event): bool {
            return $event->article->exists
                && Article::whereKey($event->article->id)->exists()
                && $event->article->title === 'Новая статья';
        });
        Event::assertDispatchedTimes(NewArticleEvent::class, 1);
    }

    public function test_guests_readers_and_invalid_forms_do_not_dispatch_events(): void
    {
        Event::fake([NewArticleEvent::class]);
        $this->post('/articles', ['title' => 'New', 'description' => 'Text'])->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create());
        $this->post('/articles', ['title' => 'New', 'description' => 'Text'])->assertForbidden();
        $this->actingAs(User::factory()->moderator()->create());
        $this->post('/articles', ['title' => '', 'description' => 'Text'])->assertSessionHasErrors('title');

        Event::assertNotDispatched(NewArticleEvent::class);
        $this->assertDatabaseCount('articles', 0);
    }

    public function test_the_event_uses_a_public_channel_and_an_explicit_public_payload(): void
    {
        $article = Article::factory()->create();
        $event = new NewArticleEvent($article);

        $this->assertInstanceOf(ShouldBroadcast::class, $event);
        $this->assertInstanceOf(Channel::class, $event->broadcastOn());
        $this->assertNotInstanceOf(PrivateChannel::class, $event->broadcastOn());
        $this->assertSame('articles', $event->broadcastOn()->name);
        $this->assertSame([
            'article' => [
                'id' => $article->id,
                'title' => $article->title,
                'url' => route('articles.show', $article),
            ],
        ], $event->broadcastWith());
    }

    public function test_broadcasting_is_queued_when_enabled(): void
    {
        config(['broadcasting.default' => 'log']);
        Queue::fake();
        $article = Article::factory()->create();

        NewArticleEvent::dispatch($article);

        Queue::assertPushed(BroadcastEvent::class, fn (BroadcastEvent $job): bool => $job->event instanceof NewArticleEvent && $job->event->article->is($article)
        );
    }

    public function test_null_broadcaster_does_not_add_an_extra_queue_job(): void
    {
        config(['broadcasting.default' => 'null']);
        Queue::fake();

        NewArticleEvent::dispatch(Article::factory()->create());

        Queue::assertNotPushed(BroadcastEvent::class);

        config(['broadcasting.default' => null]);
        NewArticleEvent::dispatch(Article::factory()->create());
        Queue::assertNotPushed(BroadcastEvent::class);
    }

    public function test_the_worker_processes_a_broadcast_job_with_the_log_transport(): void
    {
        config(['broadcasting.default' => 'log', 'queue.default' => 'database']);

        NewArticleEvent::dispatch(Article::factory()->create());

        $this->assertDatabaseCount('jobs', 1);
        $this->artisan('queue:work', ['connection' => 'database', '--once' => true])->assertSuccessful();
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('failed_jobs', 0);
    }

    public function test_updates_and_deletions_do_not_announce_new_articles(): void
    {
        Event::fake([NewArticleEvent::class]);
        $article = Article::factory()->create();
        $this->actingAs(User::factory()->moderator()->create());

        $this->put(route('articles.update', $article), ['title' => 'Changed', 'description' => 'Text'])
            ->assertRedirect('/articles');
        $this->delete(route('articles.destroy', $article))->assertRedirect('/articles');

        Event::assertNotDispatched(NewArticleEvent::class);
    }

    public function test_the_layout_exposes_only_public_pusher_configuration(): void
    {
        config([
            'broadcasting.default' => 'pusher',
            'broadcasting.connections.pusher.key' => 'public-demo-key',
            'broadcasting.connections.pusher.secret' => 'private-secret-do-not-expose',
            'broadcasting.connections.pusher.app_id' => '123456',
            'broadcasting.connections.pusher.options.cluster' => 'eu',
        ]);

        $this->get('/')->assertOk()->assertSee('data-broadcast-enabled="true"', false)
            ->assertSee('data-pusher-key="public-demo-key"', false)
            ->assertSee('data-pusher-cluster="eu"', false)
            ->assertDontSee('private-secret-do-not-expose');
    }
}
