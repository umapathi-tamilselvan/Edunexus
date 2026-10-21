<?php

namespace Tests\Feature;

use App\Domain\Identity\Enums\Role;
use App\Support\Modules\Module;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class BrandingAndModulesTest extends TestCase
{
    public function test_branding_comes_from_the_clients_environment_and_type(): void
    {
        $this->getJson('/api/v1/branding')
            ->assertOk()
            ->assertJsonPath('data.code', 'test')
            ->assertJsonPath('data.name', 'Test Public School')
            ->assertJsonPath('data.type', 'school')
            ->assertJsonPath('data.terminology.class', 'Class')
            ->assertJsonPath('data.privacy_notice_version', '2026-10');
    }

    #[TestWith(['school', 'transport', 'hostel'])]
    #[TestWith(['college', 'hostel', 'transport'])]
    #[TestWith(['coaching', 'fee', 'examination'])]
    public function test_modules_default_to_the_institution_type(string $type, string $included, string $excluded): void
    {
        config(['edunexus.client.type' => $type]);
        $token = $this->issueToken($this->createUser(Role::Admin)->email);

        $modules = $this->withToken($token)->getJson('/api/v1/me')->assertOk()->json('data.modules');

        $this->assertContains($included, $modules);
        $this->assertNotContains($excluded, $modules);
    }

    public function test_env_overrides_enable_and_disable_modules(): void
    {
        config(['edunexus.modules' => ['enable' => ['hostel', 'not-a-module'], 'disable' => ['transport']]]);
        $token = $this->issueToken($this->createUser(Role::Admin)->email);

        $modules = $this->withToken($token)->getJson('/api/v1/me')->json('data.modules');

        $this->assertContains('hostel', $modules);
        $this->assertNotContains('transport', $modules);
        $this->assertNotContains('not-a-module', $modules);
    }

    public function test_a_disabled_module_blocks_its_routes(): void
    {
        Route::middleware(['api', 'auth:sanctum', 'entitled:'.Module::Transport->value])->get('/api/v1/_test/transport', fn () => ['ok' => true]);
        $token = $this->issueToken($this->createUser(Role::Admin)->email);

        $this->withToken($token)->getJson('/api/v1/_test/transport')->assertOk();

        config(['edunexus.modules.disable' => ['transport']]);

        $this->withToken($token)->getJson('/api/v1/_test/transport')
            ->assertForbidden()
            ->assertJsonPath('message', 'This module is not enabled for your institution.');
    }
}
