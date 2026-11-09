<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_layouts', function (Blueprint $table) {
            $table->string('accent_color', 7)->nullable()->after('logo_position');
        });
    }

    public function down(): void
    {
        Schema::table('document_layouts', function (Blueprint $table) {
            $table->dropColumn('accent_color');
        });
    }
};
