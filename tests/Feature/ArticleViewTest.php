<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ArticleView;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_successful_article_opening_is_recorded_including_cache_hits(): void
    {
        $article = Article::factory()->create();

        $this->get(route('articles.show', $article))->assertOk();
        $this->get(route('articles.show', $article))->assertOk();

        $this->assertDatabaseCount('article_views', 2);
        $this->assertDatabaseHas('article_views', [
            'article_id' => $article->id,
            'url' => route('articles.show', $article),
        ]);
        $this->assertTrue(ArticleView::firstOrFail()->article->is($article));
    }

    public function test_other_pages_missing_articles_and_head_requests_are_not_counted(): void
    {
        $article = Article::factory()->create();

        $this->get('/articles')->assertOk();
        $this->get('/about')->assertOk();
        $this->get('/articles/999999')->assertNotFound();
        $this->call('HEAD', route('articles.show', $article))->assertOk();
        $this->actingAs(User::factory()->moderator()->create());
        $this->get(route('articles.edit', $article))->assertOk();

        $this->assertDatabaseCount('article_views', 0);
    }

    public function test_view_urls_do_not_store_query_parameters(): void
    {
        $article = Article::factory()->create();

        $this->get(route('articles.show', $article).'?page=2&token=private-value')->assertOk();

        $this->assertSame(route('articles.show', $article), ArticleView::firstOrFail()->url);
    }

    public function test_view_statistics_survive_article_deletion(): void
    {
        $article = Article::factory()->create();
        $this->get(route('articles.show', $article))->assertOk();

        $article->delete();

        $this->assertDatabaseCount('article_views', 1);
        $this->assertNull(ArticleView::firstOrFail()->article_id);
    }
}
