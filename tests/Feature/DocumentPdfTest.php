<?php

use App\Models\Document;
use App\Models\DocumentLayout;
use App\Models\DocumentTemplate;
use App\Services\DocumentPdf;
use Database\Seeders\DocumentSampleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function tableHtml(int $rows, string $text = 'Row'): string
{
    $body = '';

    for ($i = 1; $i <= $rows; $i++) {
        $body .= "<tr><td><p>{$text} {$i}</p></td><td><p>{$text} value {$i}</p></td></tr>";
    }

    return "<table><tbody><tr><th><p>Item</p></th><th><p>Value</p></th></tr>{$body}</tbody></table>";
}

test('placeholders are detected on save and keep their values', function () {
    $header = DocumentLayout::create(['type' => 'header', 'name' => 'H', 'content' => '<p>{{ company }}</p>']);

    $document = Document::create([
        'title' => 'T',
        'header_id' => $header->id,
        'body' => '<p>{{client}} {{اسم}} {{reference}} {{date}}</p>',
    ]);

    expect($document->placeholder_values)->toBe(['client' => '', 'اسم' => '', 'company' => '']);

    $document->update(['placeholder_values' => ['client' => 'ACME', 'اسم' => 'x', 'company' => 'y']]);
    $document->update(['body' => '<p>{{client}}</p>']);

    expect($document->placeholder_values)->toBe(['client' => 'ACME', 'company' => 'y']);
});

test('rendered content is sanitized, escaped and repeats table headers', function () {
    $document = Document::create([
        'title' => 'T',
        'body' => '<p style="background:url(http://evil.test/x)">{{client}} {{unknown}}</p>'
            .'<img src="file:///etc/passwd"><script>bad()</script>'.tableHtml(1),
        'placeholder_values' => ['client' => '<b>ACME</b>'],
    ]);

    $html = (fn () => $this->content($document, $document->body))->call(app(DocumentPdf::class));

    expect($html)
        ->not->toContain('evil.test')
        ->not->toContain('<img')
        ->not->toContain('<script')
        ->toContain('&lt;b&gt;ACME&lt;/b&gt;')
        ->toContain('{{unknown}}')
        ->toContain('<thead><tr><th>');
});

test('paragraph alignment moves onto its table cell', function () {
    $document = new Document(['title' => 'T']);
    $body = '<table><tbody><tr><td colspan="2"><p style="text-align: center">x</p></td></tr></tbody></table>';

    $html = (fn () => $this->content($document, $body))->call(app(DocumentPdf::class));

    expect($html)->toContain('<td colspan="2" style="text-align: center"><p');
});

test('an invalid accent colour falls back instead of reaching the css', function () {
    $header = DocumentLayout::create([
        'type' => 'header',
        'name' => 'H',
        'accent_color' => 'red; background: url(http://evil.test/x)',
    ]);
    $document = Document::create(['title' => 'T', 'header_id' => $header->id, 'body' => '<p>x</p>']);

    expect(app(DocumentPdf::class)->render($document))->toStartWith('%PDF');
});

test('content larger than the shared sanitizer limit is not truncated', function () {
    $document = new Document(['title' => 'T', 'body' => tableHtml(12000)]);

    expect(strlen($document->body))->toBeGreaterThan(500000);

    $html = (fn () => $this->content($document, $document->body))->call(app(DocumentPdf::class));

    expect($html)->toContain('Row value 12000');
});

test('a long table in Arabic spans several pages', function () {
    $document = Document::create(['title' => 'T', 'language' => 'ar', 'body' => tableHtml(400, 'صف')]);

    $pdf = app(DocumentPdf::class)->render($document);

    expect(Str::startsWith($pdf, '%PDF'))->toBeTrue()
        ->and(preg_match_all('#/Type\s*/Page\b#', $pdf))->toBeGreaterThan(5);
});

test('sample seeder is repeatable, complete and renders every document', function () {
    Storage::fake('local');

    $this->seed(DocumentSampleSeeder::class);

    $final = Document::firstWhere('title', 'Invoice - Blue Harbor Logistics');
    $final->finalize();
    $reference = $final->reference;

    $this->seed(DocumentSampleSeeder::class);

    expect(DocumentLayout::count())->toBe(6)
        ->and(DocumentTemplate::count())->toBe(7)
        ->and(Document::count())->toBe(9)
        ->and(DocumentLayout::where('type', 'header')->pluck('logo')->unique())->toHaveCount(3)
        ->and(Document::pluck('language')->unique()->sort()->values()->all())->toBe(['ar', 'en'])
        ->and($final->refresh()->reference)->toBe($reference)
        ->and($final->isFinal())->toBeTrue();

    foreach (Document::all() as $document) {
        expect($document->missingPlaceholders())->toBe([])
            ->and(app(DocumentPdf::class)->render($document))->toStartWith('%PDF');

        foreach (['logo', 'signature', 'stamp'] as $image) {
            if ($document->{"{$image}_mode"} === 'upload') {
                Storage::disk('local')->assertExists($document->{$image});
            }
        }
    }
});

test('finalizing stores the pdf and numbers documents per year', function () {
    Storage::fake('local');

    $first = Document::create(['title' => 'A', 'body' => '<p>a</p>']);
    $second = Document::create(['title' => 'B', 'body' => '<p>b</p>']);

    $first->finalize();
    $first->refresh();
    $second->finalize();
    $path = $first->pdf_path;
    $first->finalize();

    $year = now()->year;

    expect($first->refresh()->reference)->toBe("DOC-{$year}-0001")
        ->and($first->isFinal())->toBeTrue()
        ->and($first->pdf_path)->toBe($path)
        ->and($second->refresh()->reference)->toBe("DOC-{$year}-0002");

    Storage::disk('local')->assertExists($path);
});
