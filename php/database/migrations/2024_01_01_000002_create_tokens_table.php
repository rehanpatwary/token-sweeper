<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sweeper_tokens', function (Blueprint $table) {
            $table->id();
            $table->integer('chain_id');
            $table->string('symbol', 20);
            $table->string('name', 100);
            $table->string('contract_address');
            $table->integer('decimals');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('chain_id')
                  ->references('chain_id')
                  ->on('sweeper_chains')
                  ->onDelete('cascade');

            $table->unique(['chain_id', 'contract_address']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sweeper_tokens');
    }
};
