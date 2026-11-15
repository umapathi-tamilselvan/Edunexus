<?php

namespace App\Support\Settings;

use Illuminate\Validation\Rule;

/**
 * Every client setting the app knows: its default, its rules and whether a
 * client admin may change it. Stored in the settings table; a key with no
 * row takes its default.
 */
final class ClientSettings
{
    public const WEEKDAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    /**
     * @return array<string, array{default: mixed, editable: bool, rules: list<mixed>, used: bool}>
     */
    public static function definitions(): array
    {
        return [
            'academic_year.start_month' => ['default' => null, 'editable' => true, 'rules' => ['integer', 'between:1,12'], 'used' => true],
            'attendance.edit_window_days' => ['default' => 0, 'editable' => true, 'rules' => ['integer', 'between:0,365'], 'used' => true],
            'attendance.mode' => ['default' => 'daily', 'editable' => true, 'rules' => ['string', Rule::in(['daily', 'session', 'period'])], 'used' => true],
            'results.scale' => ['default' => 10, 'editable' => true, 'rules' => ['integer', 'between:1,100'], 'used' => true],
            'school.timezone' => ['default' => 'Asia/Kolkata', 'editable' => true, 'rules' => ['string', Rule::in(\DateTimeZone::listIdentifiers())], 'used' => false],
            'attendance.cutoff_time' => ['default' => '10:00', 'editable' => true, 'rules' => ['date_format:H:i'], 'used' => false],
            'weekly_off_days' => ['default' => ['sunday'], 'editable' => true, 'rules' => ['array', 'max:7'], 'used' => false],
            'insights.section_rank_enabled' => ['default' => false, 'editable' => true, 'rules' => ['boolean'], 'used' => false],
            'insights.at_risk.attendance_below' => ['default' => 75, 'editable' => true, 'rules' => ['integer', 'between:0,100'], 'used' => true],
            'insights.at_risk.overall_below' => ['default' => 35, 'editable' => true, 'rules' => ['integer', 'between:0,100'], 'used' => false],
            'insights.at_risk.trend_drop' => ['default' => 15, 'editable' => true, 'rules' => ['integer', 'between:1,100'], 'used' => false],
            'institution.terminology' => ['default' => null, 'editable' => false, 'rules' => [], 'used' => false],
            'branding.logo_url' => ['default' => null, 'editable' => false, 'rules' => [], 'used' => true],
            'branding.theme' => ['default' => null, 'editable' => false, 'rules' => [], 'used' => true],
        ];
    }

    /**
     * Rules for the keys a client admin may send.
     *
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        $rules = [];

        foreach (self::definitions() as $key => $definition) {
            if ($definition['editable']) {
                $rules[$key] = ['sometimes', 'nullable', ...$definition['rules']];
            }
        }

        $rules['weekly_off_days.*'] = ['string', Rule::in(self::WEEKDAYS)];

        foreach (self::definitions() as $key => $definition) {
            if (! $definition['editable']) {
                $rules[$key] = ['prohibited'];
            }
        }

        return $rules;
    }
}
