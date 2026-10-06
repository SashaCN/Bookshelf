<?php

namespace App\Services\Epub;

use App\Enums\ReaderError;
use RuntimeException;

/**
 * A book that cannot be imported, with the business-rule code that tells the reader why.
 */
class EpubImportException extends RuntimeException
{
    public function __construct(public readonly ReaderError $error, string $detail = '')
    {
        parent::__construct($detail === '' ? $error->value : "{$error->value}: {$detail}");
    }
}
