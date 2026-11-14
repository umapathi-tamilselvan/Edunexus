<?php

namespace Tests\Feature\Settings;

use App\Domain\Identity\Enums\Role;
use Tests\TestCase;

/**
 * The client's settings: defaults until changed, changes for admins only,
 * and read-only keys refused.
 */
class ClientSettingsTest extends TestCase
{
    private string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminToken = $this->issueToken($this->createUser(Role::Admin)->email);
    }

    /**
     * @return array<string, mixed>
     */
    private function values(string $token): array
    {
        return collect($this->withToken($token)->getJson('/api/v1/settings')->assertOk()->json('data'))
            ->map(fn (array $setting): mixed => $setting['value'])
            ->all();
    }

    public function test_every_setting_shows_its_default_until_it_is_changed(): void
    {
        $values = $this->values($this->adminToken);

        $this->assertSame('Asia/Kolkata', $values['school.timezone']);
        $this->assertSame('10:00', $values['attendance.cutoff_time']);
        $this->assertSame(['sunday'], $values['weekly_off_days']);
        $this->assertFalse($values['insights.section_rank_enabled']);
        $this->assertSame(75, $values['insights.at_risk.attendance_below']);
        $this->assertSame(35, $values['insights.at_risk.overall_below']);
        $this->assertSame(15, $values['insights.at_risk.trend_drop']);

        $this->assertArrayHasKey('institution.terminology', $values);
    }

    public function test_an_admin_changes_settings_and_the_new_values_are_returned(): void
    {
        $response = $this->withToken($this->adminToken)->putJson('/api/v1/settings', [
            'attendance' => ['cutoff_time' => '09:30'],
            'weekly_off_days' => ['saturday', 'sunday'],
            'insights' => ['at_risk' => ['attendance_below' => 80]],
        ])->assertOk()->json('data');

        $this->assertSame('09:30', $response['attendance.cutoff_time']['value']);
        $this->assertSame(['saturday', 'sunday'], $response['weekly_off_days']['value']);
        $this->assertSame(80, $response['insights.at_risk.attendance_below']['value']);
        $this->assertDatabaseHas('settings', ['key' => 'attendance.cutoff_time']);
        $this->assertDatabaseHas('settings', ['key' => 'weekly_off_days']);
        $this->assertDatabaseHas('settings', ['key' => 'insights.at_risk.attendance_below']);
    }

    public function test_a_setting_set_to_null_takes_its_default_again(): void
    {
        $this->withToken($this->adminToken)->putJson('/api/v1/settings', ['insights' => ['at_risk' => ['attendance_below' => 60]]])->assertOk();
        $this->assertSame(60, $this->values($this->adminToken)['insights.at_risk.attendance_below']);

        $this->withToken($this->adminToken)->putJson('/api/v1/settings', ['insights' => ['at_risk' => ['attendance_below' => null]]])->assertOk();

        $this->assertSame(75, $this->values($this->adminToken)['insights.at_risk.attendance_below']);
        $this->assertDatabaseMissing('settings', ['key' => 'insights.at_risk.attendance_below']);
    }

    public function test_invalid_values_are_refused_with_the_field_named(): void
    {
        $this->withToken($this->adminToken)->putJson('/api/v1/settings', [
            'school' => ['timezone' => 'Mars/Olympus'],
            'weekly_off_days' => ['funday'],
            'insights' => ['at_risk' => ['attendance_below' => 140]],
        ])->assertUnprocessable()->assertJsonValidationErrors(['school.timezone', 'weekly_off_days.0', 'insights.at_risk.attendance_below']);
    }

    public function test_a_read_only_setting_cannot_be_sent(): void
    {
        $this->withToken($this->adminToken)->putJson('/api/v1/settings', ['branding' => ['theme' => 'dark']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['branding.theme']);
    }

    public function test_a_setting_the_app_does_not_know_is_refused(): void
    {
        $this->withToken($this->adminToken)->putJson('/api/v1/settings', ['school' => ['motto' => 'Learn']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['school.motto']);
    }

    public function test_only_someone_who_manages_settings_sees_or_changes_them(): void
    {
        $token = $this->issueToken($this->createUser(Role::Teacher)->email);

        $this->withToken($token)->getJson('/api/v1/settings')->assertForbidden();
        $this->withToken($token)->putJson('/api/v1/settings', ['attendance' => ['cutoff_time' => '09:00']])->assertForbidden();
    }
}
