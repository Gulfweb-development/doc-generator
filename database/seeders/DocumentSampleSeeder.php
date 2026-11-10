<?php

namespace Database\Seeders;

use App\Models\Document;
use App\Models\DocumentLayout;
use App\Models\DocumentTemplate;
use App\Models\PdfDocument;
use GdImage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Demo data with fictional companies. Not called from DatabaseSeeder, run it on purpose:
 * php artisan db:seed --class=DocumentSampleSeeder
 *
 * Safe to re-run: records are matched by name, and finalized documents are left alone.
 */
class DocumentSampleSeeder extends Seeder
{
    private const COMPANIES = [
        'horizon' => [
            'name' => 'Gulf Horizon Trading Co.',
            'arabic' => 'شركة أفق الخليج التجارية',
            'initials' => 'GH',
            'color' => [29, 78, 137],
            'shape' => 'circle',
            'logo_position' => 'left',
            'signatory' => 'Layla Hassan',
            'header_line' => 'Block 3, Commercial District, Kuwait City',
            'header_line_ar' => 'قطعة ٣، المنطقة التجارية، مدينة الكويت',
            'footer' => '<p>info@gulfhorizon.test, +965 0000 0000</p>',
        ],
        'noor' => [
            'name' => 'Al Noor Training Academy',
            'arabic' => 'أكاديمية النور للتدريب',
            'initials' => 'AN',
            'color' => [22, 101, 52],
            'shape' => 'diamond',
            'logo_position' => 'center',
            'signatory' => 'Omar Khalil',
            'header_line' => 'Professional education and certification',
            'header_line_ar' => 'التعليم والشهادات المهنية',
            'footer' => '<p>Licensed training provider, licence no. 0000</p>',
        ],
        'summit' => [
            'name' => 'Summit Legal Consultants',
            'arabic' => 'سميت للاستشارات القانونية',
            'initials' => 'SL',
            'color' => [127, 29, 29],
            'shape' => 'square',
            'logo_position' => 'right',
            'signatory' => 'Nadia Farouk',
            'header_line' => 'Tower 7, Floor 12, Sharq',
            'header_line_ar' => 'برج ٧، الدور ١٢، شرق',
            'footer' => '<p>Confidential. office@summitlegal.test</p>',
        ],
    ];

    /** Letterhead built from images only: banners, page background, signature and stamp. */
    private const IMAGE_COMPANY = [
        'name' => 'Meridian Engineering Services',
        'initials' => 'ME',
        'color' => [15, 76, 92],
        'signatory' => 'Karim Aziz',
    ];

    public function run(): void
    {
        foreach (self::COMPANIES as $key => $company) {
            $this->storeImages($key, $company);
        }

        $layouts = [...$this->seedLayouts(), 'meridian' => $this->seedImageLayout()];

        $templates = [];

        foreach ($this->templates() as $name => $template) {
            $templates[$name] = DocumentTemplate::updateOrCreate(['name' => $name], [
                'language' => $template['language'],
                'layout_id' => $layouts[$template['company']],
                'body' => $template['body'],
                'logo_mode' => 'default',
                ...$this->image($template['company'], 'signature', $template['signature']),
                ...$this->image($template['company'], 'stamp', $template['stamp']),
            ]);
        }

        foreach ($this->documents() as $title => $sample) {
            $document = Document::firstOrNew(['title' => $title]);

            if ($document->isFinal()) {
                continue;
            }

            $document->fill([
                ...$templates[$sample['template']]->only(Document::TEMPLATE_FIELDS),
                ...($sample['overrides'] ?? []),
                'placeholder_values' => $sample['values'],
            ])->save();
        }

        // Template for the current Documents screen: language, body and images.
        $templates['Letter - Meridian (images)'] = DocumentTemplate::updateOrCreate(['name' => 'Letter - Meridian (images)'], [
            'language' => 'en',
            'body' => '<p>Date: </p><p><strong>To:</strong> </p><h3>Subject: </h3><p>Dear ,</p><p></p><p>Yours sincerely,</p>',
            'image_layout' => 'separate',
            ...array_combine(PdfDocument::IMAGES, array_map(fn (string $name) => $this->path('meridian', $name), PdfDocument::IMAGES)),
            'signature_mode' => 'upload',
            'stamp_mode' => 'upload',
        ]);

        foreach ($this->pdfDocuments() as $title => $sample) {
            $document = PdfDocument::firstOrNew(['title' => $title]);

            if (! $document->isFinal()) {
                $document->fill($sample)->save();
            }
        }

        $this->command?->info(count($layouts).' letterheads, '.count($templates).' templates, '.count($this->documents()).' old documents, '.count($this->pdfDocuments()).' documents seeded.');
    }

