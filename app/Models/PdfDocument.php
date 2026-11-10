<?php

namespace App\Models;

use App\Services\DocumentPdf;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/** A document that carries its own header, footer, background, signature and stamp images. */
class PdfDocument extends Model
{
    public const IMAGES = ['header_image', 'footer_image', 'background_image', 'signature', 'stamp'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['finalized_at' => 'datetime'];
    }

    public function isFinal(): bool
    {
        return $this->status === 'final';
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
            // Own folder, because the hidden older documents use the same numbering in documents/.
            $this->pdf_path = "pdf-documents/{$this->reference}.pdf";

            // Rendered inside the transaction because the PDF prints the reference it reserves.
            if (! Storage::disk('local')->put($this->pdf_path, app(DocumentPdf::class)->renderPdfDocument($this))) {
                throw new RuntimeException("Could not store {$this->pdf_path}");
            }

            $this->save();
        }, 3);
    }

    protected static function booted(): void
    {
        // The stored PDF has no other owner, so it goes with the record.
        static::deleted(function (self $document) {
            if ($document->pdf_path) {
                Storage::disk('local')->delete($document->pdf_path);
            }
        });
    }
}
