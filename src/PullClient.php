<?php

namespace App;

use App\Dtos\OcpiClientHeaders;
use App\Dtos\OcpiResponse;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class PullClient
{
    private const MAX_RETRIES = 100;

    public function __construct()
    {
        $this->client = new Client();
        $this->logger = new Logger();
    }

    public function getAllData(string $url, OcpiClientHeaders $headers): array
    {
        $results = [];
        $currentUrl = $url;
        $attempt = 0;

        while ($currentUrl !== null) {
            try {
                $response = $this->client->get($currentUrl, [
                    'headers' => $headers->toArray(),
                ]);

                $body = json_decode($response->getBody()->getContents(), true);
                $responseHeaders = $response->getHeaders();

                $ocpiResponse = OcpiResponse::fromHttpResponse($body, $responseHeaders);
                $results[] = $ocpiResponse;

                $nextUrl = substr($ocpiResponse->linkHeader, 1, strpos($ocpiResponse->linkHeader, '>') + 1);
            } catch (GuzzleException $e) {
                if ($attempt >= self::MAX_RETRIES) {
                    throw $e;
                }
                $this->logger->info("Retrying after " . self::MAX_RETRIES . " times");
                $attempt++;
                usleep(random_int(0, (int) (250_000 * (2 ** $attempt))));
                continue;
            }
        }

        return $results;
    }
}
