<?php

use App\Models\BookText;
use App\Models\Quote;
use App\Models\User;
use App\Models\UserBook;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->stranger = User::factory()->create();
    $this->userBook = UserBook::factory()->for($this->user)->reading(100)->create(['total_pages' => 320]);
    $this->text = BookText::factory()->for($this->userBook)->withSampleChapters()->create();
});

it('gives a chapter with its html, title and length', function () {
    $this->actingAs($this->user)->getJson("/api/book-texts/{$this->text->id}/chapters/0")
        ->assertOk()
        ->assertJsonPath('data.position', 0)
        ->assertJsonPath('data.title', 'One')
        ->assertJsonPath('data.html', '<p>The first chapter has a start.</p>')
        ->assertJsonPath('data.characters', 30)
        ->assertJsonPath('data.highlights', []);

    $this->actingAs($this->user)->getJson("/api/book-texts/{$this->text->id}/chapters/2")
        ->assertOk()
        ->assertJsonPath('data.title', null)
        ->assertJsonPath('data.html', '<h2>Three</h2><p>The third one: 😀 end.</p>')
        ->assertJsonPath('data.characters', 5 + 22);
});

it('gives the quotes selected from the chapter to highlight, in the order of the text', function () {
    $later = Quote::factory()->for($this->userBook)->create([
        'book_text_id' => $this->text->id, 'chapter_position' => 0, 'start_offset' => 10, 'end_offset' => 17, 'content' => 'chapter', 'note' => 'a note',
    ]);
    $earlier = Quote::factory()->for($this->userBook)->create([
        'book_text_id' => $this->text->id, 'chapter_position' => 0, 'start_offset' => 4, 'end_offset' => 9, 'content' => 'first',
    ]);
    Quote::factory()->for($this->userBook)->create([
        'book_text_id' => $this->text->id, 'chapter_position' => 1, 'start_offset' => 0, 'end_offset' => 5, 'content' => 'Short',
    ]);
    Quote::factory()->for($this->userBook)->create();

    $response = $this->actingAs($this->user)->getJson("/api/book-texts/{$this->text->id}/chapters/0")
        ->assertOk()
        ->assertJsonCount(2, 'data.highlights');

    expect($response->json('data.highlights'))->toBe([
        ['id' => $earlier->id, 'start' => 4, 'end' => 9, 'content' => 'first', 'note' => null],
        ['id' => $later->id, 'start' => 10, 'end' => 17, 'content' => 'chapter', 'note' => 'a note'],
    ]);
});

it('does not know a chapter the text does not have', function () {
    $this->actingAs($this->user)->getJson("/api/book-texts/{$this->text->id}/chapters/3")->assertNotFound();
    $this->actingAs($this->user)->getJson("/api/book-texts/{$this->text->id}/chapters/-1")->assertNotFound();
    $this->actingAs($this->user)->getJson("/api/book-texts/{$this->text->id}/chapters/abc")->assertNotFound();
    $this->actingAs($this->user)->getJson('/api/book-texts/999999/chapters/0')->assertNotFound();
});

it('answers "not found", not a server error, for a number that is too big to be an id or a position', function () {
    $huge = '99999999999999999999';

    $this->actingAs($this->user)->getJson("/api/book-texts/{$this->text->id}/chapters/{$huge}")->assertNotFound();
    $this->actingAs($this->user)->getJson("/api/book-texts/{$this->text->id}/chapters/1234567")->assertNotFound();
    $this->actingAs($this->user)->getJson("/api/book-texts/{$huge}/chapters/0")->assertNotFound();
    $this->actingAs($this->user)->putJson("/api/book-texts/{$huge}/position", ['chapter' => 0, 'chapter_progress' => 0])->assertNotFound();
    $this->actingAs($this->user)->getJson("/api/library/{$huge}/text")->assertNotFound();
    $this->actingAs($this->user)->deleteJson("/api/library/{$huge}/text")->assertNotFound();
    $this->actingAs($this->user)->postJson("/api/library/{$huge}/text")->assertNotFound();
});

it('still finds the chapters that are there, up to six digits of position', function () {
    $this->actingAs($this->user)->getJson("/api/book-texts/{$this->text->id}/chapters/000002")->assertOk()->assertJsonPath('data.position', 2);
    $this->actingAs($this->user)->getJson("/api/book-texts/{$this->text->id}/chapters/999999")->assertNotFound();
});

it('answers "not found" for somebody else\'s text, and gives them none of its quotes', function () {
    Quote::factory()->for($this->userBook)->create([
        'book_text_id' => $this->text->id, 'chapter_position' => 0, 'start_offset' => 4, 'end_offset' => 9, 'content' => 'first',
    ]);

    $this->actingAs($this->stranger)->getJson("/api/book-texts/{$this->text->id}/chapters/0")->assertNotFound();
});
