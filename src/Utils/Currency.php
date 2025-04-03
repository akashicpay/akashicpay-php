<?php

namespace Akashic\Utils;

use Akashic\L1Network;
use Akashic\Constants\AkashicError;

class Currency
{
    /**
     * Method for safe conversion from coin/token decimals.
     *
     * @param string $amount
     * @param NetworkSymbol $coinSymbol
     * @param TokenSymbol|null $tokenSymbol
     * @return string
     */
    public static function convertToDecimals(
        string $amount,
        string $coinSymbol,
        ?string $tokenSymbol = null
    ): string {
        $conversionFactor = self::getConversionFactor(
            $coinSymbol,
            $tokenSymbol
        );
        $convertedAmount = pow(10, $conversionFactor) * (float) $amount;
        self::throwIfNotInteger($convertedAmount);

        return strval($convertedAmount);
    }

    /**
     * Get the conversion factor based on the coin or token symbol.
     *
     * @param string $coinSymbol
     * @param string|null $tokenSymbol
     * @return int
     */
    private static function getConversionFactor(
        string $coinSymbol,
        ?string $tokenSymbol = null
    ): int {
        $network = L1Network::NETWORK_DICTIONARY[$coinSymbol];

        if (!$tokenSymbol) {
            return $network["nativeCoin"]["decimal"];
        }

        foreach ($network["tokens"] as $token) {
            if ($token["symbol"] === $tokenSymbol) {
                return $token["decimal"];
            }
        }

        throw new \Exception(AkashicError::UNSUPPORTED_COIN_ERROR);
    }

    private static function throwIfNotInteger($amount)
    {
        if ($amount->mod(1)->__toString() !== "0") {
            throw new \Exception(AkashicError::TRANSACTION_TOO_SMALL_ERROR);
        }
    }
}
