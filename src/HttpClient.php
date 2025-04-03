<?php

declare(strict_types=1);

namespace Akashic;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use JsonException;
use Monolog\Logger;
use Psr\Http\Message\ResponseInterface;

use function file_get_contents;
use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

class HttpClient
{
    private $client;
    private $apVersion;
    private $apClient = 'php-sdk';
    private Logger $logger;

    public function __construct(
        Logger $logger
    ) {
        // Logger initialization
        $this->logger = $logger;

        $config          = json_decode(file_get_contents(__DIR__ . '/config.json'), true);
        $this->client    = new Client();
        $this->apVersion = $config['version'];
    }

    public function post(string $url, $payload)
    {
        try {
            $response = $this->client->post(
                $url,
                [
                    'json'    => $payload,
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Ap-Version'   => $this->apVersion,
                        'Ap-Client'    => $this->apClient,
                    ],
                ],
            );

            $this->checkApiWarning($response->getHeaders());

            return $this->handleResponse($response);
        } catch (RequestException $e) {
            $this->handleException($e);
        }
    }

    public function get(string $url)
    {
        try {
            $response = $this->client->get(
                $url,
                [
                    'headers' => [
                        'Ap-Version' => $this->apVersion,
                        'Ap-Client'  => $this->apClient,
                    ],
                ],
            );

            $this->checkApiWarning($response->getHeaders());

            return $this->handleResponse($response);
        } catch (RequestException $e) {
            $this->handleException($e);
        }
    }

    /**
     * @throws Exception if the response status code is 4xx or 5xx
     * @throws JsonException if the response body is not a valid JSON string
     */
    private function handleResponse(ResponseInterface $response)
    {
        $statusCode = $response->getStatusCode();

        if ($statusCode >= 400) {
            $errorResponse = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
            throw new Exception($errorResponse['error'] . ': ' . $errorResponse['message']);
        }

        return [
            'data'   => json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR),
            'status' => $statusCode,
        ];
    }

    private function handleException(RequestException $e)
    {
        $this->logger->error($e->getMessage());

        $response = $e->getResponse();
        if (! $response) {
            throw new Exception($e->getMessage());
        }

        $errorResponse = $response->getBody()->getContents();
        $this->logger->error($errorResponse);
        throw new Exception(
            $response->getStatusCode() . ': ' . $errorResponse
        );
    }

    private function checkApiWarning($headers)
    {
        if (isset($headers['Warning'])) {
            $this->logger->warning(json_encode($headers['Warning']));
        }
    }
}