    /** Samples for the current Documents screen. Images reuse the files stored above. */
    private function pdfDocuments(): array
    {
        $meridian = fn (array $names): array => array_combine($names, array_map(fn (string $name) => $this->path('meridian', $name), $names));

        return [
            'Site inspection confirmation - Crescent Retail' => [
                'language' => 'en',
                'body' => '<p>Date: 10 November 2026</p>'
                    .'<p><strong>To:</strong> Eng. Salma Haddad<br>Crescent Retail Group</p>'
                    .'<h3>Subject: Confirmation of site inspection</h3>'
                    .'<p>Dear Eng. Salma,</p>'
                    .'<p>We confirm that our team will inspect the new warehouse site on 20 November 2026 and share the structural report within five working days.</p>'
                    .'<p>This sample uses every image: header, footer, page background, signature and stamp.</p>'
                    .'<p>Yours sincerely,</p>',
                'image_layout' => 'separate',
                ...$meridian(PdfDocument::IMAGES),
                'signature_mode' => 'upload',
                'stamp_mode' => 'upload',
            ],
            'خطاب تعريف - سارة محمود' => [
                'language' => 'ar',
                'body' => '<h2 style="text-align: center">خطاب تعريف</h2>'
                    .'<p>إلى من يهمه الأمر،</p>'
                    .'<p>نفيدكم بأن السيدة <strong>سارة محمود</strong> تعمل لدينا بوظيفة مهندسة مدنية منذ ١ مارس ٢٠٢٢، وما زالت على رأس عملها حتى تاريخه.</p>'
                    .'<p>وقد أعطي لها هذا الخطاب بناءً على طلبها دون أدنى مسؤولية على الشركة.</p>'
                    .'<p>هذا المثال يستخدم صورة الرأس والتذييل فقط، والتوقيع والختم من الإعدادات العامة.</p>',
                'image_layout' => 'separate',
                ...$meridian(['header_image', 'footer_image']),
                'background_image' => null,
                'signature' => null,
                'stamp' => null,
                'signature_mode' => 'default',
                'stamp_mode' => 'default',
            ],
            'Quotation - Office fit-out (no images)' => [
                'language' => 'en',
                'body' => '<h1>Quotation</h1>'
                    .$this->partiesTable('Prepared for', 'Blue Harbor Logistics', 'Quotation details', 'Date: 10 November 2026<br>Valid until: 10 December 2026')
                    .$this->pricedTable(self::STANDARD_ITEMS)
                    .'<p>This sample has no images of its own, so only the General settings signature and stamp appear.</p>',
                'image_layout' => 'letterhead',
                'header_image' => null,
                'footer_image' => null,
                'background_image' => null,
                'signature' => null,
                'stamp' => null,
                'signature_mode' => 'default',
                'stamp_mode' => 'default',
            ],
        ];
    }

