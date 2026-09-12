<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_statement_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_statement_import_id')->constrained('bank_statement_imports')->cascadeOnDelete();
            $table->date('line_date');
            $table->string('description', 200)->nullable();
            $table->decimal('amount', 18, 4)->default(0);
            $table->foreignId('matched_transaction_id')->nullable()->constrained('cash_bank_transactions')->nullOnDelete();
            $table->boolean('is_reconciled')->default(false);
            $table->timestamps();

            $table->index(['bank_statement_import_id']);
            $table->index(['matched_transaction_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_statement_lines');
    }
};