<?php

declare(strict_types=1);

namespace Akashic\Tests;

use Akashic\AkashicPay;
use Akashic\Constants\Environment;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class AkashicPayTest extends TestCase
{
    private function getPrivateProperty($object, string $propertyName)
    {
        $reflection = new ReflectionClass($object);
        $property   = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        return $property->getValue($object);
    }

    public function testBuildWithKeyPair(): void
    {
        $keyPair   =
            "0x1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef"; // Replace with actual key pair
        $l2Address = "your-l2-address-here"; // Replace with actual L2 address

        // Create mock without calling the constructor
        $akPayMock = $this->getMockBuilder(AkashicPay::class)
            ->disableOriginalConstructor() // Prevent constructor execution
            ->onlyMethods(['checkIfBp'])
            ->getMock();

        // Mock the checkIfBp method
        $akPayMock->method('checkIfBp')
        ->willReturn([
            "data" => [
                "isFxBp" => false,
                "isBp"   => true
            ]
        ]);

        // Manually initialize required properties via reflection
        $reflection = new ReflectionClass(AkashicPay::class);

        // Call constructor manually with arguments
        $constructor = $reflection->getConstructor();
        $constructor->setAccessible(true);
        $constructor->invoke($akPayMock, [
            "environment" => Environment::DEVELOPMENT,
            "privateKey"  => $keyPair,
            "l2Address"   => $l2Address,
        ]);

        // Get otk property
        $otkProperty = $reflection->getProperty('otk');
        $otkProperty->setAccessible(true);
        $otk = $otkProperty->getValue($akPayMock);

        // Assertions
        $this->assertNotNull($otk, "OTK should not be null after building with a key pair");
        $this->assertArrayHasKey("identity", $otk, "OTK should contain an identity");
    }

    public function testBuildWithRecoveryPhrase(): void
    {
        $recoveryPhrase = "your-recovery-phrase-here"; // Replace with actual recovery phrase
        $l2Address      = "your-l2-address-here"; // Replace with actual L2 address

        // Create mock without calling the constructor
        $akPayMock = $this->getMockBuilder(AkashicPay::class)
            ->disableOriginalConstructor() // Prevent constructor execution
            ->onlyMethods(['checkIfBp'])
            ->getMock();

        // Mock the checkIfBp method
        $akPayMock->method('checkIfBp')
        ->willReturn([
            "data" => [
                "isFxBp" => false,
                "isBp"   => true
            ]
        ]);

        // Manually initialize required properties via reflection
        $reflection = new ReflectionClass(AkashicPay::class);

        // Call constructor manually with arguments
        $constructor = $reflection->getConstructor();
        $constructor->setAccessible(true);
        $constructor->invoke($akPayMock, [
            "environment" => Environment::DEVELOPMENT,
            "recoveryPhrase"  => $recoveryPhrase,
            "l2Address"   => $l2Address,
        ]);

        // Get otk property
        $otkProperty = $reflection->getProperty('otk');
        $otkProperty->setAccessible(true);
        $otk = $otkProperty->getValue($akPayMock);

        $this->assertNotNull(
            $otk,
            "OTK should not be null after building with a recovery phrase"
        );
        $this->assertArrayHasKey(
            "identity",
            $otk,
            "OTK should contain an identity"
        );
    }
}
