<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('headline')->nullable();
            $table->text('long_description')->nullable();
            $table->text('care_instructions')->nullable();
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 320)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['headline', 'long_description', 'care_instructions', 'seo_title', 'seo_description']);
        });
    }
};
