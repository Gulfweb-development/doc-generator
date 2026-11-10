<?php

namespace App\Services;

use App\Models\Document;
use App\Models\PdfDocument;
use Illuminate\Support\Facades\Storage;
use Mpdf\HTMLParserMode;
use Mpdf\Mpdf;
use RuntimeException;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

class DocumentPdf
{
    /**
     * Where content goes on a full-page letterhead background, in millimetres on A4.
     * Measured on the Kuwait Bar Association letterhead: logo and date row on top, address footer below.
     * shortcut: one layout for every background letterhead; make it per template if letterheads differ.
     */
    private const LETTERHEAD = [
        'margins' => ['top' => 88, 'bottom' => 26, 'left' => 18, 'right' => 18],
        'date' => [143, 75],
        'reference' => [24, 75],
    ];

    public function render(Document $document): string
    {
        $layout = $document->layout;

        // Goes straight into CSS, so only a plain hex colour may pass.
        $accent = preg_match('/^#[0-9a-f]{6}$/i', (string) $layout?->accent_color) ? $layout->accent_color : '#1f3a5f';

        $images = [];

        foreach (['header_image', 'footer_image', 'background_image'] as $name) {
            $images[$name] = $this->bytes('local', $layout?->{$name});
        }

        foreach (['logo', 'signature', 'stamp'] as $name) {
            $images[$name] = $this->image($document, $name);
        }

        return $this->build(
            language: $document->language,
            final: $document->isFinal(),
            accent: $accent,
            images: $images,
            body: $this->content($document, $document->body),
            header: $this->content($document, $layout?->header_content),
            headerRight: $this->content($document, $layout?->header_right_content),
            footer: $this->content($document, $layout?->footer_content),
            logoPosition: $layout?->logo_position ?? 'left',
            pageNumbers: true,
        );
    }

    /** Images only, no letterhead text, logo, edge band or page numbers. */
    public function renderPdfDocument(PdfDocument $document): string
    {
        $images = [];

        foreach (PdfDocument::IMAGES as $name) {
            $images[$name] = $this->pdfDocumentImage($document, $name);
        }

        // A full letterhead is one page image with its own header and footer drawn on it, so stray banners are ignored.
        $letterhead = $document->usesLetterhead() && $images['background_image'] !== null;

        if ($document->usesLetterhead()) {
            $images['header_image'] = $images['footer_image'] = null;
        }

        return $this->build(
            language: $document->language,
            final: $document->isFinal(),
            accent: null,
            images: $images,
            body: $this->content(null, $document->body),
            header: '',
            headerRight: '',
            footer: '',
            logoPosition: 'left',
            pageNumbers: false,
            margins: $letterhead ? self::LETTERHEAD['margins'] : [],
            letterheadSpots: $letterhead ? $this->letterheadSpots($document) : [],
            letterhead: $letterhead,
            stackedSignoff: true,
        );
    }

    /**
     * Date and reference beside the labels the letterhead prints, repeated on every page.
     *
     * @return list<array{float, float, string}> x and y in millimetres from the page edges, and HTML
     */
    private function letterheadSpots(PdfDocument $document): array
    {
        $arabic = $document->language === 'ar';
        $digits = fn (string $text): string => $arabic ? strtr($text, ['0' => '٠', '1' => '١', '2' => '٢', '3' => '٣', '4' => '٤', '5' => '٥', '6' => '٦', '7' => '٧', '8' => '٨', '9' => '٩']) : $text;
        $date = ($document->document_date ?? $document->finalized_at ?? now())->locale($arabic ? 'ar' : 'en')->translatedFormat('d F Y');

        // The typed reference wins; otherwise DOC-2026-0012 prints as 2026 / 00012, the numbering style letterheads use.
        $reference = match (true) {
            filled($document->reference_number) => trim($document->reference_number),
            (bool) preg_match('/^DOC-(\d{4})-(\d+)$/', (string) $document->reference, $match) => sprintf('%s / %05d', $match[1], $match[2]),
            default => (string) $document->reference,
        };

        $spots = [];

        foreach ([[self::LETTERHEAD['date'], $date], [self::LETTERHEAD['reference'], $reference]] as [[$x, $y], $text]) {
            if ($text !== '') {
                // Headers skip mPDF's automatic font switch, so Arabic text names its font.
                $spots[] = [(float) $x, (float) $y, sprintf(
                    '<div dir="ltr" style="text-align: left; font-family: %s; font-weight: bold; font-size: 11pt; color: #1f3a5f;">%s</div>',
                    $arabic ? 'xbriyaz' : 'freesans',
                    e($digits($text)),
                )];
            }
        }

        return $spots;
    }

