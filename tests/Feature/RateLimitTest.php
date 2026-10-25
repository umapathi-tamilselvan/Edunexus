<?php

namespace Tests\Feature;

use App\Domain\Identity\Enums\Role;
use Tests\TestCase;

/**
 * Signed-in users are limited per account and guests per IP. A school's
 * users usually share one public IP, so the per-IP limits are high enough
 * for a whole school and the per-account limits do the real work.
 */
class RateLimitTest extends TestCase
{
    public function test_a_signed_in_user_gets_120_requests_a_minute_of_their_own(): void
    {
        $first = $this->issueToken($this->createUser()->email);
        $second = $this->issueToken($this->createUser()->email);
        $this->withToken($first)->getJson('/api/v1/me')->assertOk();

        $this->withToken($first)->getJson('/api/v1/me')
            ->assertHeader('X-RateLimit-Limit', 120)
            ->assertHeader('X-RateLimit-Remaining', 118);
        $this->withToken($second)->getJson('/api/v1/me')
            ->assertHeader('X-RateLimit-Remaining', 119);
    }

    public function test_guests_on_one_ip_share_600_requests_a_minute(): void
    {
        $this->getJson('/api/v1/branding')
            ->assertOk()
            ->assertHeader('X-RateLimit-Limit', 600)
            ->assertHeader('X-RateLimit-Remaining', 599);
    }

    public function test_one_ip_gets_300_sign_in_attempts_a_minute_across_accounts(): void
    {
        foreach (range(1, 300) as $attempt) {
            $this->postJson('/api/v1/auth/tokens', ['email' => "user{$attempt}@school.test", 'password' => 'wrong-password', 'device_name' => 'Pixel 9'])
                ->assertUnprocessable();
        }

        $this->postJson('/api/v1/auth/tokens', ['email' => $this->createUser(Role::Teacher)->email, 'password' => self::PASSWORD, 'device_name' => 'Pixel 9'])
            ->assertTooManyRequests();
    }
}
