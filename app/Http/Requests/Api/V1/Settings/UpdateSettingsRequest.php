<?php

namespace App\Http\Requests\Api\V1\Settings;

use App\Support\Settings\ClientSettings;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

/**
 * Settings arrive nested by group, the way Laravel validates dotted keys.
 * Only the settings a client admin may change are accepted; a read-only or
 * unknown setting is refused rather than silently ignored.
 */
class UpdateSettingsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ClientSettings::rules();
    }

    /**
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $patterns = array_keys($this->rules());

                foreach (array_keys(Arr::dot($this->all())) as $path) {
                    if (! Arr::first($patterns, fn (string $pattern): bool => Str::is($pattern, $path))) {
                        $validator->errors()->add($path, 'This is not a setting the app knows.');
                    }
                }
            },
        ];
    }
}
