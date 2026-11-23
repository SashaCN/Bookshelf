<?php

use App\Models\ReaderDocument;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->user = User::factory()->create(['timezone' => 'Europe/Kyiv']);
    $this->stranger = User::factory()->create();
    $this->document = ReaderDocument::factory()->for($this->user)->create(['chapters' => array_map(fn ($i) => "c$i.xhtml", range(1, 120))]);
    $this->travelTo(Carbon::parse('2026-10-15 12:00:00', 'UTC'));
});

function moveTo(ReaderDocument $document, int $chapter): void
{
    test()->putJson("/api/reader/{$document->id}/position", ['chapter' => $chapter, 'fraction' => 0.1])->assertOk();
}

/** @return list<int> */
function recordedChapters(ReaderDocument $document): array
{
    return DB::table('reader_chapter_reads')->where('reader_document_id', $document->id)->orderBy('chapter')->pluck('chapter')->map(fn ($c) => (int) $c)->all();
}

describe('recording', function () {
    it('records every chapter left behind going forward', function () {
        $this->actAs($this->user);
        moveTo($this->document, 3);

        expect(recordedChapters($this->document))->toBe([0, 1, 2]);
        expect(DB::table('reader_chapter_reads')->value('read_on'))->toBe('2026-10-15');
    });

    it('records nothing within the same chapter or going backwards', function () {
        $this->actAs($this->user);
        moveTo($this->document, 5);
        DB::table('reader_chapter_reads')->delete();

        moveTo($this->document, 5);
        moveTo($this->document, 2);

        expect(recordedChapters($this->document))->toBe([]);
    });

    it('counts a chapter once, on the first day, when it is read again', function () {
        $this->actAs($this->user);
        moveTo($this->document, 2);
        moveTo($this->document, 0);
        $this->travelTo(Carbon::parse('2026-10-16 12:00:00', 'UTC'));
        moveTo($this->document, 3);

        expect(recordedChapters($this->document))->toBe([0, 1, 2]);
        expect(DB::table('reader_chapter_reads')->where('chapter', 0)->value('read_on'))->toBe('2026-10-15');
        expect(DB::table('reader_chapter_reads')->where('chapter', 2)->value('read_on'))->toBe('2026-10-16');
    });

    it('records only the chapter that was left after a jump of more than 50 chapters', function () {
        $this->actAs($this->user);
        moveTo($this->document, 2);
        moveTo($this->document, 60);

        expect(recordedChapters($this->document))->toBe([0, 1, 2]);
    });

    it('treats a jump of exactly 50 chapters as reading', function () {
        $this->actAs($this->user);
        moveTo($this->document, 50);

        expect(recordedChapters($this->document))->toHaveCount(50);
    });

    it('uses the reader\'s local day at the day boundary', function () {
        // 22:30 UTC on the 15th is already 01:30 on the 16th in Kyiv (UTC+3).
        $this->travelTo(Carbon::parse('2026-10-15 22:30:00', 'UTC'));
        $this->actAs($this->user);
        moveTo($this->document, 1);

        expect(DB::table('reader_chapter_reads')->value('read_on'))->toBe('2026-10-16');
    });

    it('does not record anything for a position that fails validation', function () {
        $this->actAs($this->user);
        $this->putJson("/api/reader/{$this->document->id}/position", ['chapter' => 500, 'fraction' => 0])->assertUnprocessable();

        expect(recordedChapters($this->document))->toBe([]);
    });

    it('does not touch a foreign document', function () {
        $this->actAs($this->stranger);
        $this->putJson("/api/reader/{$this->document->id}/position", ['chapter' => 3, 'fraction' => 0])->assertNotFound();

        expect(recordedChapters($this->document))->toBe([]);
    });

    it('removes the records with the document', function () {
        $this->actAs($this->user);
        moveTo($this->document, 3);
        expect(DB::table('reader_chapter_reads')->count())->toBe(3);

        $this->deleteJson("/api/reader/{$this->document->id}")->assertNoContent();

        expect(DB::table('reader_chapter_reads')->count())->toBe(0);
    });
});

describe('statistics', function () {
    function chapterRow(ReaderDocument $document, int $chapter, string $day): void
    {
        DB::table('reader_chapter_reads')->insert(['reader_document_id' => $document->id, 'chapter' => $chapter, 'read_on' => $day, 'created_at' => now()]);
    }

    it('counts chapters of the month and the year in the summary, and books in progress', function () {
        chapterRow($this->document, 0, '2026-10-01');
        chapterRow($this->document, 1, '2026-10-15');
        chapterRow($this->document, 2, '2026-03-10');
        chapterRow($this->document, 3, '2025-12-31');
        $this->document->update(['position_chapter' => 4, 'position_fraction' => 0.2, 'last_read_at' => now()->subDays(3)]);
        ReaderDocument::factory()->for($this->user)->create(); // never opened
        ReaderDocument::factory()->for($this->user)->create(['position_chapter' => 2, 'last_read_at' => now()->subDays(40)]); // put aside

        $this->actAs($this->user)->getJson('/api/stats/summary')->assertOk()
            ->assertJsonPath('data.reader', ['chapters_this_month' => 2, 'chapters_this_year' => 3, 'documents_in_progress' => 1]);
    });

    it('does not count the chapters of somebody else', function () {
        $foreign = ReaderDocument::factory()->for($this->stranger)->create(['position_chapter' => 1, 'last_read_at' => now()]);
        chapterRow($foreign, 0, '2026-10-15');

        $this->actAs($this->user)->getJson('/api/stats/summary')->assertOk()
            ->assertJsonPath('data.reader', ['chapters_this_month' => 0, 'chapters_this_year' => 0, 'documents_in_progress' => 0]);
    });

    it('adds chapters to every day of the daily series, zero when none', function () {
        chapterRow($this->document, 0, '2026-10-14');
        chapterRow($this->document, 1, '2026-10-14');
        chapterRow($this->document, 2, '2026-10-15');

        $this->actAs($this->user)->getJson('/api/stats/daily?days=3')->assertOk()
            ->assertJsonPath('data', [
                ['date' => '2026-10-13', 'pages' => 0, 'chapters' => 0],
                ['date' => '2026-10-14', 'pages' => 0, 'chapters' => 2],
                ['date' => '2026-10-15', 'pages' => 0, 'chapters' => 1],
            ])
            ->assertJsonPath('meta.total_pages', 0);
    });
});
