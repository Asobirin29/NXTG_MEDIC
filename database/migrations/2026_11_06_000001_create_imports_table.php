<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Impor Excel/CSV untuk onboarding (PRD §6.6): diperiksa dulu (pratinjau + error
        // per baris), baru diproses setelah dikonfirmasi — impor besar lewat antrean.
        Schema::create('imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 30);
            $table->enum('status', ['previewed', 'queued', 'processing', 'completed', 'failed'])->default('previewed');
            $table->string('original_name');
            $table->string('path');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('error_rows')->default(0);
            $table->unsignedInteger('imported_rows')->default(0);
            $table->json('errors')->nullable()->comment('baris => daftar pesan');
            $table->json('preview')->nullable()->comment('Beberapa baris pertama yang valid');
            $table->text('failure_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imports');
    }
};
