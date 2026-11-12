<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_read_articles_without_management_controls(): void
    {
        $article = Article::factory()->create();

        $this->get('/articles')->assertOk()->assertSee($article->title)
            ->assertDontSee('+ Добавить статью');
        $this->get('/articles/'.$article->id)->assertOk()->assertSee($article->title)
            ->assertDontSee('Редактировать')->assertDontSee('Удалить');
        $this->get('/')->assertOk()->assertSee(route('articles.index'), false);
    }

    public function test_all_article_mutations_and_forms_require_authentication(): void
    {
        $article = Article::factory()->create();
        $originalTitle = $article->title;

        $this->get('/articles/create')->assertRedirect(route('login'));
        $this->get('/articles/'.$article->id.'/edit')->assertRedirect(route('login'));
        $this->post('/articles', ['title' => 'New', 'description' => 'Text'])
            ->assertRedirect(route('login'));
        $this->put('/articles/'.$article->id, ['title' => 'Changed', 'description' => 'Text'])
            ->assertRedirect(route('login'));
        $this->delete('/articles/'.$article->id)->assertRedirect(route('login'));

        $this->assertDatabaseCount('articles', 1);
        $this->assertSame($originalTitle, $article->fresh()->title);
    }

    public function test_authenticated_users_can_create_update_and_delete_articles(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get('/articles')->assertOk()->assertSee('+ Добавить статью');
        $this->get('/articles/create')->assertOk();

        $this->post('/articles', ['title' => 'New article', 'description' => 'Text'])
            ->assertRedirect('/articles');
        $article = Article::where('title', 'New article')->firstOrFail();

        $this->get('/articles/'.$article->id)->assertOk()
            ->assertSee('Редактировать')->assertSee('Удалить');
        $this->get('/articles/'.$article->id.'/edit')->assertOk();
        $this->put('/articles/'.$article->id, ['title' => 'Updated article', 'description' => 'New text'])
            ->assertRedirect('/articles');
        $this->assertDatabaseHas('articles', ['id' => $article->id, 'title' => 'Updated article']);

        $this->delete('/articles/'.$article->id)->assertRedirect('/articles');
        $this->assertDatabaseMissing('articles', ['id' => $article->id]);
    }

    public function test_article_pagination_shows_five_records_per_page(): void
    {
        Article::factory(6)->create();

        $this->get('/articles')->assertOk()->assertViewHas('articles', function ($articles): bool {
            return $articles->count() === 5 && $articles->total() === 6;
        });
        $this->get('/articles?page=2')->assertOk()->assertViewHas('articles', function ($articles): bool {
            return $articles->count() === 1 && $articles->currentPage() === 2;
        });
    }
}
