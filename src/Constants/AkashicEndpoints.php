<?php

namespace Akashic\Constants;

class AkashicEndpoints
{
    public const PREPARE_TX = "/v0/l1-txn-orchestrator/prepare-withdrawal";
    public const L2_LOOKUP = "/v0/nft/look-for-l2-address";
    public const OWNER_TRANSACTION = "/v0/public-api/owner/transactions";
    public const OWNER_BALANCE = "/v0/public-api/owner/details";
    public const TRANSACTIONS_DETAILS = "/v0/transactions/transfer";
    public const IDENTIFIER_LOOKUP = "/v0/key/bp-deposit-key";
    public const IS_BP = "/v0/public-api/owner/is-bp";
}
