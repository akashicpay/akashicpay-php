<?php

namespace Akashic\Tests;

use Akashic\AkashicPay;
use Akashic\Constants\Environment;
use PHPUnit\Framework\TestCase;

class AkashicPayTest extends TestCase
{
    private function getPrivateProperty($object, string $propertyName): mixed
    {
        $reflection = new \ReflectionClass($object);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        return $property->getValue($object);
    }

    public function testBuildWithNewOTK(): void
    {
        $akPay = new AkashicPay([
            'environment' => Environment::DEVELOPMENT,
        ]);

        // Wait for async operations if needed, e.g. $akPay->init() if it's async
        // This example assumes synchronous behavior in the constructor for simplicity

        $otk = $this->getPrivateProperty($akPay, 'otk');

        $this->assertNotNull($otk, 'OTK should not be null after building with a new OTK');
        $this->assertArrayHasKey('identity', $otk, 'OTK should contain an identity');
    }

    public function testBuildWithKeyPair(): void
    {
        $keyPair = '0x1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef'; // Replace with actual key pair
        $l2Address = 'your-l2-address-here'; // Replace with actual L2 address

        $akPay = new AkashicPay([
            'environment' => Environment::DEVELOPMENT,
            'privateKey' => $keyPair,
            'l2Address' => $l2Address,
        ]);

        // Wait for async operations if needed, e.g. $akPay->init() if it's async
        // This example assumes synchronous behavior in the constructor for simplicity

        $otk = $this->getPrivateProperty($akPay, 'otk');

        $this->assertNotNull($otk, 'OTK should not be null after building with a key pair');
        $this->assertArrayHasKey('identity', $otk, 'OTK should contain an identity');
    }

    public function testBuildWithRecoveryPhrase(): void
    {
        $recoveryPhrase = 'your-recovery-phrase-here'; // Replace with actual recovery phrase
        $l2Address = 'your-l2-address-here'; // Replace with actual L2 address

        $akPay = new AkashicPay([
            'environment' => Environment::DEVELOPMENT,
            'recoveryPhrase' => $recoveryPhrase,
            'l2Address' => $l2Address,
        ]);

        // Wait for async operations if needed, e.g. $akPay->init() if it's async
        // This example assumes synchronous behavior in the constructor for simplicity

        $otk = $this->getPrivateProperty($akPay, 'otk');

        $this->assertNotNull($otk, 'OTK should not be null after building with a recovery phrase');
        $this->assertArrayHasKey('identity', $otk, 'OTK should contain an identity');
    }
}