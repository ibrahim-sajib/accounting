<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipt_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receipt_id')->constrained('receipts')->cascadeOnDelete();
            $table->foreignId('sales_invoice_id')->constrained('sales_invoices')->cascadeOnDelete();
            $table->decimal('amount', 18, 4)->default(0);
            $table->timestamps();

            $table->unique(['receipt_id', 'sales_invoice_id']);
            $table->index(['sales_invoice_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_allocations');
    }
};