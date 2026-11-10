<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pdf_documents', function (Blueprint $table) {
            $table->string('signature_mode')->default('default')->after('background_image');
            $table->string('stamp_mode')->default('default')->after('signature');
        });

        // Until now an uploaded file meant "use this one" and an empty field meant "General settings".
        DB::table('pdf_documents')->whereNotNull('signature')->update(['signature_mode' => 'upload']);
        DB::table('pdf_documents')->whereNotNull('stamp')->update(['stamp_mode' => 'upload']);
    }

    public function down(): void
    {
        Schema::table('pdf_documents', function (Blueprint $table) {
            $table->dropColumn(['signature_mode', 'stamp_mode']);
        });
    }
};
