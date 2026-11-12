<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class CommentAuthorTest extends TestCase
{
    use RefreshDatabase;

    public function test_comment_authorship_is_available_in_both_directions(): void
    {
        $comment = Comment::factory()->approved()->create();

        $this->assertTrue($comment->user->comments->contains($comment));
        $this->get(route('articles.show', $comment->article))->assertOk()
            ->assertSee($comment->user->name);
    }

    public function test_the_management_gate_allows_only_the_author_and_moderator(): void
    {
        $comment = Comment::factory()->approved()->create();
        $author = $comment->user;
        $otherReader = User::factory()->create();
        $moderator = User::factory()->moderator()->create();

        foreach (['manage-comment', 'update', 'delete'] as $ability) {
            $this->assertTrue(Gate::forUser($author)->allows($ability, $comment));
            $this->assertTrue(Gate::forUser($moderator)->allows($ability, $comment));
            $this->assertFalse(Gate::forUser($otherReader)->allows($ability, $comment));
        }
    }

    public function test_the_interface_shows_management_controls_only_for_owned_comments(): void
    {
        $own = Comment::factory()->approved()->create();
        $foreign = Comment::factory()->approved()->for($own->article)->create();
        $this->actingAs($own->user);

        $this->get(route('articles.show', $own->article))->assertOk()
            ->assertSee(route('articles.comments.edit', [$own->article, $own]), false)
            ->assertSee(route('articles.comments.destroy', [$own->article, $own]), false)
            ->assertDontSee(route('articles.comments.edit', [$foreign->article, $foreign]), false)
            ->assertDontSee(route('articles.comments.destroy', [$foreign->article, $foreign]), false);
    }

    public function test_an_author_cannot_reassign_a_comment_or_move_it_to_another_article(): void
    {
        $comment = Comment::factory()->approved()->create();
        $otherUser = User::factory()->create();
        $otherArticle = Article::factory()->create();
        $this->actingAs($comment->user);

        $this->put(route('articles.comments.update', [$comment->article, $comment]), [
            'body' => 'Updated by author',
            'user_id' => $otherUser->id,
            'article_id' => $otherArticle->id,
        ])->assertRedirect(route('articles.show', $comment->article));

        $this->assertDatabaseHas('comments', [
            'id' => $comment->id,
            'body' => 'Updated by author',
            'user_id' => $comment->user_id,
            'article_id' => $comment->article_id,
        ]);
    }

    public function test_another_reader_receives_a_custom_denial_for_direct_requests(): void
    {
        $comment = Comment::factory()->approved()->create();
        $this->actingAs(User::factory()->create());
        $parameters = [$comment->article, $comment];
        $message = 'Изменять и удалять комментарий могут только его автор и модератор.';

        $this->get(route('articles.comments.edit', $parameters))->assertForbidden()->assertSee($message);
        $this->putJson(route('articles.comments.update', $parameters), ['body' => 'Changed'])
            ->assertForbidden()->assertExactJson(['message' => $message]);
        $this->deleteJson(route('articles.comments.destroy', $parameters))
            ->assertForbidden()->assertExactJson(['message' => $message]);
        $this->assertSame($comment->body, $comment->fresh()->body);
    }

    public function test_the_author_still_cannot_submit_invalid_comment_text(): void
    {
        $comment = Comment::factory()->approved()->create();
        $this->actingAs($comment->user);

        $this->put(route('articles.comments.update', [$comment->article, $comment]), ['body' => '   '])
            ->assertSessionHasErrors('body');
        $this->assertSame($comment->body, $comment->fresh()->body);
    }
}
