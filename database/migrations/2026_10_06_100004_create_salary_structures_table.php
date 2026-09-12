<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_structures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->decimal('basic', 18, 4)->default(0);
            $table->decimal('house_rent_allowance', 18, 4)->default(0);
            $table->decimal('medical_allowance', 18, 4)->default(0);
            $table->decimal('travel_allowance', 18, 4)->default(0);
            $table->decimal('other_allowance', 18, 4)->default(0);
            $table->decimal('income_tax_deduction', 18, 4)->default(0);
            $table->decimal('provident_fund_deduction', 18, 4)->default(0);
            $table->decimal('other_deduction', 18, 4)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'employee_id']);
            $table->index(['company_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_structures');
    }
};