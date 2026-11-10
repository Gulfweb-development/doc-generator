<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdf_documents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('language', 2)->default('en');
            $table->longText('body')->nullable();
            $table->string('header_image')->nullable();
            $table->string('footer_image')->nullable();
            $table->string('background_image')->nullable();
            $table->string('signature')->nullable();
            $table->string('stamp')->nullable();
            $table->string('status')->default('draft');
            $table->string('reference')->nullable()->unique();
            $table->timestamp('finalized_at')->nullable();
            $table->string('pdf_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdf_documents');
    }
};
