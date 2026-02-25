<?php

namespace Tests;

use App\Dtos\OcpiClientHeaders;
use App\Dtos\TokenDto;
use App\Dtos\TokenType;
use App\Dtos\WhitelistType;
use App\PullClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class PullClientIntegrationTest extends TestCase
{
    private const TEST_TOKEN = 'test-token-123';
    private OcpiClientHeaders $headers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->headers = new OcpiClientHeaders(token: self::TEST_TOKEN);
    }

    public function testShouldSuccessfullyDownloadTokensFromSinglePage(): void
    {
        // GIVEN - Mock server returns a single page of tokens without Link header
        $mockHandler = new MockHandler([
            new Response(200, [
                'Content-Type' => 'application/json',
                'X-Total-Count' => '2',
                'X-Limit' => '2',
            ], json_encode([
                'data' => [
                    [
                        'uid' => 'TOKEN-001',
                        'type' => 'RFID',
                        'auth_id' => 'AUTH-001',
                        'issuer' => 'TEST-ISSUER',
                        'valid' => true,
                        'whitelist' => 'ALWAYS',
                        'last_updated' => '2024-01-15T10:00:00Z',
                    ],
                    [
                        'uid' => 'TOKEN-002',
                        'type' => 'APP_USER',
                        'auth_id' => 'AUTH-002',
                        'issuer' => 'TEST-ISSUER',
                        'valid' => true,
                        'whitelist' => 'ALLOWED',
                        'last_updated' => '2024-01-15T11:00:00Z',
                    ],
                ],
                'status_code' => 1000,
                'status_message' => 'Success',
                'timestamp' => '2024-01-15T12:00:00Z',
            ])),
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $httpClient = new Client(['handler' => $handlerStack]);
        $pullClient = new PullClient($httpClient);

        // WHEN - Pull all data
        $results = $pullClient->getAllData(
            url: 'http://localhost:9090/tokens',
            headers: $this->headers
        );

        // THEN - Should receive exactly one page
        $this->assertCount(1, $results);

        $response = $results[0];
        $responseData = $response->data;
        $this->assertCount(2, $responseData);

        // Validate first token
        $token1 = TokenDto::fromArray($responseData[0]);
        $this->assertEquals('TOKEN-001', $token1->uid);
        $this->assertEquals(TokenType::RFID, $token1->type);
        $this->assertEquals('AUTH-001', $token1->authId);
        $this->assertTrue($token1->valid);

        // Validate second token
        $token2 = TokenDto::fromArray($responseData[1]);
        $this->assertEquals('TOKEN-002', $token2->uid);
        $this->assertEquals(TokenType::APP_USER, $token2->type);
        $this->assertEquals('AUTH-002', $token2->authId);
    }

    public function testShouldSuccessfullyDownloadTokensFromTwoPagesWithLinkHeader(): void
    {
        // GIVEN - Mock server returns two pages with pagination
        $mockHandler = new MockHandler([
            // First page with Link header pointing to second page
            new Response(200, [
                'Content-Type' => 'application/json',
                'X-Total-Count' => '4',
                'X-Limit' => '2',
                'Link' => '<http://localhost:9090/tokens?offset=2>',
            ], json_encode([
                'data' => [
                    [
                        'uid' => 'TOKEN-001',
                        'type' => 'RFID',
                        'auth_id' => 'AUTH-001',
                        'issuer' => 'TEST-ISSUER',
                        'valid' => true,
                        'whitelist' => 'ALWAYS',
                        'last_updated' => '2024-01-15T10:00:00Z',
                    ],
                    [
                        'uid' => 'TOKEN-002',
                        'type' => 'APP_USER',
                        'auth_id' => 'AUTH-002',
                        'issuer' => 'TEST-ISSUER',
                        'valid' => true,
                        'whitelist' => 'ALLOWED',
                        'last_updated' => '2024-01-15T11:00:00Z',
                    ],
                ],
                'status_code' => 1000,
                'status_message' => 'Success',
                'timestamp' => '2024-01-15T12:00:00Z',
            ])),
            // Second page without Link header (last page)
            new Response(200, [
                'Content-Type' => 'application/json',
                'X-Total-Count' => '4',
                'X-Limit' => '2',
            ], json_encode([
                'data' => [
                    [
                        'uid' => 'TOKEN-003',
                        'type' => 'RFID',
                        'auth_id' => 'AUTH-003',
                        'issuer' => 'TEST-ISSUER',
                        'valid' => true,
                        'whitelist' => 'ALLOWED_OFFLINE',
                        'last_updated' => '2024-01-15T12:00:00Z',
                    ],
                    [
                        'uid' => 'TOKEN-004',
                        'type' => 'OTHER',
                        'auth_id' => 'AUTH-004',
                        'issuer' => 'TEST-ISSUER',
                        'valid' => false,
                        'whitelist' => 'NEVER',
                        'last_updated' => '2024-01-15T13:00:00Z',
                    ],
                ],
                'status_code' => 1000,
                'status_message' => 'Success',
                'timestamp' => '2024-01-15T14:00:00Z',
            ])),
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $httpClient = new Client(['handler' => $handlerStack]);
        $pullClient = new PullClient($httpClient);

        // WHEN - Pull all data
        $results = $pullClient->getAllData(
            url: 'http://localhost:9090/tokens',
            headers: $this->headers,
            dtoClass: TokenDto::class
        );

        // THEN - Should receive two pages
        $this->assertCount(2, $results);

        // First page validation
        $firstPageData = $results[0]->data;
        $this->assertCount(2, $firstPageData);

        $firstToken1 = TokenDto::fromArray($firstPageData[0]);
        $this->assertEquals('TOKEN-001', $firstToken1->uid);

        $firstToken2 = TokenDto::fromArray($firstPageData[1]);
        $this->assertEquals('TOKEN-002', $firstToken2->uid);

        // Second page validation
        $secondPageData = $results[1]->data;
        $this->assertCount(2, $secondPageData);

        $secondToken1 = TokenDto::fromArray($secondPageData[0]);
        $this->assertEquals('TOKEN-003', $secondToken1->uid);
        $this->assertEquals(WhitelistType::ALLOWED_OFFLINE, $secondToken1->whitelist);

        $secondToken2 = TokenDto::fromArray($secondPageData[1]);
        $this->assertEquals('TOKEN-004', $secondToken2->uid);
        $this->assertFalse($secondToken2->valid);
        $this->assertEquals(WhitelistType::NEVER, $secondToken2->whitelist);
    }

    public function testShouldSuccessfullyRetryAndRecoverFromTransientErrors(): void
    {
        // GIVEN - Mock server fails first time then succeeds
        $mockHandler = new MockHandler([
            // First attempt - failure
            new Response(500, [], 'Internal Server Error'),
            // Second attempt - success
            new Response(200, [
                'Content-Type' => 'application/json',
                'X-Total-Count' => '1',
                'X-Limit' => '1',
            ], json_encode([
                'data' => [
                    [
                        'uid' => 'TOKEN-RECOVERED',
                        'type' => 'RFID',
                        'auth_id' => 'AUTH-RECOVERED',
                        'issuer' => 'TEST-ISSUER',
                        'valid' => true,
                        'whitelist' => 'ALWAYS',
                        'last_updated' => '2024-01-15T10:00:00Z',
                    ],
                ],
                'status_code' => 1000,
                'status_message' => 'Success',
                'timestamp' => '2024-01-15T12:00:00Z',
            ])),
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $httpClient = new Client(['handler' => $handlerStack]);
        $pullClient = new PullClient($httpClient);

        // WHEN - Pull all data
        $results = $pullClient->getAllData(
            url: 'http://localhost:9090/tokens',
            headers: $this->headers,
            dtoClass: TokenDto::class
        );

        // THEN - Should eventually succeed after retry
        $this->assertCount(1, $results);

        $responseData = $results[0]->data;
        $this->assertCount(1, $responseData);

        $token = TokenDto::fromArray($responseData[0]);
        $this->assertEquals('TOKEN-RECOVERED', $token->uid);
    }
}
