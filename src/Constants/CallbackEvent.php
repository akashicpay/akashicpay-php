<?php

declare(strict_types=1);

namespace Akashic\Constants;

class CallbackEvent
{
    public const CONFIRMED_DEPOSIT = 'deposit';
    public const PENDING_DEPOSIT   = 'pendingDeposit';
    public const FAILED_DEPOSIT    = 'failedDeposit';
    public const CONFIRMED_PAYOUT  = 'payout';
    public const PENDING_PAYOUT    = 'pendingPayout';
    public const FAILED_PAYOUT     = 'failedPayout';
}
