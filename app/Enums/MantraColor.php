<?php

namespace App\Enums;

/**
 * Color de la tarjeta de un mantra. Solo se guarda la elección: el degradado
 * se arma solo en CSS a partir de ese color (--mantra-color en Index.vue),
 * igual que los materiales del mala con data-material.
 *
 * Los mantras del sistema traen asignado el color tradicional de cada deidad
 * desde el seeder; en los propios lo elige el usuario.
 */
enum MantraColor: string
{
    case Blue = 'blue';
    case Indigo = 'indigo';
    case Violet = 'violet';
    case Red = 'red';
    case Amber = 'amber';
    case Orange = 'orange';
    case Green = 'green';
    case Teal = 'teal';
    case Neutral = 'neutral';

    public function label(): string
    {
        return match ($this) {
            self::Blue => 'Azul',
            self::Indigo => 'Índigo',
            self::Violet => 'Violeta',
            self::Red => 'Rojo',
            self::Amber => 'Ámbar',
            self::Orange => 'Naranja',
            self::Green => 'Verde',
            self::Teal => 'Turquesa',
            self::Neutral => 'Perla',
        };
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $color) => ['value' => $color->value, 'label' => $color->label()],
            self::cases(),
        );
    }
}
