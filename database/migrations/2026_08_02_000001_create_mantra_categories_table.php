<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mantra_categories', function (Blueprint $table) {
            $table->id();
            $table->json('name'); // {"es": "...", "en": "..."}
            $table->string('slug')->unique();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mantra_categories');
    }
};
