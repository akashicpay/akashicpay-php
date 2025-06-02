<?php

declare(strict_types=1);

namespace Akashic\Tests\OTK;

use Akashic\Constants\KeyType;
use Akashic\OTK\Otk;
use PHPUnit\Framework\TestCase;

class OtkTest extends TestCase
{
    private $privateKeyHex = '0x1c72abd48c85816c07f8fe539e0d626d10d0f95cd638ceabdb7db25046672cb4';
    private $privateKeyPem = <<<EOD
-----BEGIN EC PRIVATE KEY-----
MHcCAQEEIA0cq9UjFgWwH+P5TZ4NYm0Q0Plc1jjOq9t9slBGZyy0oAoGCCqGSM49
AwEHoUQDQgAEkHknpGQhTz5jZC0HRxQcUhAWGToFgN1W7A0z5k2u5z9aMkON5J3
J2A2AozpVCEU4kj7Tw8GsZb6PkjG23ALlP2Q==
-----END EC PRIVATE KEY-----
EOD;

    public function testRestoreOtkFromPhrase()
    {
        // This test assumes a functional restoreBIP39Key method in KeyHandler
        $phrase = 'test phrase for BIP39 key restoration';
        $result = Otk::restoreOtkFromPhrase($phrase);
        $this->assertNotNull($result, "Restored OTK from phrase should not be null.");
    }

    public function testRestoreOtkFromKeypairWithHex()
    {
        $result = Otk::restoreOtkFromKeypair($this->privateKeyHex);
        $this->assertArrayHasKey('key', $result, "Result should have a 'key' array.");
        $this->assertArrayHasKey('prv', $result['key'], "Key array should have 'prv'.");
        $this->assertArrayHasKey('pub', $result['key'], "Key array should have 'pub'.");
        $this->assertEquals(KeyType::ELLIPTIC_CURVE, $result['type'], "Key type should be 'ELLIPTIC_CURVE'.");
    }

    public function testRestoreOtkFromKeypairWithPem()
    {
        $result = Otk::restoreOtkFromKeypair($this->privateKeyPem);
        $this->assertArrayHasKey('key', $result, "Result should have a 'key' array.");
        $this->assertArrayHasKey('prv', $result['key'], "Key array should have 'prv'.");
        $this->assertArrayHasKey('pub', $result['key'], "Key array should have 'pub'.");
        $this->assertEquals(KeyType::ELLIPTIC_CURVE, $result['type'], "Key type should be 'ELLIPTIC_CURVE'.");
    }
}
