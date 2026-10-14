<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_pages', function (Blueprint $table) {
            $table->id();
            $table->string('hero_eyebrow')->default('');
            $table->string('hero_title')->default('');
            $table->text('hero_subtitle')->default('');
            $table->string('editorial_title')->default('');
            $table->text('editorial_body')->default('');
            $table->string('editorial_image_path')->nullable();
            $table->timestamps();
        });

        Schema::create('lookbook_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('home_page_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('caption')->default('');
            $table->string('link')->default('/catalog');
            $table->string('image_path')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lookbook_items');
        Schema::dropIfExists('home_pages');
    }
};
