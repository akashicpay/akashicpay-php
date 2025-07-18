<?php

declare(strict_types=1);

namespace Akashic\Constants;

class AkashicEndpoints
{
    public const PREPARE_TX             = '/v0/l1-txn-orchestrator/prepare-withdrawal';
    public const L2_LOOKUP              = '/v0/nft/look-for-l2-address';
    public const OWNER_TRANSACTION      = '/v0/owner/transactions';
    public const OWNER_BALANCE          = '/v0/owner/details';
    public const TRANSACTIONS_DETAILS   = '/v0/transactions/transfer';
    public const IDENTIFIER_LOOKUP      = '/v0/key/bp-deposit-key';
    public const ALL_KEYS_OF_IDENTIFIER = '/v0/key/all-bp-deposit-keys';
    public const IS_BP                  = '/v0/owner/is-bp';
    public const SUPPORTED_CURRENCIES   = '/v1/config/supported-currencies';
    public const CREATE_DEPOSIT_ORDER   = '/v0/deposit-request';
    public const PREPARE_L2_TXN         = '/v0/l2-txn-orchestrator/prepare-l2-withdrawal';
    public const EXCHANGE_RATES         = '/v0/exchange-rate';
    public const BECOME_BP              = '/v0/owner/become-bp';
    public const GENERATE_SECONDARY_OTK = '/v0/owner/generate-secondary-otk';
    public const SET_CALLBACK_URLS      = '/v0/owner/set-callback-urls';
}
