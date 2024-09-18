<?php

namespace Akashic\Tests\OTK;

use PHPUnit\Framework\TestCase;
use Akashic\OTK\ECKeyHandler;

class ECKeyHandlerTest extends TestCase
{
    private $privateKeyPem = <<<EOD
-----BEGIN EC PRIVATE KEY-----
MHcCAQEEIA0cq9UjFgWwH+P5TZ4NYm0Q0Plc1jjOq9t9slBGZyy0oAoGCCqGSM49
AwEHoUQDQgAEkHknpGQhTz5jZC0HRxQcUhAWGToFgN1W7A0z5k2u5z9aMkON5J3
J2A2AozpVCEU4kj7Tw8GsZb6PkjG23ALlP2Q==
-----END EC PRIVATE KEY-----
EOD;

    private $publicKeyDer = '042404a8b2d62124c2858b94913a2df53e0d3b324f57e0cd' .
    'a47c1945db16a8cfc6c18c8b9230eb71f1c0c0cf97eb3e82c32694c08ef8f52fbeab3a65d4';

    public function testDecodeECPrivateKey()
    {
        $decodedPrivateKey = ECKeyHandler::decodeECPrivateKey($this->privateKeyPem);
        $this->assertNotEmpty($decodedPrivateKey, "Decoded EC private key should not be empty.");
    }

    public function testEncodeECPublicKey()
    {
        $publicKey = hex2bin($this->publicKeyDer);
        $encodedPublicKey = ECKeyHandler::encodeECPublicKey($publicKey);

        $this->assertStringContainsString(
            '-----BEGIN PUBLIC KEY-----',
            $encodedPublicKey,
            "Encoded EC public key should contain PEM header."
        );
        $this->assertStringContainsString(
            '-----END PUBLIC KEY-----',
            $encodedPublicKey,
            "Encoded EC public key should contain PEM footer."
        );
    }
}
