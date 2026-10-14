<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('sku');
            $table->string('code')->nullable()->after('slug');
            $table->foreignId('category_id')->nullable()->after('code')->constrained()->nullOnDelete();
            $table->string('sub_category')->nullable();
            $table->string('tag')->nullable();
            $table->string('badge')->nullable();
            $table->string('substrate')->nullable();
            $table->string('spec_weight')->nullable();
            $table->string('colorway')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->string('currency', 3)->default('EUR');
            $table->json('sizes')->nullable();
            $table->json('colors')->nullable();
            $table->json('specs')->nullable();

            $table->index('is_featured');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
            $table->dropIndex(['is_featured']);
            $table->dropColumn([
                'slug', 'code', 'sub_category', 'tag', 'badge', 'substrate', 'spec_weight',
                'colorway', 'is_featured', 'currency', 'sizes', 'colors', 'specs',
            ]);
        });
    }
};
