<?php

namespace App\Http\Resources;

use App\Models\ReadingLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ReadingLog
 */
class ReadingLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'from_page' => $this->from_page,
            'to_page' => $this->to_page,
            // Negative when the reader corrected the page downwards.
            'pages' => $this->pages(),
            'logged_on' => $this->logged_on->toDateString(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
