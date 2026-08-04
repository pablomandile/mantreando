<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class SyncPracticeSessionsRequest extends FormRequest
{
    /**
     * Solo valida la forma del batch; cada sesión se valida por separado en
     * RecordPracticeSessions para que un ítem inválido no tire 422 al lote.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'sessions' => ['required', 'array', 'min:1', 'max:50'],
        ];
    }
}
