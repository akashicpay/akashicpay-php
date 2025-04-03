<?php

declare(strict_types=1);

namespace Akashic\Classes;

/** @api */
class ActiveLedgerResponse
{
    public string $umid;

    public ActiveLedgerResponseSummary $summary;

    public ActiveLedgerResponseStream $streams;

    public ?array $responses = null;

    /** @var mixed */
    public $debug;
}
