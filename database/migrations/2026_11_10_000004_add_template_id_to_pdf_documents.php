<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pdf_documents', function (Blueprint $table) {
            // Only records which template was picked; the document keeps its own copy of the content.
            $table->foreignId('template_id')->nullable()->after('id')->constrained('document_templates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pdf_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('template_id');
        });
    }
};
