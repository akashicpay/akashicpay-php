<?php

declare(strict_types=1);

namespace Akashic\Constants;

class BinanceSymbol
{
    public const BINANCE_SMART_CHAIN_MAINNET = 'BNB';
    public const BINANCE_SMART_CHAIN_TESTNET = 'tBNB';

    public const VALUES = [
        self::BINANCE_SMART_CHAIN_MAINNET,
        self::BINANCE_SMART_CHAIN_TESTNET,
    ];
}