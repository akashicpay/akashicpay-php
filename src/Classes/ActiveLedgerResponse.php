<?php

declare(strict_types=1);

namespace Akashic\Classes;

/** @api */
class ActiveLedgerResponse
{
    /** @var string */
    public $umid;

    /** @var ActiveLedgerResponseSummary */
    public $summary;

    /** @var ActiveLedgerResponseStream */
    public $streams;

    /** @var array|null */
    public $responses = null;

    /** @var mixed */
    public $debug;
}
