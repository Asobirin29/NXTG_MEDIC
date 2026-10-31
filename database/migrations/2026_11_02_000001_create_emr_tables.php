<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Asesmen awal: tanda vital, antropometri, keluhan awal (PRD §6.2).
        Schema::create('vital_signs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('encounter_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('systolic')->nullable();
            $table->unsignedSmallInteger('diastolic')->nullable();
            $table->unsignedSmallInteger('heart_rate')->nullable();
            $table->unsignedSmallInteger('respiratory_rate')->nullable();
            $table->decimal('temperature', 4, 1)->nullable();
            $table->unsignedTinyInteger('spo2')->nullable();
            $table->decimal('weight', 5, 2)->nullable()->comment('kg');
            $table->decimal('height', 5, 1)->nullable()->comment('cm');
            $table->text('chief_complaint')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['encounter_id', 'recorded_at']);
        });

        // CPPT dokter (SOAP). Dikunci saat pemeriksaan selesai; koreksi lewat addendum.
        Schema::create('clinical_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('encounter_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('subjective')->nullable();
            $table->text('objective')->nullable();
            $table->text('assessment')->nullable();
            $table->text('plan')->nullable();
            $table->enum('status', ['draft', 'locked'])->default('draft');
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('clinical_note_addenda', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('clinical_note_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('content');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('encounter_diagnoses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('encounter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('icd10_id')->constrained('icd10_masters')->restrictOnDelete();
            $table->enum('type', ['primary', 'secondary']);
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['encounter_id', 'icd10_id']);
        });

        // Tindakan yang diberikan; tarif disalin saat itu (snapshot) untuk tagihan.
        Schema::create('encounter_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('encounter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medical_service_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->unsignedBigInteger('unit_price');
            $table->string('note')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Quick-text template SOAP per faskes.
        Schema::create('soap_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->enum('section', ['subjective', 'objective', 'assessment', 'plan']);
            $table->string('title', 80);
            $table->text('body');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'section']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('soap_templates');
        Schema::dropIfExists('encounter_services');
        Schema::dropIfExists('encounter_diagnoses');
        Schema::dropIfExists('clinical_note_addenda');
        Schema::dropIfExists('clinical_notes');
        Schema::dropIfExists('vital_signs');
    }
};
