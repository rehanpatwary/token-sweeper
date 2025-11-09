<?php

use Illuminate\Support\Facades\Route;
use Multicoin\TokenSweeper\Controllers\TokenSweeperController;

Route::prefix('api/sweeper')->middleware('api')->group(function () {
    Route::get('health', [TokenSweeperController::class, 'health']);
    Route::get('chains', [TokenSweeperController::class, 'chains']);
    Route::get('tokens', [TokenSweeperController::class, 'tokens']);

    Route::post('deposit-address', [TokenSweeperController::class, 'createDepositAddress']);
    Route::get('user-addresses', [TokenSweeperController::class, 'userAddresses']);

    Route::get('sweep-status', [TokenSweeperController::class, 'sweepStatus']);
    Route::get('pending-sweeps', [TokenSweeperController::class, 'pendingSweeps']);
    Route::get('sweep-logs', [TokenSweeperController::class, 'sweepLogs']);

    Route::post('process-sweep', [TokenSweeperController::class, 'processSweep']);
});
