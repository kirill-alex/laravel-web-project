<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Comment;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CommentTest extends TestCase
{
    use RefreshDatabase;

    public function test_comments_are_public_and_scoped_to_their_article(): void
    {
        $comment = Comment::factory()->create(['body' => '<script>alert(1)</script>']);
        $otherComment = Comment::factory()->create(['body' => 'Other article comment']);

        $this->get(route('articles.show', $comment->article))->assertOk()
            ->assertSee($comment->body)->assertSee($comment->user->name)
            ->assertDontSee($comment->body, false)->assertDontSee($otherComment->body)
            ->assertDontSee('Редактировать комментарий')->assertDontSee('Удалить комментарий');
    }

    public function test_a_moderator_can_create_edit_and_delete_a_comment(): void
    {
        $article = Article::factory()->create();
        $user = User::factory()->moderator()->create();
        $this->actingAs($user);

        $this->post(route('articles.comments.store', $article), [
            'body' => 'My comment',
            'user_id' => User::factory()->create()->id,
            'article_id' => Article::factory()->create()->id,
        ])->assertRedirect(route('articles.show', $article));

        $comment = $article->comments()->firstOrFail();
        $this->assertTrue($comment->user->is($user));
        $this->get(route('articles.show', $article))->assertSee('Редактировать комментарий');
        $this->get(route('articles.comments.edit', [$article, $comment]))
            ->assertOk()->assertSee('My comment');
        $this->put(route('articles.comments.update', [$article, $comment]), ['body' => 'Updated'])
            ->assertRedirect(route('articles.show', $article));
        $this->assertDatabaseHas('comments', ['id' => $comment->id, 'body' => 'Updated']);

        $this->delete(route('articles.comments.destroy', [$article, $comment]))
            ->assertRedirect(route('articles.show', $article));
        $this->assertDatabaseMissing('comments', ['id' => $comment->id]);
    }

    public function test_guests_cannot_manage_comments(): void
    {
        $comment = Comment::factory()->create();
        $parameters = [$comment->article, $comment];

        $this->post(route('articles.comments.store', $comment->article), ['body' => 'New'])
            ->assertRedirect(route('login'));
        $this->get(route('articles.comments.edit', $parameters))->assertRedirect(route('login'));
        $this->put(route('articles.comments.update', $parameters), ['body' => 'Changed'])
            ->assertRedirect(route('login'));
        $this->delete(route('articles.comments.destroy', $parameters))->assertRedirect(route('login'));
        $this->assertDatabaseCount('comments', 1);
        $this->assertSame($comment->body, $comment->fresh()->body);
    }

    public function test_users_cannot_change_someone_elses_comment(): void
    {
        $comment = Comment::factory()->create();
        $parameters = [$comment->article, $comment];
        $this->actingAs(User::factory()->create());

        $this->get(route('articles.comments.edit', $parameters))->assertForbidden();
        $this->put(route('articles.comments.update', $parameters), ['body' => 'Changed'])->assertForbidden();
        $this->delete(route('articles.comments.destroy', $parameters))->assertForbidden();
        $this->get(route('articles.show', $comment->article))->assertDontSee('Редактировать комментарий');
        $this->assertSame($comment->body, $comment->fresh()->body);
    }

    public function test_a_comment_cannot_be_accessed_under_a_different_article(): void
    {
        $comment = Comment::factory()->create();
        $otherArticle = Article::factory()->create();
        $parameters = [$otherArticle, $comment];
        $this->actingAs($comment->user);

        $this->get(route('articles.comments.edit', $parameters))->assertNotFound();
        $this->put(route('articles.comments.update', $parameters), ['body' => 'Changed'])->assertNotFound();
        $this->delete(route('articles.comments.destroy', $parameters))->assertNotFound();
        $this->assertDatabaseHas('comments', ['id' => $comment->id, 'article_id' => $comment->article_id]);
    }

    public function test_comment_validation_rejects_empty_and_oversized_text(): void
    {
        $comment = Comment::factory()->create();
        $this->actingAs(User::factory()->moderator()->create());

        foreach (['', '   ', str_repeat('x', 5001)] as $body) {
            $this->post(route('articles.comments.store', $comment->article), ['body' => $body])
                ->assertSessionHasErrors('body');
            $this->put(route('articles.comments.update', [$comment->article, $comment]), ['body' => $body])
                ->assertSessionHasErrors('body');
        }
        $this->assertDatabaseCount('comments', 1);
        $this->assertSame($comment->body, $comment->fresh()->body);
    }

    public function test_deleting_an_article_removes_only_its_comments(): void
    {
        $article = Article::factory()->has(Comment::factory(3))->create();
        $otherComment = Comment::factory()->create();
        $this->actingAs(User::factory()->moderator()->create());

        $this->delete(route('articles.destroy', $article))->assertRedirect('/articles');

        $this->assertDatabaseMissing('comments', ['article_id' => $article->id]);
        $this->assertDatabaseHas('comments', ['id' => $otherComment->id]);
    }

    public function test_cascade_deletion_is_enforced_by_the_database(): void
    {
        $comment = Comment::factory()->create();

        DB::table('articles')->where('id', $comment->article_id)->delete();

        $this->assertDatabaseMissing('comments', ['id' => $comment->id]);
    }

    public function test_comments_are_paginated_newest_first(): void
    {
        $article = Article::factory()->has(Comment::factory(11))->create();
        $newest = $article->comments()->latest('id')->first();
        $oldest = $article->comments()->oldest('id')->first();

        $this->get(route('articles.show', $article))->assertOk()->assertSee($newest->body)
            ->assertDontSee($oldest->body)->assertViewHas('comments', function ($comments): bool {
                return $comments->count() === 10 && $comments->total() === 11;
            });
        $this->get(route('articles.show', $article).'?page=2')->assertOk()->assertSee($oldest->body);
    }

    public function test_the_seeder_generates_articles_with_comments(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('articles', 10);
        $this->assertDatabaseCount('comments', 30);
        $this->assertSame(3, Article::firstOrFail()->comments()->count());
    }
}
