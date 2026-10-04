<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * The external book catalog could not be reached or answered with an error.
 * The client is expected to fall back to manual entry.
 */
class CatalogUnavailableException extends RuntimeException
{
    /**
     * An outage of the external catalog is expected from time to time: log one line, not a stack trace.
     */
    public function report(): bool
    {
        Log::warning('Open Library is unavailable: '.($this->getPrevious()?->getMessage() ?? $this->getMessage()));

        return true;
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => 'catalog.unavailable'], 503);
    }
}
