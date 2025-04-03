<?php

namespace Akashic\Constants;

class AkashicEndpoints
{
    public const PREPARE_TX = "/v0/key/prepare-l1-txn";
    public const L2_LOOKUP = "/v0/nft/look-for-l2-address";
    public const OWNER_TRANSACTION = "/v0/public-api/owner/transactions";
    public const OWNER_BALANCE = "/v0/public-api/owner/details";
    public const TRANSACTIONS_DETAILS = "/v0/transactions/transfer";
    public const IDENTIFIER_LOOKUP = "/v0/key/bp-deposit-key";
}
