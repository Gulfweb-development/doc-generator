<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_layouts', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('name');
            $table->longText('content')->nullable();
            $table->string('logo')->nullable();
            $table->string('logo_position')->default('left');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('document_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $this->contentColumns($table);
            $table->timestamps();
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('reference')->nullable()->unique();
            $table->string('status')->default('draft');
            $this->contentColumns($table);
            $table->json('placeholder_values')->nullable();
            $table->string('pdf_path')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
        Schema::dropIfExists('document_templates');
        Schema::dropIfExists('document_layouts');
    }

    private function contentColumns(Blueprint $table): void
    {
        $table->string('language')->default('en');
        $table->foreignId('header_id')->nullable()->constrained('document_layouts')->nullOnDelete();
        $table->foreignId('footer_id')->nullable()->constrained('document_layouts')->nullOnDelete();
        $table->longText('body')->nullable();

        foreach (['logo', 'signature', 'stamp'] as $asset) {
            $table->string("{$asset}_mode")->default('default');
            $table->string($asset)->nullable();
        }
    }
};
