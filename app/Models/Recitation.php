<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Oración o yoga que se lee (no se cuenta en el mala). Ver la migración
 * para por qué vive fuera de mantras.
 *
 * @property int $id
 * @property string $slug
 * @property string $title
 * @property string $text
 * @property int $position
 */
#[Fillable(['slug', 'title', 'text', 'position'])]
class Recitation extends Model
{
    public $timestamps = true;
}
