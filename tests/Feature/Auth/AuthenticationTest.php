<?php

namespace Tests\Feature\Auth;

use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\User;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = $this->createUser(Role::Teacher, ['name' => 'Meena Teacher']);
    }

    public function test_the_mobile_app_gets_a_token_and_the_user_with_roles_permissions_and_modules(): void
    {
        $response = $this->postJson('/api/v1/auth/tokens', [
            'email' => $this->teacher->email,
            'password' => self::PASSWORD,
            'device_name' => 'Pixel 9',
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.name', 'Meena Teacher')
            ->assertJsonPath('user.roles', [Role::Teacher->value]);
        $this->assertContains('attendance.mark', $response->json('user.permissions'));
        $this->assertNotContains('fee.collect', $response->json('user.permissions'));
        $this->assertContains('examination', $response->json('user.modules'));
        $this->assertNotContains('hostel', $response->json('user.modules'));

        $this->withToken($response->json('token'))
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.email', $this->teacher->email);

        $this->assertSame('Pixel 9', PersonalAccessToken::query()->sole()->name);
    }

    public function test_a_wrong_password_is_rejected(): void
    {
        $this->postJson('/api/v1/auth/tokens', [
            'email' => $this->teacher->email,
            'password' => 'wrong-password',
            'device_name' => 'Pixel 9',
        ])->assertUnprocessable()->assertJsonValidationErrors(['email' => 'These credentials do not match our records.']);
    }

    public function test_token_requests_require_email_password_and_device_name(): void
    {
        $this->postJson('/api/v1/auth/tokens', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'email' => 'The email field is required.',
                'password' => 'The password field is required.',
                'device_name' => 'The device name field is required.',
            ]);
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_revoking_the_current_token_deletes_it(): void
    {
        $token = $this->issueToken($this->teacher->email);

        $this->withToken($token)
            ->deleteJson('/api/v1/auth/tokens/current')
            ->assertNoContent();

        $this->assertSame(0, PersonalAccessToken::query()->count());
    }

    public function test_the_spa_signs_in_with_a_session_cookie_on_its_own_domain(): void
    {
        $this->withHeader('Referer', 'http://localhost/')
            ->postJson('/api/v1/auth/login', [
                'email' => $this->teacher->email,
                'password' => self::PASSWORD,
            ])
            ->assertOk()
            ->assertJsonPath('data.email', $this->teacher->email);
    }

    public function test_spa_sessions_are_stored_in_the_clients_database(): void
    {
        config(['session.driver' => 'database']);

        $this->withHeader('Referer', 'http://localhost/')
            ->postJson('/api/v1/auth/login', [
                'email' => $this->teacher->email,
                'password' => self::PASSWORD,
            ])
            ->assertOk();

        $this->assertDatabaseHas('sessions', ['user_id' => $this->teacher->id]);
    }

    public function test_cookie_sign_in_is_refused_for_requests_not_from_the_web_app(): void
    {
        $this->withHeader('Referer', 'https://evil.example.com/')
            ->postJson('/api/v1/auth/login', [
                'email' => $this->teacher->email,
                'password' => self::PASSWORD,
            ])
            ->assertBadRequest()
            ->assertJsonPath('message', 'Cookie login is only available to the EduNexus web app.');
    }

    public function test_sign_in_attempts_are_rate_limited(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/v1/auth/tokens', [
                'email' => $this->teacher->email,
                'password' => 'wrong-password',
                'device_name' => 'Pixel 9',
            ])->assertUnprocessable();
        }

        $this->postJson('/api/v1/auth/tokens', [
            'email' => $this->teacher->email,
            'password' => self::PASSWORD,
            'device_name' => 'Pixel 9',
        ])->assertTooManyRequests();
    }
}
