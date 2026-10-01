<?php

namespace Tests\Feature;

use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\User;
use OwenIt\Auditing\Models\Audit;
use Tests\TestCase;

class AuditTrailTest extends TestCase
{
    public function test_changes_to_audited_records_are_logged_without_secrets(): void
    {
        $user = $this->createUser(Role::Teacher, ['name' => 'Old Name']);

        User::query()->findOrFail($user->id)->update(['name' => 'New Name', 'password' => 'another-secret-pass']);

        $audit = Audit::query()->where('event', 'updated')->sole();

        $this->assertSame(['name' => 'Old Name'], $audit->old_values);
        $this->assertSame(['name' => 'New Name'], $audit->new_values);
    }
}
