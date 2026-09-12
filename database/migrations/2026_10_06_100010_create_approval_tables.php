<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_workflows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('module', 60);
            $table->string('name');
            $table->decimal('min_amount', 18, 4)->default(0);
            $table->decimal('max_amount', 18, 4)->nullable();
            $table->foreignId('approver_role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->foreignId('approver_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('sequence')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['company_id', 'module', 'is_active']);
        });

        Schema::create('approval_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('module', 60);
            $table->morphs('approvable');
            $table->decimal('amount', 18, 4)->default(0);
            $table->foreignId('requested_by')->constrained('users');
            $table->string('status', 20)->default('pending');
            $table->unsignedTinyInteger('current_step')->default(1);
            $table->unsignedTinyInteger('total_steps')->default(1);
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('reject_reason')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'approvable_type', 'approvable_id']);
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_requests');
        Schema::dropIfExists('approval_workflows');
    }
};