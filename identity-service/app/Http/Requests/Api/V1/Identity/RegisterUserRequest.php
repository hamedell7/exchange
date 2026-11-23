<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Identity;

use App\Modules\Identity\Domain\Entities\User;
use App\Modules\Identity\Domain\Policies\PasswordPolicy;

final class RegisterUserRequest extends StrictIdentityRequest
{
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
                'min:'.User::MIN_NAME_LENGTH,
                'max:'.User::MAX_NAME_LENGTH,
            ],
            'email' => $this->emailAddressRules(),
            'password' => [
                'bail',
                'required',
                'string',
                'min:'.PasswordPolicy::MIN_LENGTH,
                'max:'.PasswordPolicy::MAX_LENGTH,
                'confirmed',
            ],
            'password_confirmation' => [
                'required',
                'string',
                'min:'.PasswordPolicy::MIN_LENGTH,
                'max:'.PasswordPolicy::MAX_LENGTH,
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
            'password.required' => 'The password field is required.',
            'password.min' => 'The password must be at least :min characters.',
            'password.max' => 'The password may not be greater than :max characters.',
            'password.confirmed' => 'The password confirmation does not match.',
        ];
    }
}
