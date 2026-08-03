<?php

namespace App\Http\Resources;

use App\Models\Mantra;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Mantra
 */
class MantraResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var object{is_favorite?: bool|int, daily_commitment?: ?int, total_goal?: ?int}|null $prefs */
        $prefs = $this->resource->userPrefs ?? null;

        return [
            'id' => $this->id,
            'is_system' => $this->isSystem(),
            'name' => $this->resource->localized('name'),
            'original_name' => $this->original_name,
            'transliteration' => $this->transliteration,
            'text' => $this->text,
            'translation' => $this->resource->localized('translation'),
            'description' => $this->resource->localized('description'),
            'benefits' => $this->resource->localized('benefits'),
            'image_url' => $this->image_url,
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'name' => $this->category->localized_name,
                'slug' => $this->category->slug,
            ]),
            'pivot' => [
                'is_favorite' => (bool) ($prefs->is_favorite ?? false),
                'daily_commitment' => $prefs->daily_commitment ?? null,
                'total_goal' => $prefs->total_goal ?? null,
            ],
        ];
    }
}