    /**
     * @param  array<string, ?string>  $images  PNG or JPEG bytes by name
     * @param  ?string  $accent  Validated hex colour, or null for no edge band
     */
    private function build(
        // Null on a model created without it, before the database default is read back.
        ?string $language,
        bool $final,
        ?string $accent,
        array $images,
        string $body,
        string $header,
        string $headerRight,
        string $footer,
        string $logoPosition,
        bool $pageNumbers,
        // Millimetres by side; null keeps the automatic value.
        array $margins = [],
        array $letterheadSpots = [],
        bool $letterhead = false,
        // One centred column with no "Authorized signature" line, for documents that may carry the name in the signature image.
        bool $stackedSignoff = false,
    ): string {
        // Measured: about 4 ms and 50 KB per table row, so 20,000 rows need 77 s and 1 GB.
        // shortcut: renders in the web request. Move to a queued job if documents outgrow the web server timeout.
        set_time_limit(300);
        ini_set('memory_limit', '1536M');

        // Banners print edge to edge, so header and footer text start below and above them.
        $headerMm = $this->bannerHeightMm($images['header_image'] ?? null);
        $footerMm = $this->bannerHeightMm($images['footer_image'] ?? null);

        $mpdf = new Mpdf([
            'tempDir' => storage_path('framework/cache/mpdf'),
            'format' => 'A4',
            'default_font' => 'freeserif',
            'watermark_font' => 'freesans',
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'margin_left' => $margins['left'] ?? 24,
            'margin_right' => $margins['right'] ?? 24,
            'margin_top' => $margins['top'] ?? ($headerMm > 0 ? $headerMm + 8 : 16),
            'margin_bottom' => $margins['bottom'] ?? ($footerMm > 0 ? $footerMm + 8 : 16),
            'margin_header' => $headerMm > 0 ? $headerMm + 4 : 12,
            'margin_footer' => $footerMm > 0 ? $footerMm + 3 : 10,
            // Grow page margins to fit whatever height the header and footer turn out to be.
            'setAutoTopMargin' => 'stretch',
            'setAutoBottomMargin' => 'stretch',
            'autoMarginPadding' => 6,
            // Do not enable packTableData: it saves memory but crashes on merged table cells.
        ]);

        $direction = $language === 'ar' ? 'rtl' : 'ltr';

        $mpdf->SetDirectionality($direction);

        // Images only reach mPDF as bytes, so document content can never make it read a path or URL.
        $mpdf->imageVars = array_filter($images);

        // mPDF keeps headers inside the side margins and ignores fixed positioning there, so
        // page-wide images go through the watermark image layer, which holds one image: the
        // background and banners merged into one page, or else the edge band (left in English, right in Arabic).
        if (isset($mpdf->imageVars['background_image']) && ! isset($mpdf->imageVars['header_image']) && ! isset($mpdf->imageVars['footer_image'])) {
            // Nothing to merge, so the background keeps its full resolution.
            $mpdf->SetWatermarkImage('var:background_image', 1, [210, 297], [0, 0]);
        } elseif ($images['background_image'] ?? $images['header_image'] ?? $images['footer_image'] ?? null) {
            $mpdf->imageVars['page'] = $this->pageLayer($images, $accent, $direction);
            $mpdf->SetWatermarkImage('var:page', 1, [210, 297], [0, 0]);
        } elseif ($accent !== null) {
            $mpdf->imageVars['band'] = $this->solidPixel($accent);
            $mpdf->SetWatermarkImage('var:band', 1, [3.5, 297], [$direction === 'rtl' ? 206.5 : 0, 0]);
        }
        $mpdf->showWatermarkImage = true;
        $mpdf->watermarkImgBehind = true;

        if (! $final) {
            $mpdf->SetWatermarkText('DRAFT', 0.08);
            $mpdf->showWatermarkText = true;
        }

        $has = fn (string $name): bool => isset($mpdf->imageVars[$name]);
        $accent ??= '#1f3a5f';

        $body = view('pdf.body', [
            'body' => $body,
            'signature' => $has('signature'),
            'stamp' => $has('stamp'),
            'signatureLabel' => trans('Authorized signature', [], $language),
            'stackedSignoff' => $stackedSignoff,
        ])->render();

        // mPDF refuses HTML longer than this limit, and the default is 1 MB.
        ini_set('pcre.backtrack_limit', (string) max(1_000_000, strlen($body) * 2));

        $mpdf->WriteHTML(view('pdf.styles', compact('accent', 'direction', 'letterhead'))->render(), HTMLParserMode::HEADER_CSS);
        $mpdf->SetHTMLHeader($letterheadSpots !== [] ? $this->letterheadRow($letterheadSpots) : view('pdf.header', [
            'content' => $header,
            'rightContent' => $headerRight,
            'logo' => $has('logo'),
            'position' => $logoPosition,
            'direction' => $direction,
        ])->render());
        $mpdf->SetHTMLFooter(view('pdf.footer', [
            'content' => $footer,
            'pageNumber' => $pageNumbers ? trans('Page :x of :y', ['x' => '{PAGENO}', 'y' => '{nbpg}'], $language) : '',
            'direction' => $direction,
        ])->render());
        $mpdf->WriteHTML($body, HTMLParserMode::HTML_BODY);

        return $mpdf->Output('', 'S');
    }

