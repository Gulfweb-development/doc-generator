<?php

namespace App\Models;

use App\Services\DocumentPdf;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class Document extends Model
{
    public const PLACEHOLDER_PATTERN = '/\{\{\s*([\p{L}\p{N}_-]+)\s*\}\}/u';

    /** Filled in by the system, so never asked for on the form. */
    public const BUILT_IN_PLACEHOLDERS = ['reference', 'date'];

    /** Copied from a template once. Later template edits do not reach existing documents. */
    public const TEMPLATE_FIELDS = [
        'language', 'header_id', 'footer_id', 'body',
        'logo_mode', 'logo', 'signature_mode', 'signature', 'stamp_mode', 'stamp',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'placeholder_values' => 'array',
            'finalized_at' => 'datetime',
        ];
    }

    public function header(): BelongsTo
    {
        return $this->belongsTo(DocumentLayout::class, 'header_id');
    }

    public function footer(): BelongsTo
    {
        return $this->belongsTo(DocumentLayout::class, 'footer_id');
    }

    public function isFinal(): bool
    {
        return $this->status === 'final';
    }

    public function missingPlaceholders(): array
    {
        return array_keys(array_filter($this->placeholder_values ?? [], 'blank'));
    }

    public function finalize(): void
    {
        DB::transaction(function () {
            if (static::whereKey($this->id)->lockForUpdate()->value('status') === 'final') {
                return;
            }

            $prefix = 'DOC-'.now()->year.'-';
            $last = static::where('reference', 'like', "{$prefix}%")
                ->lockForUpdate()
                ->orderByRaw('LENGTH(reference) DESC, reference DESC')
                ->value('reference');

            $this->reference = $prefix.str_pad((string) ((int) Str::after((string) $last, $prefix) + 1), 4, '0', STR_PAD_LEFT);
            $this->status = 'final';
            $this->finalized_at = now();
            $this->pdf_path = "documents/{$this->reference}.pdf";

            // Rendered inside the transaction because the PDF prints the reference it reserves.
            if (! Storage::disk('local')->put($this->pdf_path, app(DocumentPdf::class)->render($this))) {
                throw new RuntimeException("Could not store {$this->pdf_path}");
            }

            $this->save();
        }, 3);
    }

    protected static function booted(): void
    {
        static::saving(function (self $document) {
            $document->syncPlaceholders();
        });

        // Issued documents are permanent records, whichever screen or action asks.
        static::deleting(fn (self $document): bool => ! $document->isFinal());
    }

    private function syncPlaceholders(): void
    {
        $text = implode(' ', [
            $this->body,
            DocumentLayout::whereKey(array_filter([$this->header_id, $this->footer_id]))->pluck('content')->implode(' '),
        ]);

        preg_match_all(self::PLACEHOLDER_PATTERN, $text, $matches);

        $keys = array_diff(array_unique($matches[1]), self::BUILT_IN_PLACEHOLDERS);

        $this->placeholder_values = array_merge(
            array_fill_keys($keys, ''),
            array_intersect_key($this->placeholder_values ?? [], array_flip($keys)),
        );
    }
}
