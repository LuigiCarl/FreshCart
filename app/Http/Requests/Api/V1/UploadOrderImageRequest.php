<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UploadOrderImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }
}
