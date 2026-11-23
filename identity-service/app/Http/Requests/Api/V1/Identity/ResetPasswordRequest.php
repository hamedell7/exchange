<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Identity;

use App\Modules\Identity\Domain\Policies\PasswordPolicy;

final class ResetPasswordRequest extends StrictIdentityRequest
{
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/'],
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

    public function messages(): array
    {
        return [
            'password.min' => 'The password must be at least :min characters.',
            'password.max' => 'The password may not be greater than :max characters.',
            'password.confirmed' => 'The password confirmation does not match.',
        ];
    }
}
