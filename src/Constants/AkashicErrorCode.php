<?php

declare(strict_types=1);

namespace Akashic\Constants;

class AkashicErrorCode
{
    public const TEST_NET_OTK_ONBOARDING_FAILED = 'OTK_ONBOARDING_FAILED';
    public const INCORRECT_PRIVATE_KEY_FORMAT   = 'INVALID_PRIVATE_KEY_FORMAT';
    public const UNKNOWN_ERROR                  = 'UNKNOWN_ERROR';
    public const KEY_CREATION_FAILURE           = 'WALLET_CREATION_FAILURE';
    public const UNHEALTHY_KEY                  = 'UNHEALTHY_WALLET';
    public const ACCESS_DENIED                  = 'ACCESS_DENIED';
    public const L2_ADDRESS_NOT_FOUND           = 'L2ADDRESS_NOT_FOUND';
    public const IS_NOT_BP                      = 'NOT_SIGNED_UP';
    public const SAVINGS_EXCEEDED               = 'FUNDS_EXCEEDED';
    public const ASSIGNED_KEY_FAILURE           = 'ASSIGNED_KEY_FAILURE';
}
