<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fonts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // mPDF font key, also the CSS font-family name in the editor and the PDF.
            $table->string('family')->unique();
            // Uploaded TTF on the local disk; null for a font that ships with mPDF.
            $table->string('file')->nullable();
            $table->timestamps();
        });

        DB::table('fonts')->insert(collect([
            'FreeSerif' => 'freeserif',
            'FreeSans' => 'freesans',
            'DejaVu Sans' => 'dejavusans',
            'XB Riyaz (Arabic)' => 'xbriyaz',
            'Lateef (Arabic)' => 'lateef',
        ])->map(fn (string $family, string $name): array => [
            'name' => $name,
            'family' => $family,
            'created_at' => now(),
            'updated_at' => now(),
        ])->values()->all());
    }

    public function down(): void
    {
        Schema::dropIfExists('fonts');
    }
};
