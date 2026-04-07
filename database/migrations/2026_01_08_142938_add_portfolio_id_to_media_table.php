<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('media', function (Blueprint $table) {
            $table->foreignId('portfolio_project_id')
                  ->nullable()
                  ->constrained('portfolio_projects')
                  ->cascadeOnDelete();
        });
    }

    public function down()
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropForeign(['portfolio_project_id']);
            $table->dropColumn('portfolio_project_id');
        });
    }
};
