<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_layouts', function (Blueprint $table) {
            $table->longText('header_right_content')->nullable()->after('header_content');
        });
    }

    public function down(): void
    {
        Schema::table('document_layouts', function (Blueprint $table) {
            $table->dropColumn('header_right_content');
        });
    }
};
