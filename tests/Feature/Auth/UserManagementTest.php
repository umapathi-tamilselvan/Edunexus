<?php

namespace Tests\Feature\Auth;

use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\User;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = $this->createUser(Role::SuperAdmin, ['email' => 'owner@abc.test']);
    }

    public function test_a_super_admin_creates_a_user_with_a_role_who_can_then_sign_in(): void
    {
        $this->actingAsUser($this->superAdmin)
            ->postJson('/api/v1/users', [
                'name' => 'Meena Teacher',
                'email' => 'meena@abc.test',
                'password' => 'teacher-pass-1',
                'roles' => [Role::Teacher->value],
            ])
            ->assertCreated()
            ->assertJsonPath('data.email', 'meena@abc.test')
            ->assertJsonPath('data.roles', [Role::Teacher->value]);

        $this->postJson('/api/v1/auth/tokens', [
            'email' => 'meena@abc.test',
            'password' => 'teacher-pass-1',
            'device_name' => 'phpunit',
        ])->assertCreated()->assertJsonPath('user.roles', [Role::Teacher->value]);
    }

    public function test_a_user_can_be_given_several_roles(): void
    {
        $this->actingAsUser($this->superAdmin)
            ->postJson('/api/v1/users', [
                'name' => 'Ravi',
                'email' => 'ravi@abc.test',
                'password' => 'ravi-pass-123',
                'roles' => [Role::Teacher->value, Role::Accountant->value],
            ])
            ->assertCreated()
            ->assertJsonPath('data.roles', [Role::Teacher->value, Role::Accountant->value]);
    }

    #[TestWith([Role::Teacher])]
    #[TestWith([Role::Accountant])]
    #[TestWith([Role::Guardian])]
    public function test_users_without_user_management_permission_cannot_create_users(Role $role): void
    {
        $this->actingAsUser($this->createUser($role))
            ->postJson('/api/v1/users', [
                'name' => 'X',
                'email' => 'x@abc.test',
                'password' => 'password-123',
                'roles' => [Role::Teacher->value],
            ])
            ->assertForbidden();
    }

    public function test_an_admin_creates_users_but_cannot_create_a_super_admin(): void
    {
        $admin = $this->createUser(Role::Admin);
        $payload = ['name' => 'X', 'email' => 'x@abc.test', 'password' => 'password-123'];

        $this->actingAsUser($admin)
            ->postJson('/api/v1/users', [...$payload, 'roles' => [Role::SuperAdmin->value]])
            ->assertForbidden();

        $this->actingAsUser($admin)
            ->postJson('/api/v1/users', [...$payload, 'roles' => [Role::Principal->value]])
            ->assertCreated();
    }

    public function test_a_super_admin_can_create_another_super_admin(): void
    {
        $this->actingAsUser($this->superAdmin)
            ->postJson('/api/v1/users', [
                'name' => 'Co-owner',
                'email' => 'coowner@abc.test',
                'password' => 'password-123',
                'roles' => [Role::SuperAdmin->value],
            ])
            ->assertCreated()
            ->assertJsonPath('data.roles', [Role::SuperAdmin->value]);
    }

    public function test_creating_a_user_validates_its_input(): void
    {
        $this->actingAsUser($this->superAdmin)
            ->postJson('/api/v1/users', [
                'name' => '',
                'email' => 'owner@abc.test',
                'password' => 'short',
                'roles' => ['Janitor'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name' => 'The name field is required.',
                'email' => 'The email has already been taken.',
                'password' => 'The password field must be at least 8 characters.',
                'roles.0' => 'The selected roles.0 is invalid.',
            ]);
    }

    public function test_roles_of_a_user_can_be_replaced(): void
    {
        $teacher = $this->createUser(Role::Teacher);

        $this->actingAsUser($this->superAdmin)
            ->putJson("/api/v1/users/{$teacher->id}/roles", ['roles' => [Role::Principal->value]])
            ->assertOk()
            ->assertJsonPath('data.roles', [Role::Principal->value]);

        $this->assertSame([Role::Principal->value], $teacher->fresh()->getRoleNames()->all());
    }

    public function test_an_admin_cannot_change_a_super_admins_roles(): void
    {
        $this->actingAsUser($this->createUser(Role::Admin))
            ->putJson("/api/v1/users/{$this->superAdmin->id}/roles", ['roles' => [Role::Teacher->value]])
            ->assertForbidden();
    }

    public function test_the_last_super_admin_cannot_be_demoted(): void
    {
        $this->actingAsUser($this->superAdmin)
            ->putJson("/api/v1/users/{$this->superAdmin->id}/roles", ['roles' => [Role::Admin->value]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['roles' => 'The institution must keep at least one Super Admin.']);
    }

    public function test_a_super_admin_can_step_down_while_another_remains(): void
    {
        $this->createUser(Role::SuperAdmin);

        $this->actingAsUser($this->superAdmin)
            ->putJson("/api/v1/users/{$this->superAdmin->id}/roles", ['roles' => [Role::Admin->value]])
            ->assertOk()
            ->assertJsonPath('data.roles', [Role::Admin->value]);
    }

    public function test_users_with_user_management_list_users_with_their_roles(): void
    {
        $this->createUser(Role::Teacher, ['name' => 'Aarav Teacher']);

        $this->actingAsUser($this->superAdmin)
            ->getJson('/api/v1/users')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Aarav Teacher')
            ->assertJsonPath('data.0.roles', [Role::Teacher->value])
            ->assertJsonCount(2, 'data');
    }

    public function test_listing_users_and_roles_requires_user_management(): void
    {
        $teacher = $this->createUser(Role::Teacher);

        $this->actingAsUser($teacher)->getJson('/api/v1/users')->assertForbidden();
        $this->actingAsUser($teacher)->getJson('/api/v1/roles')->assertForbidden();
    }

    public function test_roles_are_listed_with_their_permissions(): void
    {
        $response = $this->actingAsUser($this->superAdmin)->getJson('/api/v1/roles')->assertOk();

        $this->assertSame(
            array_map(fn (Role $role): string => $role->value, Role::cases()),
            $response->json('data.*.name'),
        );
    }

    private function actingAsUser(User $user): static
    {
        return $this->withToken($this->issueToken($user->email));
    }
}
