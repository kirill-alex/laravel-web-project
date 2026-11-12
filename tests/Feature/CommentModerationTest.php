<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommentModerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_comments_wait_for_moderation_even_when_status_is_forged(): void
    {
        $article = Article::factory()->create();
        $this->actingAs(User::factory()->create());

        $this->post(route('articles.comments.store', $article), [
            'body' => 'Unreviewed comment',
            'moderation_status' => Comment::APPROVED,
        ])->assertRedirect(route('articles.show', $article))
            ->assertSessionHas('status', 'Комментарий добавлен и ожидает модерации.');

        $comment = $article->comments()->firstOrFail();
        $this->assertSame(Comment::PENDING, $comment->moderation_status);
        $this->get(route('articles.show', $article))->assertOk()->assertDontSee('Unreviewed comment')
            ->assertSee('Комментарий добавлен и ожидает модерации.');
    }

    public function test_only_approved_comments_are_public_and_counted(): void
    {
        $article = Article::factory()->create();
        $approved = Comment::factory()->approved()->for($article)->create();
        $pending = Comment::factory()->for($article)->create();
        $rejected = Comment::factory()->rejected()->for($article)->create();

        $this->get(route('articles.show', $article))->assertOk()
            ->assertSee($approved->body)->assertDontSee($pending->body)->assertDontSee($rejected->body)
            ->assertViewHas('comments', fn ($comments): bool => $comments->total() === 1);
        $this->actingAs(User::factory()->moderator()->create());
        $this->get(route('articles.show', $article))->assertOk()
            ->assertDontSee($pending->body)->assertDontSee($rejected->body);
    }

    public function test_queue_contains_only_pending_comments_with_author_and_article(): void
    {
        $pending = Comment::factory()->create();
        $approved = Comment::factory()->approved()->create();
        $rejected = Comment::factory()->rejected()->create();
        $this->actingAs(User::factory()->moderator()->create());

        $this->get(route('moderation.comments.index'))->assertOk()
            ->assertSee($pending->body)->assertSee($pending->user->name)->assertSee($pending->article->title)
            ->assertDontSee($approved->body)->assertDontSee($rejected->body)
            ->assertSee('Принять')->assertSee('Отклонить');
        $this->get('/')->assertSee('Модерация комментариев');
    }

    public function test_guests_and_readers_cannot_access_moderation_or_make_decisions(): void
    {
        $pending = Comment::factory()->create();

        $this->get(route('moderation.comments.index'))->assertRedirect(route('login'));
        $this->patch(route('moderation.comments.approve', $pending))->assertRedirect(route('login'));
        $this->patch(route('moderation.comments.reject', $pending))->assertRedirect(route('login'));
        $this->actingAs($pending->user);
        $this->get(route('moderation.comments.index'))->assertForbidden();
        $this->patch(route('moderation.comments.approve', $pending))->assertForbidden();
        $this->patch(route('moderation.comments.reject', $pending))->assertForbidden();
        $this->get('/')->assertDontSee('Модерация комментариев');
        $this->assertSame(Comment::PENDING, $pending->fresh()->moderation_status);
    }

    public function test_approval_publishes_a_comment_and_removes_it_from_the_queue(): void
    {
        $pending = Comment::factory()->create();
        $this->actingAs(User::factory()->moderator()->create());

        $this->patch(route('moderation.comments.approve', $pending))
            ->assertRedirect(route('moderation.comments.index'))->assertSessionHas('status', 'Комментарий одобрен.');

        $this->assertSame(Comment::APPROVED, $pending->fresh()->moderation_status);
        $this->get(route('articles.show', $pending->article))->assertSee($pending->body);
        $this->get(route('moderation.comments.index'))->assertDontSee($pending->body);
    }

    public function test_rejection_keeps_a_comment_hidden_and_removes_it_from_the_queue(): void
    {
        $pending = Comment::factory()->create();
        $this->actingAs(User::factory()->moderator()->create());

        $this->patch(route('moderation.comments.reject', $pending))
            ->assertRedirect(route('moderation.comments.index'))->assertSessionHas('status', 'Комментарий отклонён.');

        $this->assertSame(Comment::REJECTED, $pending->fresh()->moderation_status);
        $this->get(route('articles.show', $pending->article))->assertDontSee($pending->body);
        $this->get(route('moderation.comments.index'))->assertDontSee($pending->body);
    }

    public function test_a_decision_cannot_be_overwritten_by_a_stale_request(): void
    {
        $comment = Comment::factory()->create();
        $this->actingAs(User::factory()->moderator()->create());

        $this->patch(route('moderation.comments.approve', $comment))->assertRedirect();
        $this->patch(route('moderation.comments.reject', $comment))->assertStatus(409);
        $this->assertSame(Comment::APPROVED, $comment->fresh()->moderation_status);
    }

    public function test_author_edits_return_an_approved_comment_to_moderation(): void
    {
        $comment = Comment::factory()->approved()->create();
        $this->actingAs($comment->user);

        $this->put(route('articles.comments.update', [$comment->article, $comment]), [
            'body' => 'Edited text',
            'moderation_status' => Comment::APPROVED,
        ])->assertRedirect()->assertSessionHas('status', 'Комментарий обновлён и ожидает модерации.');

        $this->assertSame(Comment::PENDING, $comment->fresh()->moderation_status);
        $this->get(route('articles.show', $comment->article))->assertDontSee('Edited text');
        $this->actingAs(User::factory()->moderator()->create());
        $this->get(route('moderation.comments.index'))->assertSee('Edited text');
    }

    public function test_moderator_edits_preserve_approval(): void
    {
        $comment = Comment::factory()->approved()->create();
        $this->actingAs(User::factory()->moderator()->create());

        $this->put(route('articles.comments.update', [$comment->article, $comment]), ['body' => 'Moderated text'])
            ->assertRedirect();

        $this->assertSame(Comment::APPROVED, $comment->fresh()->moderation_status);
        $this->get(route('articles.show', $comment->article))->assertSee('Moderated text');
    }

    public function test_the_queue_is_paginated_and_shows_oldest_comments_first(): void
    {
        Comment::factory(11)->create();
        $this->actingAs(User::factory()->moderator()->create());

        $this->get(route('moderation.comments.index'))->assertOk()
            ->assertViewHas('comments', fn ($comments): bool => $comments->count() === 10 && $comments->total() === 11 && $comments->first()->id === 1
            );
        $this->get(route('moderation.comments.index').'?page=2')->assertOk()
            ->assertViewHas('comments', fn ($comments): bool => $comments->count() === 1);
    }
}
