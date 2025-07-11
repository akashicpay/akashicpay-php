<?php

declare(strict_types=1);

namespace Akashic\Constants;

/**
 * List of networks
 */
class Networks
{
    public const MAIN_NETS = [
        NetworkSymbol::TRON,
        NetworkSymbol::ETHEREUM_MAINNET,
    ];

    public const TEST_NETS = [
        NetworkSymbol::TRON_SHASTA,
        NetworkSymbol::ETHEREUM_SEPOLIA,
    ];
}
