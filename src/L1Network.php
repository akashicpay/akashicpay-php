<?php

namespace Akashic;

use Akashic\Constants\NetworkSymbol;
use Akashic\Constants\TokenSymbol;

class L1Network
{
    // 1 ETH = 1,000,000,000,000,000,000 WEI
    public const ETH_DECIMAL = 18;

    // 1 TRX = 1,000,000 SUN
    public const TRX_DECIMAL = 6;

    public const ETH_REGEX = [
        "address" => '/^0x[A-Fa-f\d]{40}$/',
        "hash" => '/^0x([A-Fa-f\d]{64})$/',
        "signedTxn" => "/0x[a-f\d]*/",
    ];

    public const TRX_REGEX = [
        "address" => '/^T[A-Za-z1-9]{33}$/',
        "hash" => '/^[\da-f]{64}$/',
        "signedTxn" => '/^[\da-f]{64}$/',
    ];

    public const NETWORK_DICTIONARY = [
        NetworkSymbol::ETHEREUM_MAINNET => [
            "regex" => self::ETH_REGEX,
            "nativeCoin" => [
                "decimal" => self::ETH_DECIMAL,
                "symbol" => "ETH",
                "displayName" => "ETH",
            ],
            "tokens" => [
                [
                    "symbol" => TokenSymbol::USDT,
                    "displayName" => "USDT (ETH)",
                    "contract" => "0xdac17f958d2ee523a2206206994597c13d831ec7",
                    "decimal" => 6,
                ],
                [
                    "symbol" => TokenSymbol::USDC,
                    "displayName" => "USDC (ETH)",
                    "contract" => "0xa0b86991c6218b36c1d19d4a2e9eb0ce3606eb48",
                    "decimal" => 6,
                ],
            ],
        ],
        NetworkSymbol::ETHEREUM_SEPOLIA => [
            "regex" => self::ETH_REGEX,
            "nativeCoin" => [
                "decimal" => self::ETH_DECIMAL,
                "symbol" => "SEP",
                "displayName" => "Sepolia-ETH",
            ],
            "tokens" => [
                [
                    "symbol" => TokenSymbol::USDT,
                    "displayName" => "USDT (Sepolia-ETH)",
                    "contract" => "0xa62be7ec09f56a813f654a9ac1aa6d29d96f604e",
                    "decimal" => 6,
                ],
            ],
        ],
        NetworkSymbol::TRON => [
            "regex" => self::TRX_REGEX,
            "nativeCoin" => [
                "decimal" => self::TRX_DECIMAL,
                "symbol" => "TRX",
                "displayName" => "TRX",
            ],
            "tokens" => [
                [
                    "symbol" => TokenSymbol::USDT,
                    "displayName" => "USDT (TRX)",
                    "contract" => "TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t",
                    "decimal" => 6,
                ],
                [
                    "symbol" => TokenSymbol::USDC,
                    "displayName" => "USDC (TRX)",
                    "contract" => "TEkxiTehnzSmSe2XqrBj4w32RUN966rdz8",
                    "decimal" => 6,
                ],
            ],
        ],
        NetworkSymbol::TRON_SHASTA => [
            "regex" => self::TRX_REGEX,
            "nativeCoin" => [
                "decimal" => self::TRX_DECIMAL,
                "symbol" => "tTRX",
                "displayName" => "TRX",
            ],
            "tokens" => [
                [
                    "symbol" => TokenSymbol::USDT,
                    "displayName" => "USDT (Shasta-TRX)",
                    "contract" => "TNLyMmgZQsb4BdPv9tLSdHSBtWv8mtXz62",
                    "decimal" => 6,
                ],
                [
                    "symbol" => TokenSymbol::TETHER,
                    "displayName" => "TETHER (Shasta-TRX)",
                    "contract" => "TG3XXyExBkPp9nzdajDZsozEu4BkaSJozs",
                    "decimal" => 6,
                ],
                [
                    "symbol" => TokenSymbol::USDC,
                    "displayName" => "USDC (Shasta-TRX)",
                    "contract" => "TQ1YTkQMRz6zJCthkC1FQAH49s1fGfoYw8",
                    "decimal" => 6,
                ],
            ],
        ],
    ];
}
