<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('safety_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('portfolio_projects')->onDelete('cascade');
            $table->foreignId('reporter_id')->constrained('users');
            $table->date('date');
            $table->enum('severity', ['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'])->default('LOW');
            $table->text('description');
            $table->string('status')->default('OPEN');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('safety_incidents');
    }
};
