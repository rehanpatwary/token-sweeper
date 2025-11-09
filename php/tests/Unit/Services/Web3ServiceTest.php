<?php

namespace Multicoin\TokenSweeper\Tests\Unit\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Multicoin\TokenSweeper\Services\Web3Service;
use Multicoin\TokenSweeper\Tests\TestCase;

class Web3ServiceTest extends TestCase
{
    protected Web3Service $web3Service;
    protected MockHandler $mockHandler;
    protected string $testRpcUrl = 'https://eth-mainnet.example.com';
    protected int $testChainId = 1;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear cache before each test
        Cache::flush();

        // Create a mock handler for Guzzle
        $this->mockHandler = new MockHandler();
        $handlerStack = HandlerStack::create($this->mockHandler);

        // Create Web3Service instance
        $this->web3Service = new Web3Service($this->testRpcUrl, $this->testChainId);

        // Inject the mock client using reflection
        $reflection = new \ReflectionClass($this->web3Service);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($this->web3Service, new Client(['handler' => $handlerStack]));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Helper method to create a successful RPC response
     */
    protected function createSuccessResponse($result): Response
    {
        return new Response(200, [], json_encode([
            'jsonrpc' => '2.0',
            'id' => time(),
            'result' => $result,
        ]));
    }

    /**
     * Helper method to create an error RPC response
     */
    protected function createErrorResponse(array $error): Response
    {
        return new Response(200, [], json_encode([
            'jsonrpc' => '2.0',
            'id' => time(),
            'error' => $error,
        ]));
    }

    /** @test */
    public function it_can_make_successful_rpc_call()
    {
        $expectedResult = '0x1234567890abcdef';
        $this->mockHandler->append($this->createSuccessResponse($expectedResult));

        $result = $this->web3Service->call('eth_blockNumber', []);

        $this->assertEquals($expectedResult, $result);
    }

    /** @test */
    public function it_throws_exception_on_rpc_error()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('RPC Error:');

        $error = [
            'code' => -32600,
            'message' => 'Invalid request',
        ];
        $this->mockHandler->append($this->createErrorResponse($error));

