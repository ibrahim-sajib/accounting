<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('expense_no', 30)->nullable();
            $table->foreignId('category_id')->constrained('expense_categories')->restrictOnDelete();
            $table->string('payee', 200);
            $table->date('expense_date');
            $table->decimal('amount', 18, 4)->default(0);
            $table->foreignId('tax_rate_id')->nullable()->constrained('tax_rates')->nullOnDelete();
            $table->decimal('tax_amount', 18, 4)->default(0);
            $table->string('payment_method', 20);
            $table->foreignId('cash_account_id')->nullable()->constrained('cash_accounts')->nullOnDelete();
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('payable_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->string('reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('draft');
            $table->foreignId('journal_id')->nullable()->constrained('journals')->nullOnDelete();
            $table->boolean('is_recurring')->default(false);
            $table->string('recurrence_frequency', 20)->nullable();
            $table->date('next_generation_date')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'expense_no']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'expense_date']);
            $table->index(['company_id', 'next_generation_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};