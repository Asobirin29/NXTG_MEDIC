<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drugs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('kfa_code', 30)->nullable()->comment('Kamus Farmasi dan Alat Kesehatan Kemenkes');
            $table->string('name', 150);
            $table->string('dosage_form', 40)->comment('Bentuk sediaan');
            $table->string('strength', 60)->nullable()->comment('Kekuatan, mis. 500 mg');
            $table->enum('category', ['bebas', 'bebas_terbatas', 'keras', 'psikotropika', 'narkotika']);
            $table->string('base_unit', 20)->comment('Satuan terkecil: tablet, kapsul, ml, ampul…');
            $table->string('barcode', 50)->nullable();
            $table->unsignedInteger('min_stock')->default(0)->comment('Ambang stok menipis, satuan terkecil');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'kfa_code']);
            $table->unique(['tenant_id', 'barcode']);
            $table->index(['tenant_id', 'name']);
        });

        Schema::create('drug_substances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('drug_id')->constrained()->cascadeOnDelete();
            $table->foreignId('substance_id')->constrained()->restrictOnDelete();
            $table->decimal('strength_value', 12, 3)->nullable();
            $table->string('strength_unit', 20)->nullable();

            $table->unique(['drug_id', 'substance_id']);
        });

        // Konversi kemasan: 1 Box = 100 tablet, 1 Strip = 10 tablet, 1 Tablet = 1.
        Schema::create('drug_packagings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('drug_id')->constrained()->cascadeOnDelete();
            $table->string('unit_name', 30);
            $table->unsignedInteger('quantity_in_base');
            $table->string('barcode', 50)->nullable();
            $table->boolean('is_default_sale')->default(false);
            $table->timestamps();

            $table->unique(['drug_id', 'unit_name']);
        });

        // Harga jual per satuan terkecil per jenis pembayaran (Rupiah).
        Schema::create('drug_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('drug_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_type_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('price_per_base');
            $table->timestamps();

            $table->unique(['drug_id', 'payment_type_id']);
        });

        DB::statement('ALTER TABLE drug_packagings ADD CONSTRAINT chk_packaging_qty CHECK (quantity_in_base >= 1)');

        // Aturan interaksi milik faskes (salinan template + tambahan sendiri).
        Schema::create('interaction_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('interaction_rule_templates')->nullOnDelete();
            $table->unsignedInteger('template_version')->nullable();
            $table->foreignId('substance_a_id')->constrained('substances')->restrictOnDelete();
            $table->foreignId('substance_b_id')->constrained('substances')->restrictOnDelete();
            $table->enum('severity', ['minor', 'moderate', 'major']);
            $table->text('effect');
            $table->text('recommendation')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'substance_a_id', 'substance_b_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interaction_rules');
        Schema::dropIfExists('drug_prices');
        Schema::dropIfExists('drug_packagings');
        Schema::dropIfExists('drug_substances');
        Schema::dropIfExists('drugs');
    }
};
