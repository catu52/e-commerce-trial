<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('order_number')->nullable()->unique();
            $table->string('email')->nullable();
            $table->string('country')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('address')->nullable();
            $table->string('apartment')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('city')->nullable();
            $table->string('phone')->nullable();
            $table->string('delivery_method')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('promo_code')->nullable();
            $table->decimal('subtotal', 10, 2)->nullable();
            $table->decimal('delivery_cost', 10, 2)->nullable();
            $table->decimal('discount', 10, 2)->nullable();
            $table->string('currency', 3)->default('EUR');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'order_number', 'email', 'country', 'first_name', 'last_name', 'address', 'apartment',
                'postal_code', 'city', 'phone', 'delivery_method', 'payment_method', 'promo_code',
                'subtotal', 'delivery_cost', 'discount', 'currency',
            ]);
        });
    }
};
