<?php

namespace Akashic;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class HttpClient
{
    private $client;

    public function __construct()
    {
        $this->client = new Client();
    }

    /** @api getting false-positives here */
    public function post(string $url, $payload)
    {
        try {
            $response = $this->client->post(
                $url,
                [
                'json' => $payload,
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                ]
            );

            return $this->handleResponse($response);
        } catch (RequestException $e) {
            $this->handleException($e);
        }
    }

    /** @api getting false-positives here */
    public function get(string $url)
    {
        try {
            $response = $this->client->get($url);

            return $this->handleResponse($response);
        } catch (RequestException $e) {
            $this->handleException($e);
        }
    }

    private function handleResponse($response)
    {
        $statusCode = $response->getStatusCode();

        if ($statusCode >= 400) {
            $errorResponse = json_decode($response->getBody(), true);
            throw new Exception($errorResponse['error'] . ': ' . $errorResponse['message']);
        }

        return [
            'data' => json_decode($response->getBody(), true),
            'status' => $statusCode,
        ];
    }

    private function handleException(RequestException $e)
    {
        if ($e->hasResponse()) {
            $response = $e->getResponse();
            $errorResponse = json_decode($response->getBody(), true);
            throw new Exception($errorResponse['error'] . ': ' . $errorResponse['message']);
        } else {
            throw new Exception($e->getMessage());
        }
    }
}
