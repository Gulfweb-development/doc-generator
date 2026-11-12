<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_document_template', function (Blueprint $table) {
            $table->foreignId('admin_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_template_id')->constrained()->cascadeOnDelete();
            $table->primary(['admin_id', 'document_template_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_document_template');
    }
};
