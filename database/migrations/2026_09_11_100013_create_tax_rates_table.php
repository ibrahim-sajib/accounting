<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('tax_type_id')->constrained('tax_types')->cascadeOnDelete();
            $table->string('name');
            $table->decimal('rate_percent', 18, 6);
            $table->boolean('is_inclusive')->default(false);
            $table->foreignId('input_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('output_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->date('effective_date');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tax_type_id', 'effective_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_rates');
    }
};