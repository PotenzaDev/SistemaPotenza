<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rotina_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rotina_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'rotina_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rotina_user');
    }
};
