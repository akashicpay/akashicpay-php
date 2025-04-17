<?php

declare(strict_types=1);

namespace Akashic\Tests\OTK;

use Akashic\OTK\KeyHandler;
use PHPUnit\Framework\TestCase;

class KeyHandlerTest extends TestCase
{
    private $keyHandler;

    protected function setUp(): void
    {
        $this->keyHandler = new KeyHandler();
    }

    public function testGenerateBIP39Key()
    {
        $keyName    = 'test-key';
        $compressed = false;

        $keyPair = $this->keyHandler->generateBIP39Key($keyName, $compressed);

        $this->assertIsArray($keyPair);
        $this->assertArrayHasKey('key', $keyPair);
        $this->assertArrayHasKey('name', $keyPair);
        $this->assertArrayHasKey('type', $keyPair);
        $this->assertArrayHasKey('phrase', $keyPair);
        $this->assertEquals('secp256k1', $keyPair['type']);
        $this->assertEquals($keyName, $keyPair['name']);
        $this->assertIsString($keyPair['phrase']);

        $this->assertArrayHasKey('pub', $keyPair['key']);
        $this->assertArrayHasKey('prv', $keyPair['key']);

        $this->assertArrayHasKey('pkcs8pem', $keyPair['key']['pub']);
        $this->assertArrayHasKey('pkcs8pem', $keyPair['key']['prv']);

        $this->assertIsString($keyPair['key']['pub']['pkcs8pem']);
        $this->assertIsString($keyPair['key']['prv']['pkcs8pem']);
    }

    public function testRestoreBIP39Key()
    {
        $keyName    = 'test-key';
        $compressed = false;
        $mnemonic = 'burden kind uphold penalty question green hour finger topple holiday van save';

        // Restore the key pair from the mnemonic
        $restoredKeyPair = $this->keyHandler->restoreBIP39Key($keyName, $mnemonic, $compressed);

        $this->assertIsArray($restoredKeyPair);
        $this->assertArrayHasKey('key', $restoredKeyPair);
        $this->assertArrayHasKey('name', $restoredKeyPair);
        $this->assertArrayHasKey('type', $restoredKeyPair);
        $this->assertArrayHasKey('phrase', $restoredKeyPair);
        $this->assertEquals('secp256k1', $restoredKeyPair['type']);
        $this->assertEquals($keyName, $restoredKeyPair['name']);
        $this->assertEquals($mnemonic, $restoredKeyPair['phrase']);

        $this->assertArrayHasKey('pub', $restoredKeyPair['key']);
        $this->assertArrayHasKey('prv', $restoredKeyPair['key']);

        $this->assertArrayHasKey('pkcs8pem', $restoredKeyPair['key']['pub']);
        $this->assertArrayHasKey('pkcs8pem', $restoredKeyPair['key']['prv']);

        $this->assertIsString($restoredKeyPair['key']['pub']['pkcs8pem']);
        $this->assertIsString($restoredKeyPair['key']['prv']['pkcs8pem']);

        // Ensure the restored key pair matches the original key pair
        $this->assertEquals('0x0475ed383819bd7aafccca4819cc73b807952bb824dbb3650f0fe2c3bb704913c7df8e42e1dd409d9c30d6a7b9a033927d351168b03b022c3f16fa699a81f4c6cd', $restoredKeyPair['key']['pub']['pkcs8pem']);
        $this->assertEquals('0x69c55564d84627b934ba2c898757af2f4e757c053a893e6f894ec60eba8a751b', $restoredKeyPair['key']['prv']['pkcs8pem']);
    }
}
