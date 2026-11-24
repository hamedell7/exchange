<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Identity;

use App\Enums\Identity\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class ListUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'status' => [
                'sometimes',
                'nullable',
                'string',
                Rule::in(array_map(
                    static fn (UserStatus $status): string => $status->value,
                    UserStatus::cases(),
                )),
            ],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $allowed = ['search', 'status', 'page', 'per_page'];

            foreach (array_diff(array_keys($this->query()), $allowed) as $unexpectedField) {
                $validator->errors()->add(
                    (string) $unexpectedField,
                    'This query parameter is not allowed.',
                );
            }
        });
    }
}
