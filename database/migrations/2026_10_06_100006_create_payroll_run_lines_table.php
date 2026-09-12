<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_run_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('payroll_run_id')->constrained('payroll_runs')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->decimal('gross_pay', 18, 4)->default(0);
            $table->decimal('allowances_total', 18, 4)->default(0);
            $table->decimal('deductions_total', 18, 4)->default(0);
            $table->decimal('net_pay', 18, 4)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['payroll_run_id', 'employee_id']);
            $table->index(['company_id', 'payroll_run_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_run_lines');
    }
};