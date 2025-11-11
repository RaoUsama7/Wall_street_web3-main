<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stock_assets', function (Blueprint $table) {
            $table->id();
            $table->string('symbol')->unique();
            $table->string('company_name');
            $table->string('exchange')->default('NASDAQ');
            $table->decimal('price', 18, 8)->nullable();
            $table->decimal('change_percent_24h', 10, 4)->nullable();
            $table->bigInteger('market_cap')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_update_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_assets');
    }
};

