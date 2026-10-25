<?php

namespace Tests\Feature;

use App\Domain\Identity\Enums\Role;
use Tests\TestCase;

/**
 * The queue dashboard shows job payloads, which can hold students'
 * personal data, so only a Super Admin opens it.
 */
class HorizonDashboardTest extends TestCase
{
    public function test_a_super_admin_opens_the_queue_dashboard(): void
    {
        $this->actingAs($this->createUser(Role::SuperAdmin))
            ->get('/horizon')
            ->assertOk()
            ->assertSee('Horizon');
    }

    public function test_an_admin_gets_403_from_the_queue_dashboard(): void
    {
        $this->actingAs($this->createUser(Role::Admin))
            ->get('/horizon')
            ->assertForbidden();
    }

    public function test_a_guest_gets_403_from_the_queue_dashboard_instead_of_the_app_shell(): void
    {
        $this->get('/horizon')->assertForbidden();
    }
}
