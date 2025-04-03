<?php

namespace Akashic\OTK;

use BitWasp\Bitcoin\Mnemonic\MnemonicFactory;
use Elliptic\EC;
use BitWasp\Bitcoin\Mnemonic\Bip39\Bip39SeedGenerator;
use Akashic\Constants\KeyType;

class KeyHandler
{
    /**
     * Generate new Key Pair with BIP39 wordlist
     *
     * @param  string $keyName
     * @param  bool   $compressed
     * @return array
     * @throws \Exception
     */
    public function generateBIP39Key($keyName, $compressed = false)
    {
        try {
            $bip39 = MnemonicFactory::bip39();
            $mnemonic = $bip39->create();
            $seedGenerator = new Bip39SeedGenerator();
            $seed = $seedGenerator->getSeed($mnemonic);
            $seedHex = $seed->getHex(); // Convert the seed to hex

            $ec = new EC('secp256k1');
            $key = $ec->keyFromPrivate($seedHex, 'hex');
            $privateKey = $key->getPrivate('hex');
            $publicKey = $compressed ? $key->getPublic(true, 'hex') : $key->getPublic(false, 'hex');

            $keyHolder = [
                'key' => [
                    'pub' => [
                        'pkcs8pem' => "0x" . $publicKey
                    ],
                    'prv' => [
                        'pkcs8pem' => "0x" . $privateKey
                    ]
                ],
                'name' => $keyName,
                'type' => KeyType::ELLIPTIC_CURVE,
                'phrase' => $mnemonic
            ];

            return $keyHolder;
        } catch (\Exception $e) {
            throw new \Exception('Failed to generate BIP39 key pair. Try again.');
        }
    }

    /**
     * Restore Key Pair from BIP39 wordlist
     *
     * @param  string $keyName
     * @param  string $phrase
     * @param  bool   $compressed
     * @return array
     * @throws \Exception
     */
    public function restoreBIP39Key($keyName, $phrase, $compressed = false)
    {
        try {
            $bip39 = MnemonicFactory::bip39();
            $seedGenerator = new Bip39SeedGenerator();
            $seed = $seedGenerator->getSeed($phrase);
            $seedHex = $seed->getHex(); // Convert the seed to hex

            $ec = new EC('secp256k1');
            $key = $ec->keyFromPrivate($seedHex, 'hex');
            $privateKey = $key->getPrivate('hex');
            $publicKey = $compressed ? $key->getPublic(true, 'hex') : $key->getPublic(false, 'hex');

            $keyHolder = [
                'key' => [
                    'pub' => [
                        'pkcs8pem' => "0x" . $publicKey
                    ],
                    'prv' => [
                        'pkcs8pem' => "0x" . $privateKey
                    ]
                ],
                'name' => $keyName,
                'type' => KeyType::ELLIPTIC_CURVE,
                'phrase' => $phrase
            ];

            return $keyHolder;
        } catch (\Exception $e) {
            throw new \Exception('Failed to restore BIP39 key pair. Try again.');
        }
    }
}
