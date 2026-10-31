<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Header e-Resep (FHIR MedicationRequest). Satu resep per kunjungan.
        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('encounter_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['draft', 'pending_verification', 'prepared', 'dispensed', 'cancelled'])->default('draft');
            $table->text('notes')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['patient_id', 'sent_at']);
        });

        // Item resep. Nama obat & kemasan disalin (snapshot) agar resep lama tidak berubah
        // bila master obat diubah kemudian.
        Schema::create('prescription_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prescription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('drug_id')->constrained()->restrictOnDelete();
            $table->foreignId('drug_packaging_id')->nullable()->constrained()->nullOnDelete();
            $table->string('drug_name', 150);
            $table->string('unit_name', 30);
            $table->unsignedInteger('quantity_in_base_per_unit');
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('quantity_base');
            $table->string('signa', 120);
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['prescription_id', 'drug_id']);
        });

        // Jejak setiap peringatan klinis yang dilewati beserta alasannya (PRD §6.3, §10.3).
        Schema::create('alert_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('context', ['prescribe', 'dispense', 'pos']);
            $table->foreignId('patient_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('prescription_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('sale_id')->nullable()->comment('Diisi modul POS (S6)');
            $table->enum('alert_type', ['allergy', 'interaction', 'duplicate']);
            $table->enum('severity', ['minor', 'moderate', 'major']);
            $table->string('alert_key', 120);
            $table->foreignId('interaction_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->text('message');
            $table->text('reason');
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_overrides');
        Schema::dropIfExists('prescription_items');
        Schema::dropIfExists('prescriptions');
    }
};
