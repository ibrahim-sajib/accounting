<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('company_id')
                ->nullable()
                ->constrained('companies')
                ->nullOnDelete()
                ->after('id');

            $table->boolean('is_super_admin')->default(false)->after('email');
            $table->string('status', 20)->default('active')->after('is_super_admin');
            $table->string('phone')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropColumn(['company_id', 'is_super_admin', 'status', 'phone']);
        });
    }
};
