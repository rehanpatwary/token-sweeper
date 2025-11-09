<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sweeper_pending_sweeps', function (Blueprint $table) {
            $table->id();
            $table->string('deposit_address');
            $table->integer('chain_id');
            $table->string('token_address');
            $table->string('token_symbol', 20)->nullable();
            $table->string('amount');
            $table->enum('status', [
                'pending',
                'funding',
                'sweeping',
                'completed',
                'failed'
            ])->default('pending');
            $table->string('funding_tx_hash')->nullable();
            $table->string('sweep_tx_hash')->nullable();
            $table->text('error_message')->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamps();

            $table->foreign('chain_id')
                  ->references('chain_id')
                  ->on('sweeper_chains')
                  ->onDelete('cascade');

            $table->index('status');
            $table->index(['deposit_address', 'chain_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sweeper_pending_sweeps');
    }
};
