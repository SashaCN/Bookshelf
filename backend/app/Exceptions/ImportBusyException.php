<?php

namespace App\Exceptions;

use App\Enums\ReaderError;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * The server reads one uploaded book at a time (reading one takes a lot of memory), and another is being read.
 * The client is expected to try again in a few seconds.
 */
class ImportBusyException extends RuntimeException
{
    /** Seconds the client is told to wait. */
    public const RETRY_AFTER = 5;

    public function __construct()
    {
        parent::__construct(ReaderError::Busy->value);
    }

    /**
     * Being busy is normal, not worth a log entry.
     */
    public function report(): bool
    {
        return true;
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => ReaderError::Busy->value], 503, ['Retry-After' => (string) self::RETRY_AFTER]);
    }
}
