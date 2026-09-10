<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Key-value settings store, scoped per company and grouped by concern
        // (e.g. general, localization, numbering, notifications, accounting).
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('group', 40)->default('general');
            $table->string('key');
            $table->text('value')->nullable();
            $table->string('type', 20)->default('string'); // string|int|bool|json
            $table->timestamps();

            $table->index(['company_id', 'group']);
            $table->unique(['company_id', 'key'], 'settings_company_key_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