    /**
     * Places the spots on one row through the page header, so every page gets them.
     * mPDF ignores fixed positioning in headers, so the row is pushed down and across with spacers.
     *
     * @param  list<array{float, float, string}>  $spots  all on the same row
     */
    private function letterheadRow(array $spots): string
    {
        // The header area starts 12 mm from the top (margin_header without banners) and spans the side margins.
        $left = self::LETTERHEAD['margins']['left'];
        $right = 210 - self::LETTERHEAD['margins']['right'];
        usort($spots, fn (array $a, array $b): int => $a[0] <=> $b[0]);

        $cells = '';
        $cursor = $left;

        foreach ($spots as [$x, , $html]) {
            $width = min(60, $right - $x);
            $cells .= sprintf('<td style="width: %.1fmm;"></td><td style="width: %.1fmm; white-space: nowrap;">%s</td>', $x - $cursor, $width, $html);
            $cursor = $x + $width;
        }

        // Widths add up to the full content width, so mPDF keeps every column where it was placed.
        $cells .= sprintf('<td style="width: %.1fmm;"></td>', max(0, $right - $cursor));

        return sprintf(
            '<div style="padding-top: %.1fmm;"><table class="plain" dir="ltr" style="width: 100%%; table-layout: fixed;"><tr>%s</tr></table></div>',
            min(array_column($spots, 1)) - 12,
            $cells,
        );
    }

    private function bannerHeightMm(?string $image): float
    {
        $size = $image === null ? false : getimagesizefromstring($image);

        return $size ? round(210 * $size[1] / $size[0], 2) : 0.0;
    }

    /** One A4 page at 150 dpi: background stretched to fill, banners full width at top and bottom. */
    private function pageLayer(array $images, ?string $accent, string $direction): string
    {
        [$width, $height] = [1240, 1754];
        $page = imagecreatetruecolor($width, $height);
        imagefill($page, 0, 0, imagecolorallocate($page, 255, 255, 255));

        $paste = function (?string $bytes, string $place) use ($page, $width, $height): void {
            $image = $bytes === null ? false : imagecreatefromstring($bytes);

            if (! $image) {
                return;
            }

            $h = $place === 'fill' ? $height : (int) round($width * imagesy($image) / imagesx($image));
            $y = $place === 'bottom' ? $height - $h : 0;
            imagecopyresampled($page, $image, 0, $y, 0, 0, $width, $h, imagesx($image), imagesy($image));
        };

        $paste($images['background_image'] ?? null, 'fill');

        // The edge band shows only without a background, like before, and sits under the banners.
        if ($accent !== null && ! isset($images['background_image'])) {
            $x = $direction === 'rtl' ? $width - 21 : 0;
            imagefilledrectangle($page, $x, 0, $x + 20, $height, imagecolorallocate($page, ...sscanf($accent, '#%02x%02x%02x')));
        }

        $paste($images['header_image'] ?? null, 'top');
        $paste($images['footer_image'] ?? null, 'bottom');

        ob_start();
        imagepng($page);

        return ob_get_clean();
    }

