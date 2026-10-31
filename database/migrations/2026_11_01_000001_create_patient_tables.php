<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Penomoran berurutan per faskes (No. RM, nomor antrean, nomor transaksi).
        Schema::create('sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->unsignedBigInteger('value')->default(0);

            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('medical_record_number', 20);
            $table->string('name', 150);
            $table->date('birth_date');
            $table->enum('gender', ['L', 'P']);
            // NIK dienkripsi AES-256; pencarian lewat HMAC-SHA256 blind index (PRD §10.5).
            $table->text('nik')->nullable();
            $table->char('nik_blind_index', 64)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('address')->nullable();
            $table->string('insurance_number', 30)->nullable()->comment('No. peserta BPJS/asuransi');
            $table->string('blood_type', 3)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'medical_record_number']);
            $table->unique(['tenant_id', 'nik_blind_index']);
            $table->index(['tenant_id', 'name']);
            $table->index(['tenant_id', 'insurance_number']);
            $table->index(['tenant_id', 'phone']);
        });

        // Alergi obat berbasis zat aktif, atau alergi non-obat (makanan/cuaca/lainnya).
        Schema::create('patient_allergies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['drug', 'food', 'environment', 'other']);
            $table->foreignId('substance_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('allergen_name', 150)->nullable();
            $table->enum('severity', ['minor', 'moderate', 'major'])->nullable();
            $table->string('reaction', 255)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['patient_id', 'substance_id']);
        });

        Schema::create('encounters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('poli_id')->constrained()->restrictOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('payment_type_id')->constrained()->restrictOnDelete();
            $table->date('queue_date');
            $table->unsignedSmallInteger('queue_number');
            $table->enum('status', [
                'QUEUED', 'NURSE_ASSESSED', 'IN_CONSULTATION', 'WAITING_PHARMACY', 'READY_BILLING', 'COMPLETED', 'CANCELLED',
            ])->default('QUEUED');
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('registered_at');
            $table->timestamp('nurse_assessed_at')->nullable();
            $table->timestamp('consultation_started_at')->nullable();
            $table->timestamp('pharmacy_queued_at')->nullable();
            $table->timestamp('billing_ready_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'poli_id', 'queue_date', 'queue_number']);
            $table->index(['tenant_id', 'queue_date', 'status']);
            $table->index(['patient_id', 'queue_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('encounters');
        Schema::dropIfExists('patient_allergies');
        Schema::dropIfExists('patients');
        Schema::dropIfExists('sequences');
    }
};
