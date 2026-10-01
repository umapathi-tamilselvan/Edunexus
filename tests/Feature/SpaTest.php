<?php

namespace Tests\Feature;

use Tests\TestCase;

class SpaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_every_page_path_serves_the_app_shell_for_this_client(): void
    {
        foreach (['/', '/login', '/users', '/dashboard/anything'] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertSee('<title>Test Public School</title>', false)
                ->assertSee('"code":"test"', false)
                ->assertSee('"type":"school"', false);
        }
    }

    public function test_unknown_api_paths_are_not_swallowed_by_the_app(): void
    {
        $this->getJson('/api/v1/does-not-exist')->assertNotFound();
        $this->getJson('/sanctum/nothing')->assertNotFound();
    }

    public function test_the_institution_name_is_escaped_in_the_page(): void
    {
        config(['educore.client.name' => '</script><script>alert(1)</script>']);

        $this->get('/')
            ->assertOk()
            ->assertDontSee('</script><script>alert(1)</script>', false);
    }
}
