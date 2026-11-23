<?php

use App\Models\BookChapter;
use App\Models\BookText;
use App\Models\User;
use App\Models\UserBook;
use Illuminate\Http\UploadedFile;
use Tests\Support\EpubBuilder;

/*
 * A damaged file is the reader's problem, not the server's: whatever is wrong with the archive, the answer is a 422 with
 * the code of a reason, never a 500, and nothing is stored. The damage is made deterministically (a fixed seed).
 */

afterEach(fn () => EpubBuilder::cleanUp());

function validEpubBytes(): string
{
    return (string) file_get_contents(
        EpubBuilder::make()
            ->chapter('Розділ перший', '<p>Це початок <em>книги</em>.</p>')
            ->chapter('Chapter two', '<p>Second chapter.</p>')
            ->toFile()
    );
}

function upload(string $bytes, string $name = 'broken.epub'): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'epub');
    file_put_contents($path, $bytes);

    return new UploadedFile($path, $name, 'application/epub+zip', null, true);
}

/** @return array<string, string> a label and the damaged bytes */
function corruptions(): array
{
    mt_srand(7);
    $valid = validEpubBytes();
    $length = strlen($valid);
    $cases = [
        'cut in the middle' => substr($valid, 0, intdiv($length, 2)),
        'without the end of the archive' => substr($valid, 0, $length - 30),
        'only the first bytes' => substr($valid, 0, 40),
        'zeroes' => str_repeat("\0", 2048),
        'a zip signature and garbage' => "PK\x03\x04".random_bytes(512),
        'random bytes' => random_bytes(4096),
    ];

    // Single bytes flipped inside an otherwise valid archive.
    for ($i = 0; $i < 12; $i++) {
        $bytes = $valid;
        foreach (range(1, 3) as $_) {
            $bytes[mt_rand(0, $length - 1)] = chr(mt_rand(0, 255));
        }
        $cases["three flipped bytes #$i"] = $bytes;
    }

    return $cases;
}

it('refuses a damaged archive with a code and never with a server error', function () {
    $user = User::factory()->create();
    $userBook = UserBook::factory()->for($user)->reading(100)->create(['total_pages' => 320]);

    foreach (corruptions() as $label => $bytes) {
        $response = $this->actingAs($user)
            ->withHeader('Accept', 'application/json')
            ->post("/api/library/{$userBook->id}/text", ['file' => upload($bytes)]);

        $status = $response->getStatusCode();
        // A flipped byte may leave the book readable (a damaged text is still text): 201 is allowed, 5xx is not.
        expect($status)->toBeIn([201, 422], "$label answered $status");

        if ($status === 422) {
            expect($response->json('errors.file.0'))->toStartWith('reader.', $label);
            expect(BookText::query()->where('user_book_id', $userBook->id)->exists())->toBeFalse($label);
        } else {
            // Start the next case from a clean entry.
            BookText::query()->where('user_book_id', $userBook->id)->delete();
        }
    }

    expect(BookChapter::query()->count())->toBe(0);
});

it('answers "not an EPUB" for a truncated archive', function () {
    $user = User::factory()->create();
    $userBook = UserBook::factory()->for($user)->reading(100)->create(['total_pages' => 320]);

    $this->actingAs($user)
        ->withHeader('Accept', 'application/json')
        ->post("/api/library/{$userBook->id}/text", ['file' => upload(substr(validEpubBytes(), 0, 60))])
        ->assertUnprocessable()
        ->assertJsonPath('errors.file', ['reader.not_epub']);
});
