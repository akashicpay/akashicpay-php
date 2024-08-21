<?php

namespace Akashic;

use Elliptic\EC;

class KeyPair
{
    private $type;
    private $handler;

    public function __construct(string $type = 'rsa', string $pem = null)
    {
        $this->type = $type;

        if ($pem) {
            $this->initializeHandler($pem);
        }
    }

    private function initializeHandler(string $pem): void
    {
        switch ($this->type) {
            case 'rsa':
            case 'bitcoin':
            case 'ethereum':
            case 'secp256k1':
                if (strpos($pem, '0x') === 0) {
                    // Raw Hex based key
                    $hexKey = substr($pem, 2);
                    if (strlen($pem) > 66) {
                        // Public key handling
                        $this->createHandler(
                            '',
                            $this->encodeECPublicKey($hexKey)
                        );
                    } else {
                        // Private key handling
                        $this->createHandler(
                            $this->encodeECPrivateKey($hexKey, ''),
                            ''
                        );
                    }
                } else {
                    // Original PEM format handling
                    if (strpos($pem, 'PRIVATE') === false) {
                        $this->createHandler('', $pem);
                    } else {
                        $this->createHandler($pem);
                    }
                }
                break;
            default:
                throw new \Exception("Unknown / unset key type: {$this->type}");
        }
    }

    private function createHandler(string $prv, string $pub = ''): void
    {
        $this->handler = [
            'pub' => ['pkcs8pem' => $pub],
            'prv' => ['pkcs8pem' => $prv]
        ];
    }

    private function encodeECPublicKey(string $key): string
    {
        // Encoding logic for EC Public Key
        // Assuming you have a function to encode EC Public Key
        return $key; // Placeholder
    }

    private function encodeECPrivateKey(string $key, string $pubKey): string
    {
        // Encoding logic for EC Private Key
        // Assuming you have a function to encode EC Private Key
        return $key; // Placeholder
    }

    public function sign($rawData, string $encoding = 'base64'): string
    {
        if (empty($this->handler['prv']['pkcs8pem'])) {
            throw new \Exception("Cannot sign without a private key");
        }
        $data = self::getString($rawData);

        $privateKey = $this->handler['prv']['pkcs8pem'];
        $signature = '';

        switch ($this->type) {
            case 'rsa':
                openssl_sign($data, $signature, $privateKey, OPENSSL_ALGO_SHA256);
                break;
            case 'secp256k1':
                $ec = new EC('secp256k1');
                $key = $ec->keyFromPrivate($privateKey, 'hex');
                $signature = $key->sign(hash('sha256', $data));
                $signature = $signature->toDER('hex');
                break;
            default:
                throw new \Exception("Unsupported key type for signing: {$this->type}");
        }

        return $encoding === 'base64' ? base64_encode(hex2bin($signature)) : $signature;
    }

    public function verify(string $data, string $signature, string $encoding = 'base64'): bool
    {
        if (!isset($this->handler['pub']['pkcs8pem'])) {
            throw new \Exception("Cannot verify without a public key");
        }

        $publicKey = $this->handler['pub']['pkcs8pem'];
        $signature = $encoding === 'base64' ? bin2hex(base64_decode($signature)) : $signature;
        $verified = false;

        switch ($this->type) {
            case 'rsa':
                $verified = openssl_verify($data, hex2bin($signature), $publicKey, OPENSSL_ALGO_SHA256) === 1;
                break;
            case 'secp256k1':
                $ec = new EC('secp256k1');
                $key = $ec->keyFromPublic($publicKey, 'hex');
                $verified = $key->verify(hash('sha256', $data), $signature);
                break;
            default:
                throw new \Exception("Unsupported key type for verification: {$this->type}");
        }

        return $verified;
    }

    /**
     * Makes sure the data is a string
     *
     * @param mixed $data
     * @return string
     * @throws \Exception
     */
    private function getString($data): string
    {
        // Check if data is a Buffer (binary string in PHP)
        if (is_string($data) && preg_match('//u', $data) === false) {
            return bin2hex($data); // Return binary data as a hex string
        }

        // Check if data is an object or array and convert to JSON string
        if (is_array($data) || is_object($data)) {
            return json_encode($data);
        }

        // Check if data is a string and return it
        if (is_string($data)) {
            return $data;
        }

        // Handle other data types as needed (e.g., integers, floats, etc.)
        // In this case, we will throw an exception for unsupported types
        throw new \Exception("Unsupported data type");
    }

    public function generate(int $bits = 2048): array
    {
        $keyPair = [];

        switch ($this->type) {
            case 'rsa':
                $config = [
                    "private_key_bits" => $bits,
                    "private_key_type" => OPENSSL_KEYTYPE_RSA,
                ];
                $res = openssl_pkey_new($config);
                openssl_pkey_export($res, $privateKey);
                $keyDetails = openssl_pkey_get_details($res);
                $publicKey = $keyDetails['key'];

                $keyPair = [
                    'pub' => ['pkcs8pem' => $publicKey],
                    'prv' => ['pkcs8pem' => $privateKey]
                ];
                break;

            case 'secp256k1':
                $ec = new EC('secp256k1');
                $key = $ec->genKeyPair();
                $privateKey = $key->getPrivate('hex');
                $publicKey = $key->getPublic('hex');

                $keyPair = [
                    'pub' => ['pkcs8pem' => $publicKey],
                    'prv' => ['pkcs8pem' => $privateKey]
                ];
                break;

            default:
                throw new \Exception("Unknown key type: {$this->type}");
        }

        return $keyPair;
    }
}
