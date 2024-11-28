<?php

declare(strict_types=1);

namespace Akashic\Constants;

class AkashicEndpoints
{
    public const PREPARE_TX           = '/v0/l1-txn-orchestrator/prepare-withdrawal';
    public const L2_LOOKUP            = '/v0/nft/look-for-l2-address';
    public const OWNER_TRANSACTION    = '/v0/owner/transactions';
    public const OWNER_BALANCE        = '/v0/owner/details';
    public const TRANSACTIONS_DETAILS = '/v0/transactions/transfer';
    public const IDENTIFIER_LOOKUP    = '/v0/key/bp-deposit-key';
    public const IS_BP                = '/v0/owner/is-bp';
    public const SUPPORTED_CURRENCIES = '/v0/config/supported-currencies';
}
