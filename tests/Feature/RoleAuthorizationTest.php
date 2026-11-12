<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Comment;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\ModeratorSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RoleAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_always_assigns_reader_role(): void
    {
        $moderator = Role::firstOrCreate(['name' => Role::MODERATOR]);

        $this->post('/register', [
            'name' => 'Reader',
            'email' => 'reader@example.com',
            'password' => 'secret123',
            'role_id' => $moderator->id,
            'role' => Role::MODERATOR,
        ])->assertRedirect('/login');

        $user = User::where('email', 'reader@example.com')->firstOrFail();
        $this->assertSame(Role::READER, $user->role->name);
        $this->assertFalse($user->isModerator());
        $this->assertTrue($user->role->users()->whereKey($user->id)->exists());
    }

    public function test_readers_can_read_and_add_comments_but_cannot_manage_articles(): void
    {
        $article = Article::factory()->create();
        $reader = User::factory()->create();
        $this->actingAs($reader);

        $this->get('/articles')->assertOk()->assertDontSee('+ Добавить статью')
            ->assertDontSee('Создать новость');
        $this->get(route('articles.show', $article))->assertOk()->assertSee('Добавить комментарий')
            ->assertDontSee('Редактировать')->assertDontSee('Удалить');
        $this->get('/articles/create')->assertForbidden()
            ->assertSee('Создавать статьи может только модератор.');
        $this->post('/articles', ['title' => 'New', 'description' => 'Text'])->assertForbidden();
        $this->get(route('articles.edit', $article))->assertForbidden();
        $this->put(route('articles.update', $article), ['title' => 'Changed', 'description' => 'Text'])
            ->assertForbidden();
        $this->delete(route('articles.destroy', $article))->assertForbidden();

        $this->post(route('articles.comments.store', $article), ['body' => 'Reader comment'])
            ->assertRedirect(route('articles.show', $article));
        $this->assertDatabaseHas('comments', ['user_id' => $reader->id, 'body' => 'Reader comment']);
        $this->assertDatabaseHas('articles', ['id' => $article->id, 'title' => $article->title]);
        $this->assertDatabaseCount('articles', 1);
    }

    public function test_readers_can_edit_and_delete_their_own_comments(): void
    {
        $comment = Comment::factory()->create();
        $this->actingAs($comment->user);
        $parameters = [$comment->article, $comment];

        $this->get(route('articles.show', $comment->article))->assertOk()
            ->assertSee('Редактировать комментарий')->assertSee('Удалить комментарий');
        $this->get(route('articles.comments.edit', $parameters))->assertOk();
        $this->put(route('articles.comments.update', $parameters), ['body' => 'Changed'])
            ->assertRedirect(route('articles.show', $comment->article));
        $this->assertSame('Changed', $comment->fresh()->body);
        $this->delete(route('articles.comments.destroy', $parameters))
            ->assertRedirect(route('articles.show', $comment->article));
        $this->assertDatabaseMissing('comments', ['id' => $comment->id]);
    }

    public function test_moderators_can_manage_someone_elses_comment(): void
    {
        $comment = Comment::factory()->create();
        $this->actingAs(User::factory()->moderator()->create());
        $parameters = [$comment->article, $comment];

        $this->get(route('articles.show', $comment->article))->assertOk()
            ->assertSee('Редактировать комментарий')->assertSee('Удалить комментарий')
            ->assertSee('Создать новость');
        $this->get(route('articles.comments.edit', $parameters))->assertOk();
        $this->put(route('articles.comments.update', $parameters), ['body' => 'Moderated'])
            ->assertRedirect(route('articles.show', $comment->article));
        $this->assertDatabaseHas('comments', ['id' => $comment->id, 'body' => 'Moderated']);
        $this->delete(route('articles.comments.destroy', $parameters))->assertRedirect();
        $this->assertDatabaseMissing('comments', ['id' => $comment->id]);
    }

    public function test_moderator_gate_runs_before_policies_and_custom_gate(): void
    {
        $reader = User::factory()->create();
        $moderator = User::factory()->moderator()->create();

        $this->assertTrue(Gate::forUser($moderator)->allows('moderate-content'));
        $this->assertTrue(Gate::forUser($moderator)->allows('create', Article::class));
        $denial = Gate::forUser($reader)->inspect('moderate-content');
        $this->assertTrue($denial->denied());
        $this->assertSame('Управление статьями и комментариями доступно только модератору.', $denial->message());
    }

    public function test_json_denials_return_a_custom_message_and_status(): void
    {
        $this->actingAs(User::factory()->create());

        $this->postJson('/articles', ['title' => 'New', 'description' => 'Text'])
            ->assertForbidden()->assertExactJson([
                'message' => 'Создавать статьи может только модератор.',
            ]);
    }

    public function test_seeders_are_repeatable_and_assign_roles_to_existing_users(): void
    {
        $reader = User::factory()->create();
        DB::table('users')->where('id', $reader->id)->update(['role_id' => null]);
        $this->seed([RoleSeeder::class, ModeratorSeeder::class]);
        $this->seed([RoleSeeder::class, ModeratorSeeder::class]);

        $this->assertDatabaseCount('roles', 2);
        $this->assertDatabaseCount('users', 2);
        $this->assertSame(Role::READER, $reader->fresh()->role->name);
        $moderator = User::where('email', 'moderator@example.com')->firstOrFail();
        $this->assertTrue($moderator->isModerator());
        $this->assertTrue(Hash::check('Moderator123!', $moderator->password));
        $moderator->update(['password' => Hash::make('ChangedPassword123!')]);
        $this->seed(ModeratorSeeder::class);
        $this->assertTrue(Hash::check('ChangedPassword123!', $moderator->fresh()->password));
    }

    public function test_a_moderator_cannot_address_a_comment_through_another_article(): void
    {
        $comment = Comment::factory()->create();
        $otherArticle = Article::factory()->create();
        $this->actingAs(User::factory()->moderator()->create());

        $this->delete(route('articles.comments.destroy', [$otherArticle, $comment]))->assertNotFound();
        $this->assertDatabaseHas('comments', ['id' => $comment->id]);
    }
}
