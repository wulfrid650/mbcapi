<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->boolean('is_manual')->default(false)->after('status');
            $table->string('payment_method')->nullable()->after('is_manual'); // cash, bank_transfer, check
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['is_manual', 'payment_method']);
        });
    }
};
