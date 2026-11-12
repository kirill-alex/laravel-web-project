<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_a_user_with_a_hashed_password(): void
    {
        $this->post('/register', [
            'name' => 'Кирилл',
            'email' => 'kirill@example.com',
            'password' => 'secret123',
        ])->assertRedirect('/login');

        $user = User::where('email', 'kirill@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('secret123', $user->password));
        $this->assertGuest();
    }

    public function test_legacy_signin_routes_still_allow_registration(): void
    {
        $this->get('/signin')->assertRedirect('/register');
        $this->post('/signin', [
            'name' => 'Кирилл',
            'email' => 'legacy@example.com',
            'password' => 'secret123',
        ])->assertRedirect('/login');

        $this->assertDatabaseHas('users', ['email' => 'legacy@example.com']);
    }

    public function test_login_succeeds_and_redirects_home(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secret123')]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ])->assertRedirect('/')->assertSessionHas('auth_token');

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_returns_to_the_requested_protected_page(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secret123')]);

        $this->get('/articles/create')->assertRedirect(route('login'));
        $this->post('/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ])->assertRedirect('/articles/create');

        $this->assertAuthenticatedAs($user);
        $this->get('/articles/create')->assertOk();
    }

    public function test_invalid_credentials_do_not_authenticate(): void
    {
        $user = User::factory()->create();

        $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'incorrect',
        ])->assertRedirect('/login')->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_authenticated_users_are_redirected_away_from_guest_forms(): void
    {
        $this->actingAs(User::factory()->create());

        foreach (['/login', '/register', '/signin'] as $path) {
            $this->get($path)->assertRedirect('/');
        }
    }

    public function test_logout_ends_the_session_and_revokes_tokens(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secret123')]);
        $this->post('/login', ['email' => $user->email, 'password' => 'secret123']);

        $this->post('/logout')->assertRedirect('/')->assertSessionMissing('auth_token');

        $this->assertGuest();
        $this->assertSame(0, $user->tokens()->count());
        $this->get('/articles/create')->assertRedirect(route('login'));
    }
}
