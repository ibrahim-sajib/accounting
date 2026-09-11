<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->string('type', 20)->default('receipt')->after('customer_id');
        });

        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->decimal('write_off_amount', 18, 4)->default(0)->after('amount_paid');
            $table->string('write_off_reason', 500)->nullable()->after('write_off_amount');
            $table->timestamp('written_off_at')->nullable()->after('write_off_reason');
            $table->unsignedBigInteger('written_off_by')->nullable()->after('written_off_at');
        });
    }

    public function down(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->dropColumn(['write_off_amount', 'write_off_reason', 'written_off_at', 'written_off_by']);
        });

        Schema::table('receipts', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};