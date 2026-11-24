<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Market;

use Illuminate\Foundation\Http\FormRequest;

final class MarketAssetIndexRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:250'],
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }
}