    private function seedLayouts(): array
    {
        $layouts = [];

        foreach (self::COMPANIES as $key => $company) {
            $layouts[$key] = DocumentLayout::updateOrCreate(
                ['name' => $company['name']],
                [
                    'header_content' => "<h3>{$company['name']}</h3><p>{$company['header_line']}</p>",
                    'header_right_content' => "<h3>{$company['arabic']}</h3><p>{$company['header_line_ar']}</p>",
                    'logo' => $this->path($key, 'logo'),
                    'logo_position' => $company['logo_position'],
                    'accent_color' => sprintf('#%02x%02x%02x', ...$company['color']),
                    'footer_content' => $company['footer'],
                ],
            )->id;
        }

        return $layouts;
    }

    private function seedImageLayout(): int
    {
        $company = self::IMAGE_COMPANY;

        $images = [
            'header_image' => $this->drawHeaderBanner($company),
            'footer_image' => $this->drawFooterBanner($company),
            'background_image' => $this->drawBackground($company),
            'signature' => $this->drawSignature($company),
            'stamp' => $this->drawStamp($company),
        ];

        $paths = [];

        foreach ($images as $name => $canvas) {
            ob_start();
            imagepng($canvas);
            Storage::disk('local')->put($paths[$name] = $this->path('meridian', $name), ob_get_clean());
        }

        return DocumentLayout::updateOrCreate(['name' => "{$company['name']} (image letterhead)"], [
            ...$paths,
            'header_content' => null,
            'header_right_content' => null,
            'logo' => null,
            'logo_position' => 'left',
            'accent_color' => sprintf('#%02x%02x%02x', ...$company['color']),
            'footer_content' => null,
        ])->id;
    }

