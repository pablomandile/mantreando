<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RetreatMantraRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'text' => ['required', 'string', 'max:5000'],
            // La cifra no se asume: un retiro puede pedir 100.000 de un
            // mantra y 10.000 de su sílaba.
            'goal' => ['required', 'integer', 'min:1', 'max:10000000'],
            'position' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'text' => 'texto',
            'goal' => 'cantidad',
            'position' => 'orden',
        ];
    }
}
