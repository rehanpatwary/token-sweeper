<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sweeper_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sweep_id')->nullable();
            $table->integer('chain_id');
            $table->string('deposit_address');
            $table->string('token_address');
            $table->string('amount');
            $table->string('funding_tx_hash')->nullable();
            $table->string('sweep_tx_hash')->nullable();
            $table->string('gas_used')->nullable();
            $table->string('status', 20);
            $table->timestamps();

            $table->foreign('sweep_id')
                  ->references('id')
                  ->on('sweeper_pending_sweeps')
                  ->onDelete('set null');

            $table->foreign('chain_id')
                  ->references('chain_id')
                  ->on('sweeper_chains')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sweeper_logs');
    }
};
