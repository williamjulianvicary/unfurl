<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ogify_og_images', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->index();
            $table->string('variant')->default('default');
            $table->string('disk');
            $table->string('path');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->timestamps();

            $table->unique(['key', 'variant']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ogify_og_images');
    }
};
