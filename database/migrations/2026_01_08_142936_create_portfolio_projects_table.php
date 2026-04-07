<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('portfolio_projects', function (Blueprint $table) {
            $table->id();

            $table->string('title');
            $table->text('description')->nullable();

            $table->string('location')->nullable();
            $table->string('project_type')->nullable(); // ex: Bâtiment, Route, Rénovation

            $table->date('completion_date')->nullable();

            $table->boolean('is_featured')->default(false);
            $table->boolean('is_published')->default(true);

            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('portfolio_projects');
    }
};
