<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class PrayerIntentionRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'reason_ids' => ['array'],
            'reason_ids.*' => ['integer', 'exists:prayer_reasons,id'],
            // El motivo escrito a mano: una línea, no un texto largo.
            'custom_reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Un nombre sin motivo no dice nada, pero el motivo puede venir de
     * cualquiera de los dos lados. No se puede expresar con required_without:
     * un array vacío cuenta como presente.
     *
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->input('reason_ids', []) === [] && blank($this->input('custom_reason'))) {
                    $validator->errors()->add(
                        'reason_ids',
                        'Elegí al menos un motivo o escribí uno.',
                    );
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'reason_ids' => 'motivos',
            'custom_reason' => 'motivo propio',
        ];
    }
}
