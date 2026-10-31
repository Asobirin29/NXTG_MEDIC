<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('polis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
        });

        // Umum, BPJS, Asuransi, Gratis + jenis lain per faskes (PRD §6.5).
        Schema::create('payment_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name', 100);
            // false = tagihan ke pasien Rp0 (BPJS/Gratis), nilai obat tetap dicatat.
            $table->boolean('patient_pays')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('medical_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('poli_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 30);
            $table->string('name', 150);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('medical_service_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medical_service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_type_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('price')->comment('Rupiah');
            $table->timestamps();

            $table->unique(['medical_service_id', 'payment_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_service_prices');
        Schema::dropIfExists('medical_services');
        Schema::dropIfExists('payment_types');
        Schema::dropIfExists('polis');
    }
};
