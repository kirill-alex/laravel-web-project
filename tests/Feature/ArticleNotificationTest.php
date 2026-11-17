<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use App\Notifications\NewArticleNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ArticleNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_articles_create_persistent_notifications_only_for_registered_readers(): void
    {
        $readers = User::factory()->count(2)->create();
        $moderator = User::factory()->moderator()->create();
        $otherModerator = User::factory()->moderator()->create();
        $this->actingAs($moderator);

        $this->post('/articles', ['title' => 'Новость для читателей', 'description' => 'Текст'])
            ->assertRedirect('/articles');

        $article = Article::where('title', 'Новость для читателей')->firstOrFail();
        $this->assertDatabaseCount('notifications', 2);
        foreach ($readers as $reader) {
            $notice = $reader->unreadNotifications()->firstOrFail();
            $this->assertSame(NewArticleNotification::class, $notice->type);
            $this->assertSame(['article_id' => $article->id, 'title' => $article->title], $notice->data);
            $this->assertNull($notice->read_at);
        }
        $this->assertSame(0, $moderator->notifications()->count());
        $this->assertSame(0, $otherModerator->notifications()->count());

        $this->actingAs($readers->first())->get('/')->assertOk()
            ->assertSee('Уведомления (1)')->assertSee($article->title);
    }

    public function test_the_notification_uses_only_the_database_channel(): void
    {
        $reader = User::factory()->create();
        $article = Article::factory()->create();
        $notice = new NewArticleNotification($article);

        $this->assertSame(['database'], $notice->via($reader));
        $this->assertSame(['article_id' => $article->id, 'title' => $article->title], $notice->toDatabase($reader));
    }

    public function test_the_menu_contains_only_the_current_users_unread_notifications(): void
    {
        $reader = User::factory()->create();
        $otherReader = User::factory()->create();
        $unread = Article::factory()->create(['title' => '<script>Unread title</script>']);
        $read = Article::factory()->create(['title' => 'Already read title']);
        $foreign = Article::factory()->create(['title' => 'Other user title']);
        $reader->notify(new NewArticleNotification($read));
        $reader->unreadNotifications()->firstOrFail()->markAsRead();
        $reader->notify(new NewArticleNotification($unread));
        $otherReader->notify(new NewArticleNotification($foreign));
        $this->actingAs($reader);

        $this->get('/')->assertOk()->assertSee('Уведомления (1)')
            ->assertSee($unread->title)->assertDontSee($unread->title, false)
            ->assertDontSee($read->title)->assertDontSee($foreign->title);
    }

    public function test_opening_a_notification_marks_only_it_as_read_and_opens_its_article(): void
    {
        $reader = User::factory()->create();
        $firstArticle = Article::factory()->create();
        $secondArticle = Article::factory()->create();
        $reader->notify(new NewArticleNotification($firstArticle));
        $firstNotice = $reader->notifications()->firstOrFail();
        $reader->notify(new NewArticleNotification($secondArticle));
        $this->actingAs($reader);

        $this->post(route('notifications.read', $firstNotice->id))
            ->assertRedirect(route('articles.show', $firstArticle));

        $this->assertNotNull($firstNotice->fresh()->read_at);
        $this->assertSame(1, $reader->unreadNotifications()->count());
        $this->get('/')->assertOk()->assertSee('Уведомления (1)')
            ->assertSee($secondArticle->title)->assertDontSee($firstArticle->title);
    }

    public function test_other_users_cannot_read_someone_elses_notification(): void
    {
        $owner = User::factory()->create();
        $owner->notify(new NewArticleNotification(Article::factory()->create()));
        $notice = $owner->notifications()->firstOrFail();
        $this->actingAs(User::factory()->create());

        $this->post(route('notifications.read', $notice->id))->assertNotFound();
        $this->assertNull($notice->fresh()->read_at);
    }

    public function test_guests_cannot_read_notifications_and_have_no_notification_menu(): void
    {
        $reader = User::factory()->create();
        $reader->notify(new NewArticleNotification(Article::factory()->create()));
        $notice = $reader->notifications()->firstOrFail();

        $this->get('/')->assertOk()->assertDontSee('notification-menu', false);
        $this->post(route('notifications.read', $notice->id))->assertRedirect(route('login'));
        $this->assertNull($notice->fresh()->read_at);
    }

    public function test_reopening_an_already_read_notification_preserves_the_original_read_time(): void
    {
        $reader = User::factory()->create();
        $article = Article::factory()->create();
        $reader->notify(new NewArticleNotification($article));
        $notice = $reader->notifications()->firstOrFail();
        $this->actingAs($reader);

        $this->post(route('notifications.read', $notice->id))->assertRedirect(route('articles.show', $article));
        $originalReadAt = $notice->fresh()->read_at->toISOString();
        $this->travel(1)->minutes();
        $this->post(route('notifications.read', $notice->id))->assertRedirect(route('articles.show', $article));
        $this->assertSame($originalReadAt, $notice->fresh()->read_at->toISOString());
    }

    public function test_notifications_about_deleted_articles_are_cleared_with_an_explanation(): void
    {
        $reader = User::factory()->create();
        $article = Article::factory()->create();
        $reader->notify(new NewArticleNotification($article));
        $notice = $reader->notifications()->firstOrFail();
        $article->delete();
        $this->actingAs($reader);

        $this->post(route('notifications.read', $notice->id))
            ->assertRedirect(route('articles.index'))
            ->assertSessionHas('status', 'Статья из уведомления уже удалена.');
        $this->assertNotNull($notice->fresh()->read_at);
    }

    public function test_get_requests_cannot_change_the_read_state(): void
    {
        $reader = User::factory()->create();
        $reader->notify(new NewArticleNotification(Article::factory()->create()));
        $notice = $reader->notifications()->firstOrFail();
        $this->actingAs($reader);

        $this->get(route('notifications.read', $notice->id))->assertStatus(405);
        $this->assertNull($notice->fresh()->read_at);
    }

    public function test_failed_creation_updates_and_deletions_do_not_notify_readers(): void
    {
        Notification::fake();
        $reader = User::factory()->create();
        $article = Article::factory()->create();
        $this->actingAs($reader);
        $this->post('/articles', ['title' => 'New', 'description' => 'Text'])->assertForbidden();
        $this->actingAs(User::factory()->moderator()->create());
        $this->post('/articles', ['title' => '', 'description' => 'Text'])->assertSessionHasErrors('title');
        $this->put(route('articles.update', $article), ['title' => 'Changed', 'description' => 'Text'])
            ->assertRedirect('/articles');
        $this->delete(route('articles.destroy', $article))->assertRedirect('/articles');

        Notification::assertNothingSent();
    }
}
