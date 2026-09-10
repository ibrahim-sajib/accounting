<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('currency_id')->constrained('currencies')->cascadeOnDelete();
            $table->decimal('rate', 18, 6);
            $table->date('effective_date');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['currency_id', 'effective_date']);
            $table->unique(['currency_id', 'effective_date'], 'exchange_rates_currency_date_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