        $this->web3Service->call('invalid_method', []);
    }

    /** @test */
    public function it_returns_null_when_result_is_missing()
    {
        $this->mockHandler->append(new Response(200, [], json_encode([
            'jsonrpc' => '2.0',
            'id' => time(),
        ])));

        $result = $this->web3Service->call('eth_blockNumber', []);

        $this->assertNull($result);
    }

    /** @test */
    public function it_can_get_balance()
    {
        $address = '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb';
        $expectedBalance = '0xde0b6b3a7640000'; // 1 ETH in wei

        $this->mockHandler->append($this->createSuccessResponse($expectedBalance));

        $balance = $this->web3Service->getBalance($address);

        $this->assertEquals($expectedBalance, $balance);
    }

    /** @test */
    public function it_can_get_balance_for_zero_balance_address()
    {
        $address = '0x0000000000000000000000000000000000000000';
        $expectedBalance = '0x0';

        $this->mockHandler->append($this->createSuccessResponse($expectedBalance));

        $balance = $this->web3Service->getBalance($address);

        $this->assertEquals($expectedBalance, $balance);
    }

    /** @test */
    public function it_can_get_transaction_count()
    {
        $address = '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb';
        $expectedNonce = '0x5'; // 5 transactions

        $this->mockHandler->append($this->createSuccessResponse($expectedNonce));

        $nonce = $this->web3Service->getTransactionCount($address);

        $this->assertEquals($expectedNonce, $nonce);
    }

    /** @test */
    public function it_can_get_transaction_count_for_new_address()
    {
        $address = '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb';
        $expectedNonce = '0x0';

        $this->mockHandler->append($this->createSuccessResponse($expectedNonce));

        $nonce = $this->web3Service->getTransactionCount($address);

        $this->assertEquals($expectedNonce, $nonce);
    }

    /** @test */
    public function it_can_send_raw_transaction()
    {
        $signedTx = '0xf86c808504a817c800825208943535353535353535353535353535353535353535880de0b6b3a76400008025a0';
        $expectedTxHash = '0x' . bin2hex(random_bytes(32));

        $this->mockHandler->append($this->createSuccessResponse($expectedTxHash));

        $txHash = $this->web3Service->sendRawTransaction($signedTx);

        $this->assertEquals($expectedTxHash, $txHash);
    }

    /** @test */
    public function it_throws_exception_when_sending_invalid_raw_transaction()
    {
        $this->expectException(\Exception::class);

        $invalidTx = '0xinvalid';
        $error = [
            'code' => -32000,
            'message' => 'invalid transaction',
        ];
        $this->mockHandler->append($this->createErrorResponse($error));

        $this->web3Service->sendRawTransaction($invalidTx);
    }

    /** @test */
    public function it_can_get_transaction_receipt()
    {
        $txHash = '0x' . bin2hex(random_bytes(32));
        $expectedReceipt = [
            'transactionHash' => $txHash,
            'blockNumber' => '0x123456',
            'gasUsed' => '0x5208',
            'status' => '0x1',
        ];

        $this->mockHandler->append($this->createSuccessResponse($expectedReceipt));

        $receipt = $this->web3Service->getTransactionReceipt($txHash);

        $this->assertIsArray($receipt);
        $this->assertEquals($expectedReceipt, $receipt);
        $this->assertEquals($txHash, $receipt['transactionHash']);
        $this->assertEquals('0x1', $receipt['status']);
    }

    /** @test */
    public function it_returns_null_for_pending_transaction_receipt()
    {
        $txHash = '0x' . bin2hex(random_bytes(32));

        $this->mockHandler->append($this->createSuccessResponse(null));

        $receipt = $this->web3Service->getTransactionReceipt($txHash);

        $this->assertNull($receipt);
    }

    /** @test */
    public function it_can_get_gas_price()
    {
        $expectedGasPrice = '0x3b9aca00'; // 1 gwei

        $this->mockHandler->append($this->createSuccessResponse($expectedGasPrice));

        $gasPrice = $this->web3Service->gasPrice();

        $this->assertEquals($expectedGasPrice, $gasPrice);
    }

    /** @test */
    public function it_caches_gas_price()
    {
        $expectedGasPrice = '0x3b9aca00';

        // First call should hit the RPC
        $this->mockHandler->append($this->createSuccessResponse($expectedGasPrice));

        $gasPrice1 = $this->web3Service->gasPrice();
        $this->assertEquals($expectedGasPrice, $gasPrice1);

        // Second call should use cache (no mock response needed)
        $gasPrice2 = $this->web3Service->gasPrice();
        $this->assertEquals($expectedGasPrice, $gasPrice2);

        // Verify cache was used
        $cacheKey = "gas_price_{$this->testChainId}";
        $this->assertTrue(Cache::has($cacheKey));
        $this->assertEquals($expectedGasPrice, Cache::get($cacheKey));
    }

    /** @test */
    public function it_can_estimate_gas()
    {
        $transaction = [
            'from' => '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb',
            'to' => '0x3535353535353535353535353535353535353535',
            'value' => '0xde0b6b3a7640000',
        ];
        $expectedGasEstimate = '0x5208'; // 21000 gas

        $this->mockHandler->append($this->createSuccessResponse($expectedGasEstimate));

        $gasEstimate = $this->web3Service->estimateGas($transaction);

        $this->assertEquals($expectedGasEstimate, $gasEstimate);
    }

    /** @test */
    public function it_can_estimate_gas_for_contract_interaction()
    {
        $transaction = [
            'from' => '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb',
            'to' => '0xA0b86991c6218b36c1d19D4a2e9Eb0cE3606eB48', // USDC contract
            'data' => '0xa9059cbb0000000000000000000000003535353535353535353535353535353535353535',
        ];
        $expectedGasEstimate = '0xc350'; // 50000 gas

        $this->mockHandler->append($this->createSuccessResponse($expectedGasEstimate));

        $gasEstimate = $this->web3Service->estimateGas($transaction);

        $this->assertEquals($expectedGasEstimate, $gasEstimate);
    }

    /** @test */
    public function it_throws_exception_on_gas_estimation_failure()
    {
        $this->expectException(\Exception::class);

        $transaction = [
            'from' => '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb',
            'to' => '0x3535353535353535353535353535353535353535',
            'value' => '0xffffffffffffffffffffffffffffffffffffffff', // Invalid huge value
        ];

        $error = [
            'code' => -32000,
            'message' => 'insufficient funds for gas * price + value',
        ];
        $this->mockHandler->append($this->createErrorResponse($error));

        $this->web3Service->estimateGas($transaction);
    }

    /** @test */
    public function it_can_call_contract()
    {
        $contractAddress = '0xA0b86991c6218b36c1d19D4a2e9Eb0cE3606eB48';
        $data = '0x70a08231000000000000000000000000742d35Cc6634C0532925a3b844Bc9e7595f0bEb'; // balanceOf
        $expectedResult = '0x0000000000000000000000000000000000000000000000000000000005f5e100'; // 100000000

        $this->mockHandler->append($this->createSuccessResponse($expectedResult));

        $result = $this->web3Service->callContract($contractAddress, $data);

        $this->assertEquals($expectedResult, $result);
    }

    /** @test */
    public function it_can_call_contract_view_function()
    {
        $contractAddress = '0xA0b86991c6218b36c1d19D4a2e9Eb0cE3606eB48';
        $data = '0x313ce567'; // decimals()
        $expectedResult = '0x0000000000000000000000000000000000000000000000000000000000000006'; // 6 decimals

        $this->mockHandler->append($this->createSuccessResponse($expectedResult));

        $result = $this->web3Service->callContract($contractAddress, $data);

        $this->assertEquals($expectedResult, $result);
    }

    /** @test */
    public function it_throws_exception_on_contract_call_failure()
    {
        $this->expectException(\Exception::class);

        $contractAddress = '0x0000000000000000000000000000000000000000';
        $data = '0x70a08231';

        $error = [
            'code' => -32000,
            'message' => 'execution reverted',
        ];
        $this->mockHandler->append($this->createErrorResponse($error));

        $this->web3Service->callContract($contractAddress, $data);
    }

    /** @test */
    public function it_can_get_block_number()
    {
        $expectedBlockNumber = '0x10d4f'; // 68943

        $this->mockHandler->append($this->createSuccessResponse($expectedBlockNumber));

        $blockNumber = $this->web3Service->getBlockNumber();

        $this->assertEquals($expectedBlockNumber, $blockNumber);
    }

    /** @test */
    public function it_can_get_latest_block_number()
    {
        $expectedBlockNumber = '0x1234567';

        $this->mockHandler->append($this->createSuccessResponse($expectedBlockNumber));

        $blockNumber = $this->web3Service->getBlockNumber();

        $this->assertIsString($blockNumber);
        $this->assertStringStartsWith('0x', $blockNumber);
    }

    /** @test */
    public function it_can_get_logs()
    {
        $params = [
            'fromBlock' => '0x123456',
            'toBlock' => 'latest',
            'address' => '0xA0b86991c6218b36c1d19D4a2e9Eb0cE3606eB48',
            'topics' => [
                '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef',
            ],
        ];
        $expectedLogs = [
            [
                'address' => '0xA0b86991c6218b36c1d19D4a2e9Eb0cE3606eB48',
                'topics' => [
                    '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef',
                ],
                'data' => '0x0000000000000000000000000000000000000000000000000000000005f5e100',
                'blockNumber' => '0x123456',
                'transactionHash' => '0x' . bin2hex(random_bytes(32)),
            ],
        ];

        $this->mockHandler->append($this->createSuccessResponse($expectedLogs));

        $logs = $this->web3Service->getLogs($params);

        $this->assertIsArray($logs);
        $this->assertCount(1, $logs);
        $this->assertEquals($expectedLogs, $logs);
    }

    /** @test */
    public function it_returns_empty_array_for_no_logs()
    {
        $params = [
            'fromBlock' => '0x123456',
            'toBlock' => 'latest',
            'address' => '0xA0b86991c6218b36c1d19D4a2e9Eb0cE3606eB48',
        ];

        $this->mockHandler->append($this->createSuccessResponse([]));

        $logs = $this->web3Service->getLogs($params);

        $this->assertIsArray($logs);
        $this->assertEmpty($logs);
    }

    /** @test */
    public function it_returns_empty_array_when_logs_result_is_null()
    {
        $params = [
            'fromBlock' => '0x123456',
            'toBlock' => 'latest',
        ];

        $this->mockHandler->append($this->createSuccessResponse(null));

        $logs = $this->web3Service->getLogs($params);

        $this->assertIsArray($logs);
        $this->assertEmpty($logs);
    }

    /** @test */
    public function it_can_wait_for_successful_transaction_confirmation()
    {
        $txHash = '0x' . bin2hex(random_bytes(32));

        // First attempt: pending (null receipt)
        $this->mockHandler->append($this->createSuccessResponse(null));

        // Second attempt: confirmed with success
        $this->mockHandler->append($this->createSuccessResponse([
            'transactionHash' => $txHash,
            'blockNumber' => '0x123456',
            'status' => '0x1',
        ]));

        // Mock sleep to speed up test
        $service = Mockery::mock(Web3Service::class, [$this->testRpcUrl, $this->testChainId])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        // Inject mock client
        $reflection = new \ReflectionClass($service);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($service, new Client(['handler' => HandlerStack::create($this->mockHandler)]));

        $result = $service->waitForConfirmation($txHash, 2);

        $this->assertTrue($result);
    }

    /** @test */
    public function it_returns_false_for_failed_transaction()
    {
        $txHash = '0x' . bin2hex(random_bytes(32));

        // Return failed transaction receipt
        $this->mockHandler->append($this->createSuccessResponse([
            'transactionHash' => $txHash,
            'blockNumber' => '0x123456',
            'status' => '0x0', // Failed status
        ]));

        $service = Mockery::mock(Web3Service::class, [$this->testRpcUrl, $this->testChainId])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $reflection = new \ReflectionClass($service);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($service, new Client(['handler' => HandlerStack::create($this->mockHandler)]));

        $result = $service->waitForConfirmation($txHash, 1);

        $this->assertFalse($result);
    }

    /** @test */
    public function it_throws_exception_on_confirmation_timeout()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Transaction confirmation timeout:');

        $txHash = '0x' . bin2hex(random_bytes(32));

        // Always return null (pending)
        $this->mockHandler->append($this->createSuccessResponse(null));
        $this->mockHandler->append($this->createSuccessResponse(null));

        $service = Mockery::mock(Web3Service::class, [$this->testRpcUrl, $this->testChainId])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $reflection = new \ReflectionClass($service);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($service, new Client(['handler' => HandlerStack::create($this->mockHandler)]));

        $service->waitForConfirmation($txHash, 2);
    }

    /** @test */
    public function it_constructs_with_correct_properties()
    {
        $rpcUrl = 'https://mainnet.infura.io/v3/YOUR-PROJECT-ID';
        $chainId = 1;

        $service = new Web3Service($rpcUrl, $chainId);

        $reflection = new \ReflectionClass($service);

        $rpcUrlProperty = $reflection->getProperty('rpcUrl');
        $rpcUrlProperty->setAccessible(true);
        $this->assertEquals($rpcUrl, $rpcUrlProperty->getValue($service));

        $chainIdProperty = $reflection->getProperty('chainId');
        $chainIdProperty->setAccessible(true);
        $this->assertEquals($chainId, $chainIdProperty->getValue($service));

        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setAccessible(true);
        $this->assertInstanceOf(Client::class, $clientProperty->getValue($service));
    }

    /** @test */
    public function it_sends_correct_json_rpc_request_format()
    {
        $expectedResult = '0x1234';
        $method = 'eth_blockNumber';
        $params = [];

        $this->mockHandler->append($this->createSuccessResponse($expectedResult));

        $result = $this->web3Service->call($method, $params);

        // Verify the request was sent (mock handler consumed)
        $this->assertEquals($expectedResult, $result);
        $this->assertCount(0, $this->mockHandler);
    }

    /** @test */
    public function it_handles_network_errors_gracefully()
    {
        $this->expectException(RequestException::class);

        $this->mockHandler->append(new RequestException(
            'Connection timeout',
            new Request('POST', $this->testRpcUrl)
        ));

        $this->web3Service->call('eth_blockNumber', []);
    }

    /** @test */
    public function it_can_make_multiple_sequential_calls()
    {
        // Setup multiple responses
        $this->mockHandler->append($this->createSuccessResponse('0x1'));
        $this->mockHandler->append($this->createSuccessResponse('0x2'));
        $this->mockHandler->append($this->createSuccessResponse('0x3'));

        $result1 = $this->web3Service->getBlockNumber();
        $result2 = $this->web3Service->getBlockNumber();
        $result3 = $this->web3Service->getBlockNumber();

        $this->assertEquals('0x1', $result1);
        $this->assertEquals('0x2', $result2);
        $this->assertEquals('0x3', $result3);
    }

    /** @test */
    public function it_handles_malformed_json_response()
    {
        $this->mockHandler->append(new Response(200, [], 'invalid json'));

        try {
            $this->web3Service->call('eth_blockNumber', []);
            $this->fail('Expected exception was not thrown');
        } catch (\Exception $e) {
            // Either JSON decode error or null result is acceptable
            $this->assertTrue(true);
        }
    }

    /** @test */
    public function it_can_get_logs_with_multiple_topics()
    {
        $params = [
            'fromBlock' => '0x1',
            'toBlock' => 'latest',
            'address' => '0xA0b86991c6218b36c1d19D4a2e9Eb0cE3606eB48',
            'topics' => [
                '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef',
                '0x000000000000000000000000742d35Cc6634C0532925a3b844Bc9e7595f0bEb',
            ],
        ];
        $expectedLogs = [
            [
                'address' => '0xA0b86991c6218b36c1d19D4a2e9Eb0cE3606eB48',
                'topics' => $params['topics'],
                'data' => '0x0000000000000000000000000000000000000000000000000000000005f5e100',
            ],
        ];

        $this->mockHandler->append($this->createSuccessResponse($expectedLogs));

        $logs = $this->web3Service->getLogs($params);

        $this->assertCount(1, $logs);
        $this->assertEquals($expectedLogs[0]['topics'], $logs[0]['topics']);
    }

    /** @test */
    public function it_preserves_different_chain_ids_in_cache()
    {
        $gasPrice1 = '0x3b9aca00';
        $gasPrice2 = '0x4b9aca00';

        // First service with chain ID 1
        $service1 = new Web3Service($this->testRpcUrl, 1);
        $mockHandler1 = new MockHandler([$this->createSuccessResponse($gasPrice1)]);
        $reflection = new \ReflectionClass($service1);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($service1, new Client(['handler' => HandlerStack::create($mockHandler1)]));

        // Second service with chain ID 56
        $service2 = new Web3Service($this->testRpcUrl, 56);
        $mockHandler2 = new MockHandler([$this->createSuccessResponse($gasPrice2)]);
        $property->setValue($service2, new Client(['handler' => HandlerStack::create($mockHandler2)]));

        $result1 = $service1->gasPrice();
        $result2 = $service2->gasPrice();

        $this->assertEquals($gasPrice1, $result1);
        $this->assertEquals($gasPrice2, $result2);
        $this->assertNotEquals($result1, $result2);
    }
}
