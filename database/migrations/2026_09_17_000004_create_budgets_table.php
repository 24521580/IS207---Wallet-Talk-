<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('month');           // 1–12
            $table->unsignedSmallInteger('year');
            $table->unsignedBigInteger('limit_amount');     // VNĐ nguyên, nhất quán với transactions
            $table->timestamps();

            // Mỗi user chỉ có một ngân sách cho mỗi danh mục trong mỗi tháng
            $table->unique(['user_id', 'category_id', 'month', 'year'], 'budgets_unique');
            $table->index(['user_id', 'month', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};
