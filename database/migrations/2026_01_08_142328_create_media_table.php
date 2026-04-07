<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_log_id')->nullable()->constrained('daily_logs')->onDelete('cascade');
            $table->foreignId('safety_incident_id')->nullable()->constrained('safety_incidents')->onDelete('cascade');
            $table->string('url');
            $table->enum('type', ['BEFORE', 'DURING', 'AFTER', 'DOCUMENT'])->default('DURING');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('media');
    }
};
