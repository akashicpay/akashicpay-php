<?php

namespace Akashic\OTK;

use FG\ASN1\ASNObject;
use FG\ASN1\Universal\Sequence;
use FG\ASN1\Universal\Integer;
use FG\ASN1\Universal\OctetString;
use FG\ASN1\Universal\BitString;
use FG\ASN1\Universal\ObjectIdentifier;
use FG\ASN1\Composite\ASNObject as CompositeASNObject;
use FG\ASN1\Exception\ParserException;

class ECKeyHandler
{
    private static function extractNestedKeys($asn, $type = 'privateKey')
    {
        foreach ($asn as $object) {
            if ($object instanceof OctetString) {
                return bin2hex($object->getContent());
            } elseif ($object instanceof Sequence) {
                return self::extractNestedKeys($object, $type);
            }
        }

        throw new \RuntimeException('PPK not found inside ASN');
    }

    public static function decodeECPrivateKey($pkcs8pem, $label = 'EC PRIVATE KEY')
    {
        // Strip the PEM headers and decode the base64 content
        $pkcs8pem = preg_replace('/-----BEGIN .*?-----/', '', $pkcs8pem);
        $pkcs8pem = preg_replace('/-----END .*?-----/', '', $pkcs8pem);
        $pkcs8pem = str_replace(["\r", "\n"], '', $pkcs8pem);
        $binaryData = base64_decode($pkcs8pem);

        try {
            $asn = ASNObject::fromBinary($binaryData);
        } catch (ParserException $e) {
            throw new \RuntimeException('Failed to decode PKCS8 PEM: ' . $e->getMessage());
        }

        return self::extractNestedKeys($asn);
    }

    public static function encodeECPublicKey($key, $label = 'PUBLIC KEY')
    {
        $algorithm = new Sequence();
        $algorithm->addChild(new ObjectIdentifier('1.2.840.10045.2.1'));
        $algorithm->addChild(new ObjectIdentifier('1.3.132.0.10'));

        $publicKey = new BitString($key);

        $sequence = new Sequence();
        $sequence->addChild($algorithm);
        $sequence->addChild($publicKey);

        $encoded = $sequence->getBinary();
        return "-----BEGIN $label-----\r\n" . chunk_split(base64_encode($encoded), 64, "\r\n") . "-----END $label-----\r\n";
    }
}
