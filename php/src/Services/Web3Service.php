<?php

namespace Multicoin\TokenSweeper\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;

class Web3Service
{
    protected Client $client;
    protected string $rpcUrl;
    protected int $chainId;

    public function __construct(string $rpcUrl, int $chainId)
    {
        $this->client = new Client(['timeout' => 30]);
        $this->rpcUrl = $rpcUrl;
        $this->chainId = $chainId;
    }

    public function call(string $method, array $params = []): mixed
    {
        $response = $this->client->post($this->rpcUrl, [
            'json' => [
                'jsonrpc' => '2.0',
                'method' => $method,
                'params' => $params,
                'id' => time(),
            ],
        ]);

        $result = json_decode($response->getBody()->getContents(), true);

        if (isset($result['error'])) {
            throw new \Exception("RPC Error: " . json_encode($result['error']));
        }

        return $result['result'] ?? null;
    }

    public function getBalance(string $address): string
    {
        $balance = $this->call('eth_getBalance', [$address, 'latest']);
        if (!is_string($balance) || !preg_match('/^0x[0-9a-fA-F]*$/', $balance)) {
            throw new \RuntimeException('RPC returned an invalid balance');
        }

        return $balance;
    }

    public function getTransactionCount(string $address, string $block = 'pending'): string
    {
        $count = $this->call('eth_getTransactionCount', [$address, $block]);
        if (!is_string($count) || !preg_match('/^0x[0-9a-fA-F]*$/', $count)) {
            throw new \RuntimeException('RPC returned an invalid transaction count');
        }

        return $count;
    }

    public function sendRawTransaction(string $signedTx): string
    {
        $hash = $this->call('eth_sendRawTransaction', [$signedTx]);
        if (!is_string($hash) || !preg_match('/^0x[0-9a-fA-F]{64}$/', $hash)) {
            throw new \RuntimeException('RPC returned an invalid transaction hash');
        }

        return $hash;
    }

    public function getTransactionReceipt(string $txHash): ?array
    {
        return $this->call('eth_getTransactionReceipt', [$txHash]);
    }

    public function gasPrice(): string
    {
        $cacheKey = "gas_price_{$this->chainId}";

        return Cache::remember($cacheKey, 10, function () {
            $price = $this->call('eth_gasPrice', []);
            if (!is_string($price) || !preg_match('/^0x[0-9a-fA-F]+$/', $price)) {
                throw new \RuntimeException('RPC returned an invalid gas price');
            }

            return $price;
        });
    }

    public function estimateGas(array $transaction): string
    {
        return $this->call('eth_estimateGas', [$transaction]);
    }

    public function callContract(string $to, string $data): string
    {
        $result = $this->call('eth_call', [
            ['to' => $to, 'data' => $data],
            'latest',
        ]);

        if (!is_string($result)) {
            throw new \RuntimeException('RPC returned an invalid call result');
        }

        return $result;
    }

    public function getBlockNumber(): string
    {
        return $this->call('eth_blockNumber', []);
    }

    public function getLogs(array $params): array
    {
        return $this->call('eth_getLogs', [$params]) ?? [];
    }

    public function waitForConfirmation(string $txHash, int $maxAttempts = 60): bool
    {
        for ($i = 0; $i < $maxAttempts; $i++) {
            sleep(2);

            $receipt = $this->getTransactionReceipt($txHash);

            if ($receipt && isset($receipt['status'])) {
                return $receipt['status'] === '0x1';
            }
        }

        throw new \Exception("Transaction confirmation timeout: $txHash");
    }
}
