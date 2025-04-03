<?php

namespace Akashic\Utils;

class Prefix
{
    /**
     * Regular expression for matching a UMID/L2 address with or without the "AS" prefix.
     */
    private const L2_REGEX_WITH_OPTIONAL_PREFIX = '/^(AS)?[A-Fa-f\d]{64}$/';

    /**
     * Ensures a UMID/L2 address has exactly one "AS" prefix. It is idempotent.
     *
     * @param  string $umid The UMID/L2 address to check and potentially prefix.
     * @return string The UMID/L2 address with the "AS" prefix.
     * @throws \Exception if the UMID does not match the regex with or without the prefix.
     */
    public static function prefixWithAS(string $umid): string
    {
        if (!preg_match(self::L2_REGEX_WITH_OPTIONAL_PREFIX, $umid, $matches)) {
            throw new \Exception(
                "{$umid} does not match regex with or without prefix"
            );
        }

        if (!empty($matches[1])) {
            return $umid;
        }

        return "AS" . $umid;
    }

    /**
     * Ensures a UMID/L2 address doesn't have an "AS" prefix. It is idempotent.
     *
     * @param  string $umid The UMID/L2 address to check and potentially remove the prefix from.
     * @return string The UMID/L2 address without the "AS" prefix.
     * @throws \Exception if the UMID does not match the regex with or without the prefix.
     */
    public static function removeASPrefix(string $umid): string
    {
        if (!preg_match(self::L2_REGEX_WITH_OPTIONAL_PREFIX, $umid, $matches)) {
            throw new \Exception(
                "{$umid} does not match regex with or without prefix"
            );
        }

        if (!empty($matches[1])) {
            return substr($umid, 2);
        }

        return $umid;
    }
}
