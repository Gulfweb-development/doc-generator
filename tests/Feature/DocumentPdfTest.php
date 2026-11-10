<?php

use App\Models\Document;
use App\Models\DocumentLayout;
use App\Models\DocumentTemplate;
use App\Models\PdfDocument;
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
    $letterhead = DocumentLayout::create(['name' => 'H', 'header_content' => '<p>{{ company }}</p>', 'footer_content' => '<p>{{ phone }}</p>']);

    $document = Document::create([
        'title' => 'T',
        'layout_id' => $letterhead->id,
        'body' => '<p>{{client}} {{اسم}} {{reference}} {{date}}</p>',
    ]);

    expect($document->placeholder_values)->toBe(['client' => '', 'اسم' => '', 'company' => '', 'phone' => '']);

    $document->update(['placeholder_values' => ['client' => 'ACME', 'اسم' => 'x', 'company' => 'y', 'phone' => 'z']]);
    $document->update(['body' => '<p>{{client}}</p>']);

    expect($document->placeholder_values)->toBe(['client' => 'ACME', 'company' => 'y', 'phone' => 'z']);
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

test('header columns follow the logo position and empty right content changes nothing', function (string $position, array $order) {
    $html = view('pdf.header', [
        'content' => 'LEFT',
        'rightContent' => 'RIGHT',
        'logo' => true,
        'position' => $position,
        'direction' => 'ltr',
    ])->render();

    $positions = collect($order)->map(fn (string $part) => strpos($html, $part));

    expect($positions->all())->toBe($positions->sort()->values()->all())
        ->and($positions)->not->toContain(false);

    $withoutRight = view('pdf.header', [
        'content' => 'LEFT',
        'rightContent' => '',
        'logo' => true,
        'position' => $position,
        'direction' => 'ltr',
    ])->render();

    expect($withoutRight)->not->toContain('text-align: right;">RIGHT');
})->with([
    'logo left' => ['left', ['var:logo', 'LEFT', 'RIGHT']],
    'logo center' => ['center', ['LEFT', 'var:logo', 'RIGHT']],
    'logo right' => ['right', ['LEFT', 'RIGHT', 'var:logo']],
]);

test('header and footer images reach the page edges', function () {
    $solid = function (int $width, int $height, array $rgb): string {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, ...$rgb));
        ob_start();
        imagepng($image);

        return ob_get_clean();
    };

    $pdf = app(DocumentPdf::class);
    $images = ['header_image' => $solid(420, 42, [255, 0, 0]), 'footer_image' => $solid(420, 21, [0, 0, 255])];
    $page = imagecreatefromstring((fn () => $this->pageLayer($images, null, 'ltr'))->call($pdf));
    $color = fn (int $x, int $y) => imagecolorsforindex($page, imagecolorat($page, $x, $y));

    // 1240 px wide page: the header is 124 px tall, the footer 62 px.
    expect($color(0, 0))->toMatchArray(['red' => 255, 'blue' => 0])
        ->and($color(1239, 120))->toMatchArray(['red' => 255, 'blue' => 0])
        ->and($color(600, 130))->toMatchArray(['red' => 255, 'green' => 255, 'blue' => 255])
        ->and($color(0, 1753))->toMatchArray(['red' => 0, 'blue' => 255])
        ->and((fn () => $this->bannerHeightMm($images['header_image']))->call($pdf))->toBe(21.0);

    $bannerOnly = view('pdf.header', [
        'content' => '', 'rightContent' => '', 'logo' => false, 'position' => 'left', 'direction' => 'ltr',
    ])->render();

    expect($bannerOnly)->not->toContain('letterhead-rule');
});

test('with a header banner only the letterhead own logo is used', function () {
    $pdf = app(DocumentPdf::class);
    $source = fn (Document $document) => (fn () => $this->defaultImage($document, 'logo'))->call($pdf);

    $letterhead = DocumentLayout::create(['name' => 'B', 'header_image' => 'banner.png']);
    $document = Document::create(['title' => 'T', 'layout_id' => $letterhead->id, 'body' => '<p>x</p>']);

    expect($source($document->load('layout')))->toBe([null, null]);

    $letterhead->update(['logo' => 'own-logo.png']);

    expect($source($document->load('layout')))->toBe(['local', 'own-logo.png']);
});

test('right header content is sanitized and fills placeholders', function () {
    $letterhead = DocumentLayout::create([
        'name' => 'H',
        'header_right_content' => '<p>{{ branch }}</p><script>bad()</script><img src="file:///etc/passwd">',
    ]);
    $document = Document::create([
        'title' => 'T',
        'layout_id' => $letterhead->id,
        'body' => '<p>x</p>',
        'placeholder_values' => ['branch' => 'Sharq'],
    ]);

    expect($document->placeholder_values)->toBe(['branch' => 'Sharq']);

    $html = (fn () => $this->content($document, $letterhead->header_right_content))->call(app(DocumentPdf::class));

    expect($html)->toContain('Sharq')->not->toContain('<script')->not->toContain('<img')
        ->and(app(DocumentPdf::class)->render($document))->toStartWith('%PDF');
});

test('an invalid accent colour falls back instead of reaching the css', function () {
    $letterhead = DocumentLayout::create([
        'name' => 'H',
        'accent_color' => 'red; background: url(http://evil.test/x)',
    ]);
    $document = Document::create(['title' => 'T', 'layout_id' => $letterhead->id, 'body' => '<p>x</p>']);

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

    $imageLetterhead = DocumentLayout::whereNotNull('header_image')->sole();

    expect(DocumentLayout::count())->toBe(4)
        ->and(DocumentTemplate::count())->toBe(8)
        ->and(Document::count())->toBe(10)
        ->and(DocumentLayout::whereNotNull('logo')->pluck('logo')->unique())->toHaveCount(3)
        ->and(Document::where('layout_id', $imageLetterhead->id)->where('signature_mode', 'default')->where('stamp_mode', 'default')->exists())->toBeTrue()
        ->and(Document::pluck('language')->unique()->sort()->values()->all())->toBe(['ar', 'en'])
        ->and($final->refresh()->reference)->toBe($reference)
        ->and($final->isFinal())->toBeTrue();

    foreach (['header_image', 'footer_image', 'background_image', 'signature', 'stamp'] as $image) {
        Storage::disk('local')->assertExists($imageLetterhead->{$image});
    }

    expect(PdfDocument::count())->toBe(3)
        ->and(PdfDocument::pluck('language')->unique()->sort()->values()->all())->toBe(['ar', 'en']);

    foreach (PdfDocument::all() as $document) {
        foreach (PdfDocument::IMAGES as $image) {
            if ($document->{$image} !== null) {
                Storage::disk('local')->assertExists($document->{$image});
            }
        }

        expect(app(DocumentPdf::class)->renderPdfDocument($document))->toStartWith('%PDF');
    }

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
