<?php
namespace Akashic;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class HttpClient {
    private $client;

    public function __construct() {
        $this->client = new Client();
    }

    public function post($url, $data, $headers = []) {
        try {
            $response = $this->client->post($url, [
                'headers' => $headers,
                'json' => $data
            ]);
            return $response->getBody()->getContents();
        } catch (RequestException $e) {
            return $e->getMessage();
        }
    }

    public function get($url, $headers = []) {
        try {
            $response = $this->client->get($url, [
                'headers' => $headers
            ]);
            return $response->getBody()->getContents();
        } catch (RequestException $e) {
            return $e->getMessage();
        }
    }
}
