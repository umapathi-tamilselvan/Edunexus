<?php

namespace App\Http\Requests\Api\V1\Student;

use App\Domain\Student\Enums\GuardianRelation;
use App\Domain\Student\Models\Guardian;
use Closure;
use Illuminate\Validation\Rule;

/**
 * Validation rules for guardian details, shared by student creation and
 * linking a guardian later.
 */
final class GuardianRules
{
    /**
     * @return array<string, mixed>
     */
    public static function for(string $prefix = ''): array
    {
        return [
            $prefix.'name' => ['required', 'string', 'max:255'],
            $prefix.'phone' => ['required', 'string', 'max:20', self::indianMobile()],
            $prefix.'email' => ['nullable', 'string', 'email', 'max:255'],
            $prefix.'relation' => ['required', Rule::enum(GuardianRelation::class)],
            $prefix.'is_primary' => ['sometimes', 'boolean'],
        ];
    }

    private static function indianMobile(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! preg_match('/^[6-9]\d{9}$/', Guardian::normalisePhone((string) $value))) {
                $fail('The :attribute must be a 10-digit Indian mobile number.');
            }
        };
    }
}
