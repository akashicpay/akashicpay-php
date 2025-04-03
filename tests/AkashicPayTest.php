<?php

namespace Akashic\Tests;

use PHPUnit\Framework\TestCase;
use Akashic\AkashicPay;
use Akashic\OTK\Otk;
use Akashic\HttpClient;

class AkashicPayTest extends TestCase
{
    public function setUp(): void
    {
        // Mock the HttpClient
        $this->httpClientMock = $this->createMock(HttpClient::class);

        // Mock the Logger
        $this->loggerMock = $this->getMockBuilder(\Monolog\Logger::class)
            ->disableOriginalConstructor()
            ->getMock();

        // Replace the HttpClient in AkashicPay with the mock
        $this->akashicPay = $this->getMockBuilder(AkashicPay::class)
            ->setConstructorArgs([null, 'https://example.com', 'development', []])
            ->onlyMethods(['setNewOTK', 'chooseBestACNode'])
            ->getMock();
    }

    public function testInitWithNewOTK()
    {
        // Mock Otk class method
        $mockOtk = $this->createMock(Otk::class);
        $mockOtk->method('generateOTK')->willReturn([
            'key' => [
                'prv' => ['pkcs8pem' => 'mocked_private_key'],
                'pub' => ['pkcs8pem' => 'mocked_public_key']
            ],
            'type' => 'secp256k1',
            'name' => 'otk'
        ]);

        // Create an instance of AkashicPay with appropriate args
        $args = [
            // 'l2Address' => 'dummyAddress', // This line should be commented to test New OTK generation
        ];
        $akashicPay = new AkashicPay(null, 'http://example.com', 'production', $args);

        // Use reflection to access the protected property
        $reflectionClass = new \ReflectionClass(get_class($akashicPay));
        $reflectionProperty = $reflectionClass->getProperty('otk');
        $reflectionProperty->setAccessible(true);

        // Verify the property has been set
        $otkValue = $reflectionProperty->getValue($akashicPay);
        $this->assertNotNull($otkValue);
        $this->assertArrayHasKey('key', $otkValue);
        $this->assertArrayHasKey('prv', $otkValue['key']);
        $this->assertArrayHasKey('pkcs8pem', $otkValue['key']['prv']);
    }

    public function testInitWithPrivateKey()
    {
        $privateKey = '0x1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef';
        $args = ['l2Address' => 'test-l2-address', 'privateKey' => $privateKey];
        $this->akashicPay->__construct(null, 'https://example.com', 'development', $args);
    
        // Use reflection to access the protected property
        $reflectionClass = new \ReflectionClass($this->akashicPay);
        $reflectionProperty = $reflectionClass->getProperty('otk');
        $reflectionProperty->setAccessible(true);
    
        // Get the value of the protected property
        $otk = $reflectionProperty->getValue($this->akashicPay);
    
        $this->assertNotNull($otk);
        $this->assertEquals('test-l2-address', $otk['identity']);
    }

    public function testInitWithRecoveryPhrase()
    {
        $recoveryPhrase = 'abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon about';
        $args = ['l2Address' => 'test-l2-address', 'recoveryPhrase' => $recoveryPhrase];
        $this->akashicPay->__construct(null, 'https://example.com', 'development', $args);

        $this->assertNotNull($this->akashicPay->otk);
        $this->assertEquals('test-l2-address', $this->akashicPay->otk['identity']);
    }

    public function testInitWithIncorrectFormat()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Incorrect Private Key Format');

        $args = ['l2Address' => 'test-l2-address'];
        $this->akashicPay->__construct(null, 'https://example.com', 'development', $args);
    }
}

?>