    private function templates(): array
    {
        return [
            'Letter on image letterhead (EN)' => [
                'company' => 'meridian', 'language' => 'en', 'signature' => 'default', 'stamp' => 'default',
                'body' => '<p>Ref: {{reference}}<br>Date: {{date}}</p>'
                    .'<p><strong>To:</strong> {{recipient}}<br>{{recipient_company}}</p>'
                    .'<h3>Subject: {{subject}}</h3>'
                    .'<p>Dear {{recipient}},</p>'
                    .'<p>{{message}}</p>'
                    .'<p>This letter uses an image letterhead: the header, footer, page background, signature and stamp all come from the letterhead record.</p>'
                    .'<p>Yours sincerely,</p>',
            ],
            'Business Letter (EN)' => [
                'company' => 'horizon', 'language' => 'en', 'signature' => true, 'stamp' => false,
                'body' => '<p>Ref: {{reference}}<br>Date: {{date}}</p>'
                    .'<p><strong>To:</strong> {{recipient}}<br>{{recipient_company}}</p>'
                    .'<h3>Subject: {{subject}}</h3>'
                    .'<p>Dear {{recipient}},</p>'
                    .'<p>{{message}}</p>'
                    .'<p>We value our partnership and look forward to working with you. Please contact our office if you need any further information.</p>'
                    .'<p>Yours sincerely,</p>',
            ],
            'Experience Letter (AR)' => [
                'company' => 'horizon', 'language' => 'ar', 'signature' => true, 'stamp' => true,
                'body' => '<h1>شهادة خبرة</h1>'
                    .'<p>المرجع: {{reference}}<br>التاريخ: {{date}}</p>'
                    .'<p>إلى من يهمه الأمر،</p>'
                    .'<p>تشهد الشركة بأن السيد/ة <strong>{{employee}}</strong> قد عمل/ت لدينا بوظيفة <strong>{{position}}</strong> خلال الفترة من {{start_date}} إلى {{end_date}}.</p>'
                    .'<p>وقد كان/ت مثالاً للالتزام وحسن الأداء طوال فترة العمل، وأعطيت هذه الشهادة بناءً على طلبه/ا دون أدنى مسؤولية على الشركة.</p>',
            ],
            'Invoice (EN)' => [
                'company' => 'horizon', 'language' => 'en', 'signature' => false, 'stamp' => true,
                'body' => $this->invoiceBody(self::STANDARD_ITEMS),
            ],
            'Quotation (EN)' => [
                'company' => 'summit', 'language' => 'en', 'signature' => true, 'stamp' => false,
                'body' => '<h1>Quotation</h1>'
                    .$this->partiesTable('Prepared for', '{{client}}', 'Quotation details', 'No. {{reference}}<br>Date: {{date}}<br>Valid until: {{valid_until}}')
                    .'<p>Thank you for your enquiry. We are pleased to quote as follows.</p>'
                    .$this->pricedTable(self::QUOTE_ITEMS)
                    .'<h3>Terms</h3><ul><li><p>50% payable on acceptance, balance on completion.</p></li><li><p>Prices exclude court and government fees.</p></li></ul>',
            ],
            'Service Agreement (EN)' => [
                'company' => 'summit', 'language' => 'en', 'signature' => true, 'stamp' => true,
                'body' => '<h1>Service agreement</h1>'
                    .'<p>This agreement ({{reference}}) is made on {{date}} between <strong>Summit Legal Consultants</strong> and <strong>{{client}}</strong>.</p>'
                    .'<h3>1. Scope of services</h3><p>{{scope}}</p>'
                    .'<h3>2. Fees</h3><p>The client shall pay a monthly fee of {{monthly_fee}}, invoiced at the start of each month.</p>'
                    .'<h3>3. Term</h3><p>This agreement runs for {{term}} and renews only by written consent of both parties.</p>'
                    .'<h3>4. Confidentiality</h3><p>Each party shall keep the other party\'s information confidential during the term and after it ends.</p>'
                    .'<h3>5. Termination</h3><p>Either party may terminate this agreement with thirty days\' written notice.</p>',
            ],
            'Certificate of Completion (EN)' => [
                'company' => 'noor', 'language' => 'en', 'signature' => true, 'stamp' => true,
                'body' => '<h2 style="text-align: center">Certificate of completion</h2>'
                    .'<p style="text-align: center">&nbsp;</p>'
                    .'<p style="text-align: center">This is to certify that</p>'
                    .'<h1 style="text-align: center">{{participant}}</h1>'
                    .'<p style="text-align: center">has successfully completed the course</p>'
                    .'<h2 style="text-align: center">{{course}}</h2>'
                    .'<p style="text-align: center">{{hours}} training hours, completed on {{completion_date}}</p>'
                    .'<p style="text-align: center">&nbsp;</p>'
                    .'<p style="text-align: center">Certificate no. {{reference}}</p>',
            ],
            'Training Certificate (AR)' => [
                'company' => 'noor', 'language' => 'ar', 'signature' => true, 'stamp' => true,
                'body' => '<h2 style="text-align: center">شهادة إتمام دورة تدريبية</h2>'
                    .'<p style="text-align: center">&nbsp;</p>'
                    .'<p style="text-align: center">تشهد الأكاديمية بأن</p>'
                    .'<h1 style="text-align: center">{{participant}}</h1>'
                    .'<p style="text-align: center">قد أتم/ت بنجاح الدورة التدريبية</p>'
                    .'<h2 style="text-align: center">{{course}}</h2>'
                    .'<p style="text-align: center">بواقع {{hours}} ساعة تدريبية، بتاريخ {{completion_date}}</p>'
                    .'<p style="text-align: center">&nbsp;</p>'
                    .'<p style="text-align: center">رقم الشهادة: {{reference}}</p>',
            ],
        ];
    }

