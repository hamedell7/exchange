<?php

declare(strict_types=1);

namespace App\Modules\Identity\Presentation\Http\Requests\Api\V1\Identity;

final class ForgotPasswordRequest extends StrictIdentityRequest
{
    public function rules(): array
    {
        return ['email' => $this->emailAddressRules()];
    }
}
