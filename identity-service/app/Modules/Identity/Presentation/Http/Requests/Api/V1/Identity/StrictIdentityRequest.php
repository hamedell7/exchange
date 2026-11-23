<?php

declare(strict_types=1);

namespace App\Modules\Identity\Presentation\Http\Requests\Api\V1\Identity;

use App\Modules\Identity\Domain\ValueObjects\EmailAddress;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

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
        return [
            'bail',
            'required',
            'string',
            'max:'.EmailAddress::MAX_LENGTH,
            function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value)) {
                    return;
                }

                try {
                    new EmailAddress($value);
                } catch (InvalidArgumentException) {
                    $fail('Please provide a valid email address.');
                }
            },
        ];
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