    private function documents(): array
    {
        $invoiceValues = fn (string $client, string $due): array => [
            'client' => $client,
            'client_address' => 'Salmiya, Kuwait',
            'due_date' => $due,
        ];

        return [
            'Letter - Site visit confirmation (image letterhead)' => [
                'template' => 'Letter on image letterhead (EN)',
                'values' => [
                    'recipient' => 'Eng. Salma Haddad',
                    'recipient_company' => 'Crescent Retail Group',
                    'subject' => 'Confirmation of site inspection',
                    'message' => 'We confirm that our team will inspect the new warehouse site on 20 November 2026 and share the structural report within five working days.',
                ],
            ],
            'Letter - Partnership renewal' => [
                'template' => 'Business Letter (EN)',
                'values' => [
                    'recipient' => 'Mr. Yousef Rahman',
                    'recipient_company' => 'Blue Harbor Logistics',
                    'subject' => 'Renewal of partnership agreement',
                    'message' => 'We are pleased to confirm the renewal of our partnership for the coming year on the existing terms.',
                ],
            ],
            'Letter - Joint training programme (academy logo)' => [
                'template' => 'Business Letter (EN)',
                'overrides' => ['logo_mode' => 'upload', 'logo' => $this->path('noor', 'logo')],
                'values' => [
                    'recipient' => 'Ms. Huda Salem',
                    'recipient_company' => 'Al Noor Training Academy',
                    'subject' => 'Joint staff training programme',
                    'message' => 'This letter confirms our joint programme with the academy, which is why it carries the academy logo instead of our own.',
                ],
            ],
            'شهادة خبرة - سارة محمود' => [
                'template' => 'Experience Letter (AR)',
                'values' => [
                    'employee' => 'سارة محمود',
                    'position' => 'محاسب أول',
                    'start_date' => '2021-03-01',
                    'end_date' => '2026-06-30',
                ],
            ],
            'Invoice - Blue Harbor Logistics' => [
                'template' => 'Invoice (EN)',
                'values' => $invoiceValues('Blue Harbor Logistics', '2026-12-15'),
            ],
            'Invoice - Annual supply, 150 lines (multi-page)' => [
                'template' => 'Invoice (EN)',
                'overrides' => ['body' => $this->invoiceBody($this->manyItems(150))],
                'values' => $invoiceValues('Crescent Retail Group', '2026-12-31'),
            ],
            'Quotation - Corporate legal retainer' => [
                'template' => 'Quotation (EN)',
                'values' => ['client' => 'Crescent Retail Group', 'valid_until' => '2026-12-31'],
            ],
            'Agreement - Legal services (no logo)' => [
                'template' => 'Service Agreement (EN)',
                'overrides' => ['logo_mode' => 'none'],
                'values' => [
                    'client' => 'Blue Harbor Logistics',
                    'scope' => 'General corporate legal advice, contract review and regulatory support.',
                    'monthly_fee' => 'KWD 750',
                    'term' => 'twelve months',
                ],
            ],
            'Certificate - Project Management, Fatima Noor' => [
                'template' => 'Certificate of Completion (EN)',
                'values' => [
                    'participant' => 'Fatima Noor',
                    'course' => 'Project Management Fundamentals',
                    'hours' => '40',
                    'completion_date' => '2026-10-29',
                ],
            ],
            'شهادة تدريب - أحمد العلي' => [
                'template' => 'Training Certificate (AR)',
                'values' => [
                    'participant' => 'أحمد العلي',
                    'course' => 'مهارات القيادة والإدارة',
                    'hours' => '24',
                    'completion_date' => '2026-10-15',
                ],
            ],
        ];
    }

    private const STANDARD_ITEMS = [
        ['Office chairs', 12, 45.0],
        ['Standing desks', 6, 130.0],
        ['Delivery and installation', 1, 85.5],
    ];

    private const QUOTE_ITEMS = [
        ['Company structure review', 1, 900.0],
        ['Contract templates (per template)', 5, 120.0],
        ['Monthly advisory hours', 10, 65.0],
    ];

    private function manyItems(int $count): array
    {
        $items = [];

        for ($i = 1; $i <= $count; $i++) {
            $items[] = ["Product line {$i}", $i % 9 + 1, 5 + $i * 0.25];
        }

        return $items;
    }

    private function invoiceBody(array $items): string
    {
        return '<h1>Invoice</h1>'
            .$this->partiesTable('Bill to', '{{client}}<br>{{client_address}}', 'Invoice details', 'No. {{reference}}<br>Date: {{date}}<br>Due: {{due_date}}')
            .$this->pricedTable($items)
            .'<p>Please quote the invoice number with your payment.</p>';
    }

