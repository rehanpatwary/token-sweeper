<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sweeper_chains', function (Blueprint $table) {
            $table->id();
            $table->integer('chain_id')->unique();
            $table->string('name');
            $table->text('rpc_url');
            $table->string('master_wallet_address');
            $table->text('master_private_key_encrypted');
            $table->string('hot_wallet_address');
            $table->string('native_symbol', 10);
            $table->string('gas_amount_wei');
            $table->integer('gas_limit_token_transfer')->default(100000);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sweeper_chains');
    }
};
