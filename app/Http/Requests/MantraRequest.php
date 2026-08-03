<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MantraRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'original_name' => ['nullable', 'string', 'max:255'],
            'transliteration' => ['nullable', 'string', 'max:255'],
            'text' => ['required', 'string', 'max:2000'],
            'translation' => ['nullable', 'string', 'max:2000'],
            'description' => ['nullable', 'string', 'max:5000'],
            'benefits' => ['nullable', 'string', 'max:5000'],
            'category_id' => ['required', 'integer', 'exists:mantra_categories,id'],
            'image' => ['nullable', 'image', 'max:2048'], // 2 MB
            'remove_image' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'text' => 'texto',
            'category_id' => 'categoría',
            'image' => 'imagen',
        ];
    }
}
