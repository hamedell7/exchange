<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Identity;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

abstract class StrictIdentityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<int, mixed>
     */
    protected function emailAddressRules(): array
    {
        return ['bail', 'required', 'string', 'email', 'max:254'];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $input = $this->isJson() ? $this->json()->all() : $this->all();
            $allowed = array_keys($this->rules());

            foreach (array_diff(array_keys($input), $allowed) as $unexpectedField) {
                $validator->errors()->add(
                    (string) $unexpectedField,
                    'This field is not allowed.',
                );
            }
        });
    }
}
