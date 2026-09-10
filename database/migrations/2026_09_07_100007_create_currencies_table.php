<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('code', 3);
            $table->string('name');
            $table->string('symbol', 10)->nullable();
            $table->unsignedTinyInteger('decimal_places')->default(2);
            $table->boolean('is_base')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['company_id', 'code']);
            $table->unique(['company_id', 'code'], 'currencies_company_code_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
