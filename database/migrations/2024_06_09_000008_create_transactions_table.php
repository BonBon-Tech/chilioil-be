<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->date('date');
            $table->decimal('total', 15, 2);
            $table->decimal('sub_total', 15, 2);
            $table->integer('total_item');
            $table->enum('type', ['INTERNAL', 'OFFLINE', 'SHOPEEFOOD', 'GOFOOD', 'GRABFOOD']);
            $table->enum('payment_type', ['QRIS', 'CASH', 'GOPAY', 'SHOPEEPAY', 'OVO']);
            $table->enum('status', ['PAID', 'CANCELED']);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};

