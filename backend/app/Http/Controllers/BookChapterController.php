<?php

namespace App\Http\Controllers;

use App\Http\Resources\BookChapterResource;
use App\Models\BookText;
use Illuminate\Support\Facades\Gate;

class BookChapterController extends Controller
{
    /**
     * One chapter of a text: its sanitized HTML, its title and length, and the quotes selected from it.
     */
    public function show(BookText $bookText, int $position): BookChapterResource
    {
        Gate::authorize('view', $bookText);

        $chapter = $bookText->chapters()->where('position', $position)->firstOrFail();

        $highlights = $bookText->quotes()
            ->where('chapter_position', $position)
            ->orderBy('start_offset')
            ->orderBy('id')
            ->get(['id', 'start_offset', 'end_offset', 'content', 'note'])
            ->map(fn ($quote) => [
                'id' => $quote->id,
                'start' => $quote->start_offset,
                'end' => $quote->end_offset,
                'content' => $quote->content,
                'note' => $quote->note,
            ])
            ->all();

        return new BookChapterResource($chapter, $highlights);
    }
}