    private function partiesTable(string $firstLabel, string $first, string $secondLabel, string $second): string
    {
        return "<table><tbody><tr><th><p>{$firstLabel}</p></th><th><p>{$secondLabel}</p></th></tr>"
            ."<tr><td><p>{$first}</p></td><td><p>{$second}</p></td></tr></tbody></table>";
    }

    private function pricedTable(array $items): string
    {
        $right = fn (string $text): string => '<p style="text-align: right">'.$text.'</p>';
        $money = fn (float $amount): string => number_format($amount, 3);

        $rows = '';
        $subtotal = 0.0;

        foreach ($items as $index => [$description, $quantity, $price]) {
            $amount = $quantity * $price;
            $subtotal += $amount;
            $rows .= '<tr><td><p>'.($index + 1)."</p></td><td><p>{$description}</p></td><td>".$right((string) $quantity)
                .'</td><td>'.$right($money($price)).'</td><td>'.$right($money($amount)).'</td></tr>';
        }

        $tax = $subtotal * 0.05;
        $line = fn (string $label, float $value): string => '<tr><td colspan="4">'.$right($label).'</td><td>'.$right($money($value)).'</td></tr>';
        $grandTotal = fn (string $label, float $value): string => '<tr><td colspan="4"><h3 style="text-align: right">'.$label.'</h3></td><td><h3 style="text-align: right">'.$money($value).'</h3></td></tr>';

        return '<table><tbody><tr><th><p>#</p></th><th><p>Description</p></th><th>'.$right('Qty').'</th><th>'.$right('Unit price (KWD)').'</th><th>'.$right('Amount (KWD)').'</th></tr>'
            .$rows
            .$line('Subtotal', $subtotal)
            .$line('Tax (5%)', $tax)
            .$grandTotal('Total', $subtotal + $tax)
            .'</tbody></table>';
    }

    /** @param  bool|'default'  $enabled  'default' takes the image from the letterhead or General settings. */
    private function image(string $company, string $name, bool|string $enabled): array
    {
        if ($enabled === 'default') {
            return ["{$name}_mode" => 'default', $name => null];
        }

        return [
            "{$name}_mode" => $enabled ? 'upload' : 'none',
            $name => $enabled ? $this->path($company, $name) : null,
        ];
    }

    private function path(string $company, string $name): string
    {
        return "documents/samples/{$company}-{$name}.png";
    }

    private function storeImages(string $key, array $company): void
    {
        foreach (['logo', 'signature', 'stamp'] as $name) {
            $canvas = match ($name) {
                'logo' => $this->drawLogo($company),
                'signature' => $this->drawSignature($company),
                'stamp' => $this->drawStamp($company),
            };

            ob_start();
            imagepng($canvas);
            Storage::disk('local')->put($this->path($key, $name), ob_get_clean());
        }
    }

    private function drawLogo(array $company): GdImage
    {
        $canvas = $this->canvas(240, 240);
        $color = imagecolorallocate($canvas, ...$company['color']);

        match ($company['shape']) {
            'circle' => imagefilledellipse($canvas, 120, 120, 228, 228, $color),
            'diamond' => imagefilledpolygon($canvas, [120, 4, 236, 120, 120, 236, 4, 120], $color),
            'square' => imagefilledrectangle($canvas, 8, 8, 232, 232, $color),
        };

        $this->text($canvas, $company['initials'], 74, 0, 120, 120, imagecolorallocate($canvas, 255, 255, 255), 'DejaVuSans-Bold');

        return $canvas;
    }

    private function drawSignature(array $company): GdImage
    {
        $canvas = $this->canvas(480, 150);
        $ink = imagecolorallocate($canvas, 20, 40, 120);

        $this->text($canvas, $company['signatory'], 34, 6, 240, 70, $ink, 'DejaVuSans-Oblique');

        imagesetthickness($canvas, 3);

        for ($x = 60; $x < 420; $x++) {
            imageline($canvas, $x, (int) (120 + 10 * sin($x / 18)), $x + 1, (int) (120 + 10 * sin(($x + 1) / 18)), $ink);
        }

        return $canvas;
    }

