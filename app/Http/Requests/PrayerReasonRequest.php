<?php

namespace App\Http\Requests;

use App\Enums\MantraColor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PrayerReasonRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            // Corto a propósito: entra en un chip al lado del nombre.
            'name' => ['required', 'string', 'max:80'],
            'color' => ['nullable', Rule::enum(MantraColor::class)],
            'position' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'color' => 'color',
            'position' => 'orden',
        ];
    }
}
