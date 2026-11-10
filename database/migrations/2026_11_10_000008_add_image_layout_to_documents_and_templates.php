<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['pdf_documents', 'document_templates'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                // letterhead: one full-page image with the content fitted into it. separate: header, footer and background images.
                $table->string('image_layout')->default('letterhead');
            });

            // Records that already use a header or footer banner keep rendering the same way.
            DB::table($tableName)
                ->where(fn ($query) => $query->whereNotNull('header_image')->orWhereNotNull('footer_image'))
                ->update(['image_layout' => 'separate']);
        }
    }

    public function down(): void
    {
        foreach (['pdf_documents', 'document_templates'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('image_layout');
            });
        }
    }
};
