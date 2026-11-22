<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Identity;

use App\Modules\Identity\Application\Commands\RegisterUserCommand;
use App\Modules\Identity\Domain\ValueObjects\EmailAddress;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use InvalidArgumentException;

final class RegisterUserRequest extends FormRequest
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
            'name' => [
                'bail',
                'required',
                'string',
                'min:'.RegisterUserCommand::MIN_NAME_LENGTH,
                'max:'.RegisterUserCommand::MAX_NAME_LENGTH,
            ],
            'email' => [
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
            ],
            'password' => [
                'bail',
                'required',
                'string',
                'min:'.RegisterUserCommand::MIN_PASSWORD_LENGTH,
                'max:'.RegisterUserCommand::MAX_PASSWORD_LENGTH,
                'confirmed',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'The name field is required.',
            'name.min' => 'The name must be at least :min characters.',
            'name.max' => 'The name may not be greater than :max characters.',
            'email.required' => 'The email field is required.',
            'email.max' => 'The email may not be greater than :max characters.',
            'password.required' => 'The password field is required.',
            'password.min' => 'The password must be at least :min characters.',
            'password.max' => 'The password may not be greater than :max characters.',
            'password.confirmed' => 'The password confirmation does not match.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'name',
            'email' => 'email',
            'password' => 'password',
        ];
    }
}
