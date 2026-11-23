<?php

namespace App\Http\Requests\Concerns;

use App\Models\Tag;

trait NormalizesTags
{
    /**
     * Tag names are compared in their canonical form, so "  Стоїцизм " and "стоїцизм" are one tag (counted once).
     */
    protected function prepareForValidation(): void
    {
        $tags = $this->input('tags');

        if (! is_array($tags)) {
            return;
        }

        $normalized = array_map(fn (mixed $tag) => is_string($tag) ? Tag::normalize($tag) : $tag, $tags);

        $this->merge(['tags' => array_values(array_unique($normalized, SORT_REGULAR))]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function tagRules(): array
    {
        return [
            'tags' => ['sometimes', 'array', 'max:'.Tag::MAX_PER_QUOTE],
            'tags.*' => ['string', 'min:1', 'max:'.Tag::MAX_LENGTH],
        ];
    }
}
