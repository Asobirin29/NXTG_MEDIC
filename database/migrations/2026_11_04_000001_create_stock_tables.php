<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('phone', 30)->nullable();
            $table->string('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
        });

        // Dokumen penerimaan barang (faktur pemasok). Tidak bisa diubah setelah disimpan;
        // koreksi lewat penyesuaian stok.
        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('number', 30);
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->string('invoice_number', 60)->nullable();
            $table->date('received_at');
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('total_cost')->default(0)->comment('Rupiah');
            $table->timestamps();

            $table->unique(['tenant_id', 'number']);
            $table->index(['tenant_id', 'received_at']);
        });

        // Saldo per batch dalam satuan terkecil. quarantine = retur rusak/karantina (S6),
        // tidak pernah dialokasikan FEFO.
        Schema::create('batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('drug_id')->constrained()->restrictOnDelete();
            $table->string('batch_number', 60);
            $table->date('expiry_date');
            $table->decimal('purchase_price_per_base', 15, 4)->default(0)->comment('HPP per satuan terkecil');
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('status', ['available', 'quarantine'])->default('available');
            $table->unsignedInteger('qty_on_hand')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'drug_id', 'batch_number', 'status']);
            $table->index(['drug_id', 'status', 'expiry_date']);
        });

        Schema::create('goods_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('goods_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('drug_id')->constrained()->restrictOnDelete();
            $table->foreignId('batch_id')->constrained()->restrictOnDelete();
            $table->string('drug_name', 150);
            $table->string('batch_number', 60);
            $table->date('expiry_date');
            $table->string('unit_name', 30);
            $table->unsignedInteger('quantity_in_base_per_unit');
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('quantity_base');
            $table->unsignedBigInteger('unit_price')->comment('Harga beli per kemasan, Rupiah');
            $table->timestamps();
        });

        Schema::create('stock_opnames', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('number', 30);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('reason');
            $table->unsignedInteger('batches_counted');
            $table->unsignedInteger('batches_changed');
            $table->timestamps();

            $table->unique(['tenant_id', 'number']);
        });

        // Buku besar stok (PRD §6.4): append-only. quantity bertanda (+ masuk, − keluar),
        // balance_after = saldo batch sesudah mutasi.
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_id')->constrained()->restrictOnDelete();
            $table->foreignId('drug_id')->constrained()->restrictOnDelete();
            $table->enum('type', ['receipt', 'adjustment', 'opname', 'dispense', 'return', 'quarantine']);
            $table->integer('quantity');
            $table->unsignedInteger('balance_after');
            $table->string('reason', 500)->nullable();
            $table->nullableMorphs('reference');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['drug_id', 'created_at']);
            $table->index(['batch_id', 'id']);
        });

        DB::statement('ALTER TABLE batches ADD CONSTRAINT chk_batch_qty CHECK (qty_on_hand >= 0)');
        DB::statement('ALTER TABLE stock_movements ADD CONSTRAINT chk_movement_qty CHECK (quantity <> 0)');

        // Lapis kedua selain model: database menolak UPDATE/DELETE pada buku besar.
        // (Hapus berantai saat tenant dihapus tetap jalan: FK cascade tidak memicu trigger.)
        DB::unprepared("CREATE TRIGGER stock_movements_no_update BEFORE UPDATE ON stock_movements FOR EACH ROW
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'stock_movements bersifat append-only'");
        DB::unprepared("CREATE TRIGGER stock_movements_no_delete BEFORE DELETE ON stock_movements FOR EACH ROW
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'stock_movements bersifat append-only'");
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS stock_movements_no_update');
        DB::unprepared('DROP TRIGGER IF EXISTS stock_movements_no_delete');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_opnames');
        Schema::dropIfExists('goods_receipt_items');
        Schema::dropIfExists('batches');
        Schema::dropIfExists('goods_receipts');
        Schema::dropIfExists('suppliers');
    }
};
