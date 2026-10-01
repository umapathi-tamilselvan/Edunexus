<?php

namespace App\Http\Requests\Api\V1\Academic;

use App\Domain\Academic\Models\Subject;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubjectRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => strtoupper(trim($this->input('code')))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Subject|null $subject */
        $subject = $this->route('subject');

        return [
            'code' => ['required', 'string', 'max:20', 'alpha_dash:ascii', Rule::unique('subjects', 'code')->ignore($subject)],
            'name' => ['required', 'string', 'max:100'],
        ];
    }
}
