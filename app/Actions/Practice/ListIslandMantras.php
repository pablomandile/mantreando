<?php

namespace App\Actions\Practice;

use App\Models\Mantra;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ListIslandMantras
{
    /**
     * La biblioteca tal como la consume la isla de práctica: mantras
     * accesibles con las preferencias del usuario y el orden personal.
     *
     * Sale de un solo lugar porque tiene dos consumidores que DEBEN coincidir:
     * el bootstrap del API (lo que se cachea en IndexedDB) y la precarga de la
     * pantalla de práctica (lo que se pinta en el primer render). Si el orden
     * o las prefs difirieran, el select cambiaría de contenido solo, al llegar
     * la cache.
     *
     * @return Collection<int, Mantra>
     */
    public function handle(User $user): Collection
    {
        $prefs = DB::table('mantra_user')
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('mantra_id');

        return Mantra::query()
            ->accessibleBy($user)
            ->with('category')
            // Mismo orden personal que la biblioteca (pivot.position)
            ->leftJoin('mantra_user', function ($join) use ($user) {
                $join->on('mantra_user.mantra_id', '=', 'mantras.id')
                    ->where('mantra_user.user_id', $user->id);
            })
            ->orderByRaw('COALESCE(mantra_user.position, 999999)')
            ->orderBy('mantras.name')
            ->select('mantras.*')
            ->get()
            ->each(function (Mantra $mantra, int $index) use ($prefs) {
                $mantra->userPrefs = $prefs->get($mantra->id);
                $mantra->sortIndex = $index; // orden personal, para la isla
            });
    }
}
