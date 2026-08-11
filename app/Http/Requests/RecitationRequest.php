<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecitationRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            // Sin techo bajo: son textos largos (los votos, los cuatro
            // inconmensurables), y la columna es TEXT.
            'text' => ['required', 'string', 'max:20000'],
            'position' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'title' => 'título',
            'text' => 'texto',
            'position' => 'orden',
        ];
    }
}
