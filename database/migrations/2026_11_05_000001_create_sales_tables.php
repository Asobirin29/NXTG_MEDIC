<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Keranjang & transaksi kasir (PRD §6.5, §10.4). status open = keranjang aktif/ditahan;
        // harga & alokasi batch baru dikunci saat checkout (completed).
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('number', 30)->nullable()->comment('Diisi saat checkout');
            $table->enum('type', ['encounter', 'otc']);
            $table->enum('status', ['open', 'completed', 'voided'])->default('open');
            $table->foreignId('encounter_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('prescription_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('payment_type_id')->constrained()->restrictOnDelete();
            $table->string('external_rx_ref', 150)->nullable()->comment('Resep luar untuk obat keras/psikotropika/narkotika OTC');
            $table->string('label', 60)->nullable()->comment('Penanda keranjang ditahan, mis. nama pembeli');
            $table->unsignedBigInteger('total_value')->default(0)->comment('Nilai obat + tindakan sesuai tarif jenis bayar');
            $table->unsignedBigInteger('amount_due')->default(0)->comment('Tagihan ke pasien; Rp0 bila BPJS/Gratis');
            $table->unsignedBigInteger('paid_amount')->default(0);
            $table->unsignedBigInteger('change_amount')->default(0);
            $table->enum('payment_method', ['cash', 'non_cash'])->nullable();
            $table->decimal('cost_total', 15, 2)->default(0)->comment('HPP dari batch yang dialokasikan');
            $table->uuid('idempotency_key')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cashier_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('void_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason', 500)->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'number']);
            $table->unique(['tenant_id', 'idempotency_key']);
            $table->index(['tenant_id', 'status', 'completed_at']);
            $table->index(['encounter_id', 'status']);
        });

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->enum('kind', ['drug', 'service']);
            $table->foreignId('drug_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('drug_packaging_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('prescription_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('encounter_service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 150);
            $table->string('unit_name', 30)->nullable();
            $table->unsignedInteger('quantity_in_base_per_unit')->default(1);
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('quantity_base');
            $table->string('signa', 120)->nullable();
            $table->unsignedBigInteger('unit_price')->default(0)->comment('Obat: per satuan terkecil; tindakan: per tindakan');
            $table->unsignedBigInteger('line_total')->default(0);
            $table->decimal('cost_total', 15, 2)->default(0);
            $table->unsignedInteger('returned_base')->default(0);
            $table->timestamps();
        });

        // Batch yang benar-benar dipotong per item (FEFO, bisa lebih dari satu batch).
        Schema::create('sale_item_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_cost', 15, 4);
            $table->unsignedInteger('returned')->default(0);
        });

        Schema::create('sale_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('number', 30);
            $table->foreignId('sale_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 500);
            $table->unsignedBigInteger('refund_amount')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'number']);
        });

        Schema::create('sale_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('sale_item_batch_id')->constrained()->restrictOnDelete();
            $table->foreignId('batch_id')->constrained()->restrictOnDelete()->comment('Batch tujuan: asal (layak jual) atau karantina');
            $table->unsignedInteger('quantity');
            $table->enum('condition', ['sellable', 'quarantine']);
            $table->unsignedBigInteger('refund');
        });

        DB::statement("ALTER TABLE stock_movements MODIFY type ENUM('receipt','adjustment','opname','dispense','return','quarantine','void') NOT NULL");
        DB::statement('ALTER TABLE sale_item_batches ADD CONSTRAINT chk_sib_returned CHECK (returned <= quantity)');
        DB::statement('ALTER TABLE sale_items ADD CONSTRAINT chk_si_returned CHECK (returned_base <= quantity_base)');
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE stock_movements MODIFY type ENUM('receipt','adjustment','opname','dispense','return','quarantine') NOT NULL");
        Schema::dropIfExists('sale_return_items');
        Schema::dropIfExists('sale_returns');
        Schema::dropIfExists('sale_item_batches');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
    }
};
