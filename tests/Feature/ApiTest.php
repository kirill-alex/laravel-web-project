<?php

namespace Tests\Feature;

use App\Events\NewArticleEvent;
use App\Jobs\VeryLongJob;
use App\Models\Article;
use App\Models\Comment;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_returns_reader_and_working_bearer_token_without_private_fields(): void
    {
        $moderator = Role::firstOrCreate(['name' => Role::MODERATOR]);
        $response = $this->postJson('/api/register', [
            'name' => 'API Reader', 'email' => 'reader@example.test',
            'password' => 'secret123', 'role_id' => $moderator->id,
        ])->assertCreated()->assertJsonPath('user.role.name', Role::READER)
            ->assertJsonPath('token_type', 'Bearer')->assertJsonMissingPath('user.password')
            ->assertJsonMissingPath('user.remember_token');
        $user = User::where('email', 'reader@example.test')->firstOrFail();
        $this->assertTrue(Hash::check('secret123', $user->password));
        $this->withToken($response->json('token'))->getJson('/api/user')
            ->assertOk()->assertJsonPath('user.id', $user->id);
    }

    public function test_registration_validation_is_json_even_without_accept_header(): void
    {
        $this->post('/api/register', [])->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password']);
        $user = User::factory()->create();
        $this->postJson('/api/register', [
            'name' => 'Duplicate', 'email' => $user->email, 'password' => '123456',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_login_checks_hash_and_issues_token_without_a_session(): void
    {
        $user = User::factory()->moderator()->create();
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertUnauthorized()->assertJsonMissingPath('token');
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $response = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk()->assertJsonPath('user.role.name', Role::MODERATOR);
        $this->withToken($response->json('token'))->getJson('/api/user')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_logout_revokes_only_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('current');
        $other = $user->createToken('other');
        $this->withToken($token->plainTextToken)->postJson('/api/logout')->assertOk();
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $other->accessToken->id]);
        $this->app['auth']->forgetGuards();
        $this->withToken($token->plainTextToken)->getJson('/api/user')->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $this->withToken($other->plainTextToken)->getJson('/api/user')->assertOk();
    }

    public function test_guest_writes_and_invalid_tokens_return_401_without_redirects(): void
    {
        $article = Article::factory()->create();
        $this->post('/api/articles', [])->assertUnauthorized()->assertHeader('Content-Type', 'application/json');
        $this->put('/api/articles/'.$article->id, [])->assertUnauthorized();
        $this->delete('/api/articles/'.$article->id)->assertUnauthorized();
        $this->withToken('invalid')->get('/api/user')->assertUnauthorized();
    }

    public function test_reader_cannot_change_articles_even_without_accept_header(): void
    {
        $article = Article::factory()->create();
        $this->withToken(User::factory()->create()->createToken('reader')->plainTextToken);
        $this->post('/api/articles', ['title' => 'Forbidden', 'description' => 'Text'])
            ->assertForbidden()->assertHeader('Content-Type', 'application/json');
        $this->patch('/api/articles/'.$article->id, ['title' => 'Forbidden'])->assertForbidden();
        $this->delete('/api/articles/'.$article->id)->assertForbidden();
        $this->assertDatabaseHas('articles', ['id' => $article->id, 'title' => $article->title]);
    }

    public function test_list_pagination_has_api_links_even_when_cache_was_filled_by_web(): void
    {
        config(['cache.default' => 'database']);
        Article::factory(6)->create();
        $this->get('/articles?page=2')->assertOk();
        $this->get('/api/articles?page=2')->assertOk()
            ->assertJsonPath('articles.total', 6)->assertJsonCount(1, 'articles.data')
            ->assertJsonPath('articles.current_page', 2)
            ->assertJsonPath('articles.path', url('/api/articles'));
        $this->get('/api/articles')->assertJsonCount(5, 'articles.data');
    }

    public function test_show_only_returns_approved_comments_and_records_views_on_cache_hits(): void
    {
        config(['cache.default' => 'database']);
        $article = Article::factory()->create();
        $approved = Comment::factory()->approved()->for($article)->create();
        Comment::factory()->for($article)->create();
        Comment::factory()->rejected()->for($article)->create();
        $this->get('/articles/'.$article->id)->assertOk();
        $this->get('/api/articles/'.$article->id)->assertOk()
            ->assertJsonPath('article.id', $article->id)->assertJsonCount(1, 'comments.data')
            ->assertJsonPath('comments.data.0.id', $approved->id)
            ->assertJsonPath('comments.data.0.user.name', $approved->user->name)
            ->assertJsonMissingPath('comments.data.0.user.email')
            ->assertJsonMissingPath('comments.data.0.user.password');
        $this->get('/api/articles/'.$article->id)->assertOk();
        $this->assertDatabaseCount('article_views', 3);
        $this->get('/api/articles/999999')->assertNotFound()->assertHeader('Content-Type', 'application/json');
    }

    public function test_api_creation_keeps_queue_broadcast_notifications_and_cache_invalidation(): void
    {
        Queue::fake();
        Event::fake([NewArticleEvent::class]);
        $reader = User::factory()->create();
        $moderator = User::factory()->moderator()->create();
        $this->get('/api/articles')->assertJsonPath('articles.total', 0);
        $this->withToken($moderator->createToken('moderator')->plainTextToken);
        $response = $this->postJson('/api/articles', [
            'title' => 'From API', 'description' => 'Article text',
        ])->assertCreated()->assertJsonPath('article.title', 'From API');
        $id = $response->json('article.id');
        Queue::assertPushed(VeryLongJob::class);
        Event::assertDispatched(NewArticleEvent::class, fn (NewArticleEvent $event): bool => $event->article->id === $id);
        $this->assertSame($id, $reader->unreadNotifications()->firstOrFail()->data['article_id']);
        $this->assertSame(0, $moderator->unreadNotifications()->count());
        $this->get('/api/articles')->assertJsonPath('articles.total', 1);
    }

    public function test_api_update_supports_patch_and_clears_web_cache_without_stopping_workers(): void
    {
        config(['cache.default' => 'database']);
        $article = Article::factory()->create();
        $this->get('/articles/'.$article->id)->assertOk();
        Cache::forever('illuminate:queue:restart', 123);
        $this->withToken(User::factory()->moderator()->create()->createToken('moderator')->plainTextToken);
        $this->patchJson('/api/articles/'.$article->id, ['title' => 'Updated API title'])
            ->assertOk()->assertJsonPath('article.title', 'Updated API title')
            ->assertJsonPath('article.description', $article->description);
        $this->assertSame(123, Cache::get('illuminate:queue:restart'));
        $this->get('/articles/'.$article->id)->assertSee('Updated API title');
        $this->putJson('/api/articles/'.$article->id, ['title' => 'Incomplete'])
            ->assertUnprocessable()->assertJsonValidationErrors('description');
    }

    public function test_deleting_article_also_deletes_comments_and_invalidates_cache(): void
    {
        $article = Article::factory()->create();
        Comment::factory()->for($article)->create();
        $this->get('/api/articles/'.$article->id)->assertOk();
        $this->withToken(User::factory()->moderator()->create()->createToken('moderator')->plainTextToken);
        $this->deleteJson('/api/articles/'.$article->id)->assertOk()->assertJsonPath('message', 'Статья удалена.');
        $this->assertDatabaseCount('comments', 0);
        $this->get('/api/articles/'.$article->id)->assertNotFound();
        $this->get('/api/articles')->assertJsonPath('articles.total', 0);
    }

    public function test_moderator_submission_is_validated_and_forms_are_not_api_routes(): void
    {
        $this->withToken(User::factory()->moderator()->create()->createToken('moderator')->plainTextToken);
        $this->post('/api/articles', [])->assertUnprocessable()->assertJsonValidationErrors(['title', 'description']);
        $this->get('/api/articles/create')->assertNotFound();
        $article = Article::factory()->create();
        $this->get('/api/articles/'.$article->id.'/edit')->assertNotFound();
    }

    public function test_api_comment_author_is_authenticated_user_and_editing_requires_moderation_again(): void
    {
        $article = Article::factory()->create();
        $reader = User::factory()->create();
        $this->withToken($reader->createToken('reader')->plainTextToken);
        $response = $this->postJson('/api/articles/'.$article->id.'/comments', [
            'body' => 'My comment', 'user_id' => 999, 'moderation_status' => Comment::APPROVED,
        ])->assertCreated()->assertJsonPath('comment.user_id', $reader->id)
            ->assertJsonPath('comment.moderation_status', Comment::PENDING);
        $comment = Comment::findOrFail($response->json('comment.id'));
        $comment->forceFill(['moderation_status' => Comment::APPROVED])->save();
        $this->get('/api/articles/'.$article->id)->assertJsonCount(1, 'comments.data');
        $this->patchJson('/api/articles/'.$article->id.'/comments/'.$comment->id, ['body' => 'Edited'])
            ->assertOk()->assertJsonPath('comment.moderation_status', Comment::PENDING);
        $this->get('/api/articles/'.$article->id)->assertJsonCount(0, 'comments.data');
    }

    public function test_comment_authorization_and_scoped_routes_prevent_access_to_foreign_comments(): void
    {
        $comment = Comment::factory()->create();
        $otherArticle = Article::factory()->create();
        $this->withToken(User::factory()->create()->createToken('stranger')->plainTextToken);
        $path = '/api/articles/'.$comment->article_id.'/comments/'.$comment->id;
        $this->patchJson($path, ['body' => 'Forbidden'])->assertForbidden();
        $this->deleteJson($path)->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->withToken($comment->user->createToken('author')->plainTextToken);
        $this->deleteJson('/api/articles/'.$otherArticle->id.'/comments/'.$comment->id)->assertNotFound();
        $this->deleteJson($path)->assertOk();
        $this->assertDatabaseMissing('comments', ['id' => $comment->id]);
    }
}
