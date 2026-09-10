<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('country_code', 3)->nullable();
            $table->string('currency_code', 3)->nullable();
            $table->string('tax_registration_no')->nullable();
            $table->string('vat_registration_no')->nullable();
            $table->string('accounting_basis', 20)->default('accrual');
            $table->string('status', 20)->default('active');
            $table->json('settings')->nullable();

            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('zip_code')->nullable();

            $table->date('fiscal_year_start')->nullable();
            $table->date('fiscal_year_end')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
