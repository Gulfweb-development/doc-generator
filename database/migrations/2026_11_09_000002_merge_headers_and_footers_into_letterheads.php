<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const OWNERS = ['documents', 'document_templates'];

    public function up(): void
    {
        Schema::table('document_layouts', function (Blueprint $table) {
            $table->renameColumn('content', 'header_content');
        });

        Schema::table('document_layouts', function (Blueprint $table) {
            $table->longText('footer_content')->nullable()->after('header_content');
        });

        foreach (self::OWNERS as $owner) {
            Schema::table($owner, function (Blueprint $table) {
                $table->foreignId('layout_id')->nullable()->after('language')->constrained('document_layouts')->nullOnDelete();
            });
        }

        $this->assignLetterheads();

        $usedFooters = collect(self::OWNERS)->flatMap(fn (string $owner) => DB::table($owner)->whereNotNull('footer_id')->pluck('footer_id'));

        foreach (self::OWNERS as $owner) {
            Schema::table($owner, function (Blueprint $table) {
                $table->dropConstrainedForeignId('header_id');
                $table->dropConstrainedForeignId('footer_id');
            });
        }

        // Footers nothing used would otherwise vanish, so they stay as footer-only letterheads.
        DB::table('document_layouts')->where('type', 'footer')->whereNotIn('id', $usedFooters)->update([
            'footer_content' => DB::raw('header_content'),
            'header_content' => null,
            'is_default' => false,
            'type' => 'letterhead',
        ]);

        DB::table('document_layouts')->where('type', 'footer')->delete();

        Schema::table('document_layouts', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }

    public function down(): void
    {
        Schema::table('document_layouts', function (Blueprint $table) {
            $table->string('type')->default('header')->after('id');
        });

        foreach (self::OWNERS as $owner) {
            Schema::table($owner, function (Blueprint $table) {
                $table->foreignId('header_id')->nullable()->after('language')->constrained('document_layouts')->nullOnDelete();
                $table->foreignId('footer_id')->nullable()->after('header_id')->constrained('document_layouts')->nullOnDelete();
            });
        }

        foreach (DB::table('document_layouts')->get() as $layout) {
            $footerId = filled($layout->footer_content)
                ? DB::table('document_layouts')->insertGetId([
                    'type' => 'footer',
                    'name' => "{$layout->name} footer",
                    'footer_content' => $layout->footer_content,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
                : null;

            foreach (self::OWNERS as $owner) {
                DB::table($owner)->where('layout_id', $layout->id)->update(['header_id' => $layout->id, 'footer_id' => $footerId]);
            }
        }

        DB::table('document_layouts')->where('type', 'footer')->update(['header_content' => DB::raw('footer_content')]);

        foreach (self::OWNERS as $owner) {
            Schema::table($owner, function (Blueprint $table) {
                $table->dropConstrainedForeignId('layout_id');
            });
        }

        Schema::table('document_layouts', function (Blueprint $table) {
            $table->dropColumn('footer_content');
        });

        Schema::table('document_layouts', function (Blueprint $table) {
            $table->renameColumn('header_content', 'content');
        });
    }

    private function assignLetterheads(): void
    {
        $letterheads = [];
        $claimedHeaders = [];

        foreach (self::OWNERS as $owner) {
            $pairs = DB::table($owner)->select('header_id', 'footer_id')->distinct()->get();

            foreach ($pairs as $pair) {
                if (! $pair->header_id && ! $pair->footer_id) {
                    continue;
                }

                $key = "{$pair->header_id}|{$pair->footer_id}";

                $letterheads[$key] ??= $this->letterheadFor($pair->header_id, $pair->footer_id, $claimedHeaders);

                DB::table($owner)
                    ->where('header_id', $pair->header_id)
                    ->where('footer_id', $pair->footer_id)
                    ->update(['layout_id' => $letterheads[$key]]);
            }
        }
    }

    private function letterheadFor(?int $headerId, ?int $footerId, array &$claimedHeaders): int
    {
        $header = $headerId ? DB::table('document_layouts')->find($headerId) : null;
        $footer = $footerId ? DB::table('document_layouts')->find($footerId) : null;

        // Each header takes its first footer. A second pairing gets its own copy, so no document changes.
        if ($header && ! in_array($headerId, $claimedHeaders, true)) {
            $claimedHeaders[] = $headerId;

            DB::table('document_layouts')->where('id', $headerId)->update(['footer_content' => $footer?->header_content]);

            return $headerId;
        }

        return DB::table('document_layouts')->insertGetId([
            'type' => 'letterhead',
            'name' => trim(($header?->name ?? '').' + '.($footer?->name ?? ''), ' +'),
            'header_content' => $header?->header_content,
            'footer_content' => $footer?->header_content,
            'logo' => $header?->logo,
            'logo_position' => $header?->logo_position ?? 'left',
            'accent_color' => $header?->accent_color,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
