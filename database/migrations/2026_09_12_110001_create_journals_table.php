<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('period_id')->nullable()->constrained('accounting_periods')->nullOnDelete();
            $table->string('journal_no', 50)->nullable();
            $table->date('journal_date');
            $table->string('source_type', 30)->default('manual');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('reference', 100)->nullable();
            $table->string('description', 500)->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reversed_journal_id')->nullable()->constrained('journals')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'journal_no']);
            $table->index(['company_id', 'period_id', 'status']);
            $table->index(['company_id', 'journal_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journals');
    }
};