    private function solidPixel(string $hex): string
    {
        $pixel = imagecreatetruecolor(1, 1);
        imagefill($pixel, 0, 0, imagecolorallocate($pixel, ...sscanf($hex, '#%02x%02x%02x')));

        ob_start();
        imagepng($pixel);

        return ob_get_clean();
    }

    /** Without a Document there are no placeholders, so {{...}} prints as typed. */
    private function content(?Document $document, ?string $html): string
    {
        if (blank($html)) {
            return '';
        }

        // Filament's shared sanitizer truncates at 500 KB, which would silently cut large documents.
        $config = app(HtmlSanitizerConfig::class)->withMaxInputLength(-1)->dropElement('img');
        $html = (new HtmlSanitizer($config))->sanitize($html);

        // The sanitizer keeps style attributes, and mPDF would fetch any url() inside them.
        $html = preg_replace('/\sstyle="[^"]*url\s*\([^"]*"/i', '', $html);

        // The editor keeps header rows in tbody, and mPDF only repeats thead on each new page.
        $html = preg_replace(
            '#<tbody>\s*(<tr[^>]*>\s*<th\b(?:(?!<td\b|</tr>).)*+</tr>)#si',
            '<thead>$1</thead><tbody>',
            $html,
        );

        // mPDF ignores block alignment inside table cells, so the cell has to carry it.
        $html = preg_replace(
            '#<(td|th)\b((?:(?!style=)[^>])*)>(\s*<(?:p|h[1-6]) style="(text-align:[^"]*)")#i',
            '<$1$2 style="$4">$3',
            $html,
        );

        if ($document === null) {
            return $html;
        }

        return preg_replace_callback(Document::PLACEHOLDER_PATTERN, function (array $match) use ($document) {
            return e($this->placeholderValue($document, $match[1]) ?? $match[0]);
        }, $html) ?? throw new RuntimeException('Could not process placeholders');
    }

    private function placeholderValue(Document $document, string $key): ?string
    {
        return match ($key) {
            'reference' => $document->reference ?? 'DRAFT',
            'date' => ($document->finalized_at ?? now())->format('Y-m-d'),
            default => filled($document->placeholder_values[$key] ?? null) ? $document->placeholder_values[$key] : null,
        };
    }

    private function image(Document $document, string $name): ?string
    {
        [$disk, $path] = match ($document->{"{$name}_mode"}) {
            'none' => [null, null],
            'upload' => ['local', $document->{$name}],
            default => $this->defaultImage($document, $name),
        };

        return $this->bytes($disk, $path);
    }

    private function pdfDocumentImage(PdfDocument $document, string $name): ?string
    {
        // Signature and stamp pick their source by mode; the banners and background are uploads only.
        $path = match (in_array($name, ['signature', 'stamp'], true) ? $document->{"{$name}_mode"} : 'upload') {
            'none' => null,
            'upload' => $document->{$name},
            default => setting("general.{$name}"),
        };

        return $this->bytes('local', $path);
    }

    private function bytes(?string $disk, ?string $path): ?string
    {
        if (blank($path) || ! Storage::disk($disk)->exists($path)) {
            return null;
        }

        // shortcut: only PNG and JPEG, because SVG can reference external files. Rasterize SVG logos if they are needed.
        return in_array(Storage::disk($disk)->mimeType($path), ['image/png', 'image/jpeg'], true)
            ? Storage::disk($disk)->get($path)
            : null;
    }

    private function defaultImage(Document $document, string $name): array
    {
        // Letterhead first, then General settings.
        if (filled($document->layout?->{$name})) {
            return ['local', $document->layout->{$name}];
        }

        // A header banner usually carries the brand already, so the site logo is not added beside it.
        if ($name === 'logo' && filled($document->layout?->header_image)) {
            return [null, null];
        }

        // The site logo is public because the panel shows it. Signature and stamp stay private.
        return [$name === 'logo' ? 'public' : 'local', setting("general.{$name}")];
    }
}
