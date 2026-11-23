<?php

namespace App\Actions\Reader;

use App\Models\ReaderDocument;
use App\Models\User;
use App\Services\Reader\EpubParser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class ImportEpub
{
    public function __construct(private readonly EpubParser $parser) {}

    /**
     * Checks the file first and keeps it only when it is a sound EPUB.
     *
     * @throws ValidationException
     */
    public function handle(User $user, UploadedFile $file): ReaderDocument
    {
        try {
            $parsed = $this->parser->parse(
                $file->getRealPath(),
                pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            );
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable) {
            // Last line of defence: whatever the parser chokes on, the file is not a usable EPUB.
            throw ValidationException::withMessages(['file' => ['reader.invalid_epub']]);
        }

        $path = Storage::disk('local')->putFileAs('reader/'.$user->id, $file, Str::uuid().'.epub');

        try {
            return $user->readerDocuments()->create([
                'title' => $parsed['title'],
                'author' => $parsed['author'],
                'path' => $path,
                'chapters' => $parsed['chapters'],
                'chapter_titles' => $parsed['titles'],
                'chapters_count' => count($parsed['chapters']),
            ]);
        } catch (Throwable $exception) {
            // No row, no use for the file.
            Storage::disk('local')->delete($path);

            throw $exception;
        }
    }
}
