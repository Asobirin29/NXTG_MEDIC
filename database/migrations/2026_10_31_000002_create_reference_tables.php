<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Zat aktif. tenant_id NULL = referensi bersama; terisi = tambahan faskes itu sendiri.
        Schema::create('substances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('atc_code', 10)->nullable();
            $table->timestamps();

            $table->index('name');
        });

        // Kamus ICD-10 (global).
        Schema::create('icd10_masters', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name_id');
            $table->string('name_en')->nullable();
            $table->boolean('is_active')->default(true);

            $table->index('name_id');
            $table->index('name_en');
        });

        // Template interaksi yang dikurasi apoteker mitra; disalin ke tiap faskes baru.
        Schema::create('interaction_rule_templates', function (Blueprint $table) {
            $table->id();
            // Pasangan disimpan terurut (a < b) supaya A-B dan B-A tidak dobel.
            $table->foreignId('substance_a_id')->constrained('substances')->cascadeOnDelete();
            $table->foreignId('substance_b_id')->constrained('substances')->cascadeOnDelete();
            $table->enum('severity', ['minor', 'moderate', 'major']);
            $table->text('effect');
            $table->text('recommendation')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique(['substance_a_id', 'substance_b_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interaction_rule_templates');
        Schema::dropIfExists('icd10_masters');
        Schema::dropIfExists('substances');
    }
};
