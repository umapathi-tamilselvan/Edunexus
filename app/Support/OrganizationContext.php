<?php

namespace App\Support;

use App\Support\Deployment\InstitutionType;
use App\Support\Settings\Setting;

/**
 * The client this deployment serves, from its .env, plus its settings
 * from its database.
 */
final class OrganizationContext
{
    public const YEAR_START_MONTH = 'academic_year.start_month';

    public const ATTENDANCE_EDIT_WINDOW = 'attendance.edit_window_days';

    /** @var array<string, mixed>|null */
    private ?array $settings = null;

    public function code(): string
    {
        return (string) config('edunexus.client.code');
    }

    public function name(): string
    {
        return (string) config('edunexus.client.name');
    }

    public function type(): InstitutionType
    {
        return InstitutionType::from((string) config('edunexus.client.type'));
    }

    /**
     * A setting from the settings table, loaded once per request.
     */
    public function setting(string $key, mixed $default = null): mixed
    {
        $this->settings ??= Setting::query()->pluck('value', 'key')->all();

        return $this->settings[$key] ?? $default;
    }

    /**
     * The month (1–12) the client's academic years start in.
     */
    public function yearStartMonth(): int
    {
        return (int) $this->setting(self::YEAR_START_MONTH, $this->type()->yearStartMonth());
    }

    /**
     * For how many days before today teachers may still change attendance;
     * 0, the default, is the day itself.
     */
    public function attendanceEditWindowDays(): int
    {
        return max(0, (int) $this->setting(self::ATTENDANCE_EDIT_WINDOW, 0));
    }
}
