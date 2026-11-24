<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Identity;

final class VerifyEmailRequest extends StrictIdentityRequest
{
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/'],
        ];
    }
}
