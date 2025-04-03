<?php

namespace Akashic\OTK;

use Elliptic\EC;
use Akashic\Constants\KeyType;
use Exception;

class Otk
{
    public static function restoreOtkFromPhrase($phrase)
    {
        return (new KeyHandler())->restoreBIP39Key('otk', $phrase, true);
    }

    private static function parsePrvKey($prvKey)
    {
        if (strpos($prvKey, '0x') === 0) {
            return $prvKey;
        }

        return "-----BEGIN EC PRIVATE KEY-----\n" . $prvKey . "\n-----END EC PRIVATE KEY-----";
    }

    public static function restoreOtkFromKeypair($keyPair)
    {
        try {
            $ec = new EC('secp256k1');
            $otkPriv = self::parsePrvKey($keyPair);

            if (strpos($otkPriv, '0x') === 0) {
                $privKeyHex = str_replace('0x', '', $keyPair);
                $key = $ec->keyFromPrivate($privKeyHex);
                $publicKey = '0x' . $key->getPublic(true, 'hex');
            } else {
                $pemDecoded = hex2bin(ECKeyHandler::decodeECPrivateKey($otkPriv));
                $key = $ec->keyFromPrivate($pemDecoded);
                $publicKey = ECKeyHandler::encodeECPublicKey($key->getPublic(false, 'hex'));
            }

            return [
                'key' => [
                    'prv' => ['pkcs8pem' => $otkPriv],
                    'pub' => ['pkcs8pem' => $publicKey],
                ],
                'type' => KeyType::ELLIPTIC_CURVE,
                'name' => 'otk',
            ];
        } catch (Exception $e) {
            throw new Exception('Failed to restore OTK. Try again');
        }
    }

    public static function generateOTK()
    {
        $kh = new KeyHandler();
        return $kh->generateBIP39Key('otk', true);
    }
}
