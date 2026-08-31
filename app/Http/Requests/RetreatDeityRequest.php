<?php

namespace App\Http\Requests;

use App\Enums\MantraColor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RetreatDeityRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'color' => ['nullable', Rule::enum(MantraColor::class)],
            'position' => ['nullable', 'integer', 'min:0', 'max:65535'],
            // Cada imagen llega de una de dos formas: un archivo nuevo, o la
            // ruta de una ya cargada que se elige de la grilla.
            'image' => ['nullable', 'image', 'max:4096'],
            'syllable_image' => ['nullable', 'image', 'max:4096'],
            'image_path' => ['nullable', 'string', 'max:255'],
            'syllable_image_path' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'color' => 'color',
            'position' => 'orden',
            'image' => 'imagen de la deidad',
            'syllable_image' => 'imagen de la sílaba',
            'image_path' => 'imagen de la deidad',
            'syllable_image_path' => 'imagen de la sílaba',
        ];
    }
}
