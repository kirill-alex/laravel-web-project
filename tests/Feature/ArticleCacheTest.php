<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ArticleCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'database']);
    }

    public function test_list_data_is_reused_from_database_cache_and_expires_after_five_minutes(): void
    {
        $article = Article::factory()->create(['title' => 'Original title']);
        $this->get('/articles')->assertOk()->assertSee('Original title');
        Article::whereKey($article->id)->update(['title' => 'Changed directly in database']);

        $this->get('/articles')->assertOk()->assertSee('Original title')
            ->assertDontSee('Changed directly in database');
        $this->assertDatabaseHas('cache', ['key' => config('cache.prefix').'articles.index.version']);

        $this->travel(6)->minutes();
        $this->get('/articles')->assertOk()->assertSee('Changed directly in database')
            ->assertDontSee('Original title');
    }

    public function test_each_list_page_has_its_own_cache_and_correct_pagination(): void
    {
        $articles = Article::factory(6)->create();
        $this->get('/articles')->assertOk()->assertSee($articles[0]->title)->assertDontSee($articles[5]->title);
        $this->get('/articles?page=2')->assertOk()->assertSee($articles[5]->title)
            ->assertDontSee($articles[0]->title)->assertViewHas('articles', fn ($page): bool => $page->currentPage() === 2 && $page->count() === 1 && $page->total() === 6
            );
        $this->get('/articles')->assertOk()->assertSee($articles[0]->title)->assertDontSee($articles[5]->title);
    }

    public function test_creation_invalidates_all_list_pages_and_totals(): void
    {
        Article::factory(6)->create();
        $this->get('/articles')->assertOk();
        $this->get('/articles?page=2')->assertOk();
        $version = Cache::get('articles.index.version');
        $this->actingAs(User::factory()->moderator()->create());

        $this->post('/articles', ['title' => 'New cached article', 'description' => 'Text'])->assertRedirect('/articles');

        $this->assertNull(Cache::get('articles.index.version'));
        $this->get('/articles?page=2')->assertOk()->assertSee('New cached article')
            ->assertViewHas('articles', fn ($page): bool => $page->total() === 7 && $page->count() === 2);
        $this->assertNotSame($version, Cache::get('articles.index.version'));
        $this->get('/articles')->assertViewHas('articles', fn ($page): bool => $page->total() === 7);
    }

    public function test_article_and_approved_comments_are_cached_forever_as_safe_arrays(): void
    {
        $article = Article::factory()->create(['title' => 'Cached article title']);
        $comment = Comment::factory()->approved()->for($article)->create(['body' => 'Cached comment text']);
        $this->get(route('articles.show', $article))->assertOk()->assertSee($comment->body);
        Article::whereKey($article->id)->update(['title' => 'New database title']);
        Comment::whereKey($comment->id)->update(['body' => 'New database comment']);
        $this->travel(1)->days();

        $this->get(route('articles.show', $article))->assertOk()->assertSee('Cached article title')
            ->assertSee('Cached comment text')->assertDontSee('New database title')->assertDontSee('New database comment');
        $data = Cache::get('articles.show.'.$article->id);
        $this->assertIsArray($data);
        $this->assertSame(['id', 'name'], array_keys($data['comments'][0]['user']));
        $this->assertStringNotContainsString($comment->user->password, serialize($data));
    }

    public function test_cached_comments_keep_pagination_and_authors(): void
    {
        $article = Article::factory()->has(Comment::factory(11)->approved())->create();
        $oldest = $article->comments()->oldest('id')->firstOrFail();
        $newest = $article->comments()->latest('id')->firstOrFail();
        $this->get(route('articles.show', $article))->assertOk()->assertSee($newest->body)
            ->assertSee($newest->user->name)->assertDontSee($oldest->body);
        $this->get(route('articles.show', $article).'?page=2')->assertOk()
            ->assertSee($oldest->body)->assertSee($oldest->user->name)->assertDontSee($newest->body)
            ->assertViewHas('comments', fn ($page): bool => $page->total() === 11 && $page->count() === 1);
    }

    public function test_updating_an_article_flushes_data_without_stopping_queue_workers(): void
    {
        $article = Article::factory()->create(['title' => 'Old article title']);
        $this->get('/articles')->assertOk();
        $this->get(route('articles.show', $article))->assertOk();
        Cache::forever('illuminate:queue:restart', 1234567890);
        Cache::forever('test-cache-marker', 'will be flushed');
        $this->actingAs(User::factory()->moderator()->create());

        $this->put(route('articles.update', $article), ['title' => 'Updated article title', 'description' => 'Text'])
            ->assertRedirect('/articles');

        $this->assertFalse(Cache::has('articles.show.'.$article->id));
        $this->assertFalse(Cache::has('articles.index.version'));
        $this->assertFalse(Cache::has('test-cache-marker'));
        $this->assertSame(1234567890, Cache::get('illuminate:queue:restart'));
        $this->get('/articles')->assertOk()->assertSee('Updated article title');
        $this->get(route('articles.show', $article))->assertOk()->assertSee('Updated article title');
    }

    public function test_deleting_an_article_clears_cached_lists_and_detail(): void
    {
        $article = Article::factory()->create();
        $this->get('/articles')->assertOk();
        $this->get(route('articles.show', $article))->assertOk();
        $this->actingAs(User::factory()->moderator()->create());

        $this->delete(route('articles.destroy', $article))->assertRedirect('/articles');

        $this->get('/articles')->assertOk()->assertDontSee($article->title);
        $this->get(route('articles.show', $article))->assertNotFound();
    }

    public function test_comment_creation_and_moderation_invalidate_the_article_cache(): void
    {
        $article = Article::factory()->create();
        $reader = User::factory()->create();
        $this->get(route('articles.show', $article))->assertOk();
        $this->actingAs($reader)->post(route('articles.comments.store', $article), ['body' => 'Pending cache test'])
            ->assertRedirect();
        $this->assertFalse(Cache::has('articles.show.'.$article->id));
        $this->get(route('articles.show', $article))->assertDontSee('Pending cache test');
        $comment = $article->comments()->firstOrFail();

        $this->actingAs(User::factory()->moderator()->create())
            ->patch(route('moderation.comments.approve', $comment))->assertRedirect();
        $this->assertFalse(Cache::has('articles.show.'.$article->id));
        $this->get(route('articles.show', $article))->assertSee('Pending cache test');
    }

    public function test_author_edits_remove_the_old_cached_approved_text_until_remoderation(): void
    {
        $comment = Comment::factory()->approved()->create(['body' => 'Previously approved text']);
        $this->get(route('articles.show', $comment->article))->assertSee($comment->body);
        $this->actingAs($comment->user);

        $this->put(route('articles.comments.update', [$comment->article, $comment]), ['body' => 'Pending revised text'])
            ->assertRedirect();

        $this->get(route('articles.show', $comment->article))->assertOk()
            ->assertDontSee('Previously approved text')->assertDontSee('Pending revised text');
    }

    public function test_deleting_a_comment_removes_it_from_a_warmed_article_cache(): void
    {
        $comment = Comment::factory()->approved()->create();
        $this->get(route('articles.show', $comment->article))->assertSee($comment->body);
        $this->actingAs($comment->user);

        $this->delete(route('articles.comments.destroy', [$comment->article, $comment]))->assertRedirect();

        $this->get(route('articles.show', $comment->article))->assertOk()->assertDontSee($comment->body);
    }

    public function test_cached_data_does_not_share_management_controls_between_users(): void
    {
        $comment = Comment::factory()->approved()->create();
        $this->actingAs(User::factory()->moderator()->create());
        $this->get(route('articles.show', $comment->article))->assertSee('Редактировать комментарий');
        $this->actingAs(User::factory()->create());

        $this->get(route('articles.show', $comment->article))->assertOk()
            ->assertDontSee('Редактировать комментарий')->assertDontSee('Удалить комментарий');
        $this->put(route('articles.comments.update', [$comment->article, $comment]), ['body' => 'Forbidden'])
            ->assertForbidden();
    }
}
