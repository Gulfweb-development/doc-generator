<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pdf_documents', function (Blueprint $table) {
            // Typed by the user and printed beside the letterhead's "our reference" label; separate from the finalize number.
            $table->string('reference_number')->nullable()->after('reference');
        });
    }

    public function down(): void
    {
        Schema::table('pdf_documents', function (Blueprint $table) {
            $table->dropColumn('reference_number');
        });
    }
};
