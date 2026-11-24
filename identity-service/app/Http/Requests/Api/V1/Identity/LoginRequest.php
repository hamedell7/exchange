<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Identity;

final class LoginRequest extends StrictIdentityRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'email' => $this->emailAddressRules(),
            'password' => ['required', 'string', 'max:128'],
            'device_name' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }
}
