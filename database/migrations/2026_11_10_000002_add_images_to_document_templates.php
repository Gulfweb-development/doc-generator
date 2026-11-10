<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // signature and stamp already exist on templates.
    private const COLUMNS = ['header_image', 'footer_image', 'background_image'];

    public function up(): void
    {
        Schema::table('document_templates', function (Blueprint $table) {
            foreach (self::COLUMNS as $column) {
                $table->string($column)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('document_templates', function (Blueprint $table) {
            $table->dropColumn(self::COLUMNS);
        });
    }
};
