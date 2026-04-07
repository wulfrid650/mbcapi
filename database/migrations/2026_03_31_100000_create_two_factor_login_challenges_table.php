<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('two_factor_login_challenges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('challenge_token', 80)->unique();
            $table->string('code_hash');
            $table->timestamp('expires_at')->index();
            $table->timestamp('verified_at')->nullable()->index();
            $table->timestamp('consumed_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('two_factor_login_challenge_sends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('challenge_id')->constrained('two_factor_login_challenges')->cascadeOnDelete();
            $table->timestamps();
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('two_factor_login_challenge_sends');
        Schema::dropIfExists('two_factor_login_challenges');
    }
};
