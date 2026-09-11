<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_id')->constrained('journals')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete();
            $table->string('party_type', 20)->nullable();
            $table->unsignedBigInteger('party_id')->nullable();
            $table->string('description', 500)->nullable();
            $table->decimal('debit', 18, 4)->default(0);
            $table->decimal('credit', 18, 4)->default(0);
            $table->timestamps();

            $table->index('journal_id');
            $table->index('account_id');
            $table->index(['party_type', 'party_id']);
        });

        Schema::table('journal_lines', function (Blueprint $table) {
            $table->index(['journal_id', 'account_id'], 'journal_lines_journal_account_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_lines');
    }
};