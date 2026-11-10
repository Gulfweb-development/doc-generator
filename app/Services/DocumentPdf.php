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
        );
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
            'margin_left' => 24,
            'margin_right' => 24,
            'margin_top' => $headerMm > 0 ? $headerMm + 8 : 16,
            'margin_bottom' => $footerMm > 0 ? $footerMm + 8 : 16,
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
        if ($images['background_image'] ?? $images['header_image'] ?? $images['footer_image'] ?? null) {
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
        ])->render();

        // mPDF refuses HTML longer than this limit, and the default is 1 MB.
        ini_set('pcre.backtrack_limit', (string) max(1_000_000, strlen($body) * 2));

        $mpdf->WriteHTML(view('pdf.styles', compact('accent', 'direction'))->render(), HTMLParserMode::HEADER_CSS);
        $mpdf->SetHTMLHeader(view('pdf.header', [
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
        // Signature and stamp fall back to General settings; the banners and background do not.
        $path = $document->{$name} ?? (in_array($name, ['signature', 'stamp'], true) ? setting("general.{$name}") : null);

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
