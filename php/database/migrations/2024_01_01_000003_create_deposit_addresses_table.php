<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sweeper_deposit_addresses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->integer('chain_id');
            $table->string('address');
            $table->text('private_key_encrypted');
            $table->timestamp('last_sweep_at')->nullable();
            $table->timestamps();

            $table->foreign('chain_id')
                  ->references('chain_id')
                  ->on('sweeper_chains')
                  ->onDelete('cascade');

            $table->unique(['chain_id', 'address']);
            $table->index(['user_id', 'chain_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sweeper_deposit_addresses');
    }
};
