<?php

namespace Multicoin\TokenSweeper\Controllers;

use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Routing\Controller;
use Multicoin\TokenSweeper\Models\{Chain, Token, DepositAddress, PendingSweep, SweepLog};
use Multicoin\TokenSweeper\Services\{WalletService, SweeperService};

class TokenSweeperController extends Controller
{
    public function __construct(
        protected WalletService $walletService,
        protected SweeperService $sweeperService
    ) {}

    public function health(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function chains(): JsonResponse
    {
        $chains = Chain::select('id', 'chain_id', 'name', 'native_symbol', 'is_active')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $chains,
        ]);
    }

    public function tokens(Request $request): JsonResponse
    {
        $query = Token::with('chain:chain_id,name')
            ->where('is_active', true);

        if ($request->has('chain_id')) {
            $query->forChain($request->chain_id);
        }

        return response()->json([
            'success' => true,
            'data' => $query->get(),
        ]);
    }

    public function createDepositAddress(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer',
            'chain_id' => 'required|integer|exists:sweeper_chains,chain_id',
        ]);

        $address = $this->walletService->createDepositAddress(
            $validated['user_id'],
            $validated['chain_id']
        );

        return response()->json([
            'success' => true,
            'data' => [
                'user_id' => $validated['user_id'],
                'chain_id' => $validated['chain_id'],
                'address' => $address,
            ],
        ], 201);
    }

    public function userAddresses(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer',
        ]);

        $addresses = $this->walletService->getUserDepositAddresses($validated['user_id']);

        return response()->json([
            'success' => true,
            'data' => $addresses,
        ]);
    }

    public function sweepStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'address' => 'required|string',
            'chain_id' => 'required|integer',
        ]);

        $sweeps = PendingSweep::with('chain:chain_id,name')
            ->where('deposit_address', $validated['address'])
            ->where('chain_id', $validated['chain_id'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $sweeps,
        ]);
    }

    public function pendingSweeps(Request $request): JsonResponse
    {
        $status = $request->get('status', 'pending');
        $chainId = $request->get('chain_id');

        $query = PendingSweep::with('chain:chain_id,name')
            ->where('status', $status);

        if ($chainId) {
            $query->forChain($chainId);
        }

        $sweeps = $query->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $sweeps,
        ]);
    }

    public function sweepLogs(Request $request): JsonResponse
    {
        $limit = min($request->get('limit', 50), 100);
        $chainId = $request->get('chain_id');

        $query = SweepLog::with('chain:chain_id,name');

        if ($chainId) {
            $query->forChain($chainId);
        }

        $logs = $query->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }

    public function processSweep(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'deposit_address' => 'required|string',
            'token_address' => 'required|string',
            'chain_id' => 'required|integer',
        ]);

        $result = $this->sweeperService->processSweep(
            $validated['deposit_address'],
            $validated['token_address'],
            $validated['chain_id']
        );

        return response()->json([
            'success' => $result,
            'message' => $result ? 'Sweep initiated successfully' : 'Sweep failed',
        ], $result ? 200 : 500);
    }
}
