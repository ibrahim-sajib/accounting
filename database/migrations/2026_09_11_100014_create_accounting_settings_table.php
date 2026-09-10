<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained('companies')->cascadeOnDelete();
            $table->foreignId('default_sales_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('default_purchase_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('default_inventory_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('default_ar_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('default_ap_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('default_cash_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('default_bank_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('default_tax_input_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('default_tax_output_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->json('voucher_numbering')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_settings');
    }
};