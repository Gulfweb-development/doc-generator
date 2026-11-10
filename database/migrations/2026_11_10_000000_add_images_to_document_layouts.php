<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const COLUMNS = ['header_image', 'footer_image', 'background_image', 'signature', 'stamp'];

    public function up(): void
    {
        Schema::table('document_layouts', function (Blueprint $table) {
            foreach (self::COLUMNS as $column) {
                $table->string($column)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('document_layouts', function (Blueprint $table) {
            $table->dropColumn(self::COLUMNS);
        });
    }
};