    private function drawStamp(array $company): GdImage
    {
        $canvas = $this->canvas(300, 300);
        $ink = imagecolorallocate($canvas, ...$company['color']);

        imagesetthickness($canvas, 6);
        imagearc($canvas, 150, 150, 284, 284, 0, 360, $ink);
        imagesetthickness($canvas, 3);
        imagearc($canvas, 150, 150, 250, 250, 0, 360, $ink);

        $this->text($canvas, $company['initials'], 44, 0, 150, 85, $ink, 'DejaVuSans-Bold');
        $this->text($canvas, 'APPROVED', 26, 0, 150, 155, $ink, 'DejaVuSans-Bold');
        $this->text($canvas, 'SAMPLE', 20, 0, 150, 215, $ink, 'DejaVuSans-Bold');

        return $canvas;
    }

    /** Content width is 162 mm, so 1620 px wide keeps 10 px per mm. */
    private function drawHeaderBanner(array $company): GdImage
    {
        $canvas = $this->canvas(1620, 230);
        $accent = imagecolorallocate($canvas, ...$company['color']);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        $gold = imagecolorallocate($canvas, 201, 162, 39);

        imagefilledrectangle($canvas, 0, 0, 1619, 200, $accent);
        imagefilledrectangle($canvas, 0, 208, 1619, 216, $gold);
        imagefilledellipse($canvas, 110, 100, 150, 150, $white);
        $this->text($canvas, $company['initials'], 44, 0, 110, 100, $accent, 'DejaVuSans-Bold');
        imagettftext($canvas, 44, 0, 220, 105, $white, base_path('vendor/mpdf/mpdf/ttfonts/DejaVuSans-Bold.ttf'), $company['name']);
        imagettftext($canvas, 22, 0, 222, 155, $white, base_path('vendor/mpdf/mpdf/ttfonts/DejaVuSans.ttf'), 'Structural design, site supervision, inspections');

        return $canvas;
    }

    private function drawFooterBanner(array $company): GdImage
    {
        $canvas = $this->canvas(1620, 80);
        $accent = imagecolorallocate($canvas, ...$company['color']);

        imagefilledrectangle($canvas, 0, 0, 1619, 4, $accent);
        $this->text($canvas, 'Meridian Engineering Services   |   Industrial Area, Shuwaikh   |   projects@meridian.test   |   +965 0000 0000', 20, 0, 810, 45, $accent, 'DejaVuSans');

        return $canvas;
    }

    /** A4 at 6 px per mm. Kept faint so body text stays readable on top. */
    private function drawBackground(array $company): GdImage
    {
        $canvas = imagecreatetruecolor(1260, 1782);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        $tint = imagecolorallocate($canvas, 236, 243, 245);

        imagefilledpolygon($canvas, [1260, 1180, 1260, 1782, 660, 1782], $tint);
        imagefilledpolygon($canvas, [0, 0, 380, 0, 0, 380], $tint);
        $this->text($canvas, $company['initials'], 300, 30, 630, 900, imagecolorallocate($canvas, 244, 248, 249), 'DejaVuSans-Bold');

        return $canvas;
    }

    private function canvas(int $width, int $height): GdImage
    {
        $canvas = imagecreatetruecolor($width, $height);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));

        return $canvas;
    }

    private function text(GdImage $canvas, string $text, int $size, int $angle, int $centerX, int $centerY, int $color, string $font): void
    {
        $file = base_path("vendor/mpdf/mpdf/ttfonts/{$font}.ttf");
        $box = imagettfbbox($size, 0, $file, $text);
        $width = $box[2] - $box[0];
        $height = $box[1] - $box[7];

        imagettftext($canvas, $size, $angle, (int) ($centerX - $width / 2), (int) ($centerY + $height / 2), $color, $file, $text);
    }
}
