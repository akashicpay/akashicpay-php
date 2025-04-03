<?php

declare(strict_types=1);

namespace Akashic;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use JsonException;
use Psr\Http\Message\ResponseInterface;

use function file_get_contents;
use function json_decode;

use const JSON_THROW_ON_ERROR;

class HttpClient
{
    private $client;
    private $apVersion;
    private $apClient = 'php-sdk';

    public function __construct()
    {
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
        } catch (RequestException $e) {
            $this->handleException($e);
        }
        return $this->handleResponse($response);
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
        $response = $e->getResponse();
        if (! $response) {
            throw new Exception($e->getMessage());
        }

        $errorResponse = $response->getBody()->getContents();
        throw new Exception(
            $response->getStatusCode() . ': ' . $errorResponse
        );
    }
}
