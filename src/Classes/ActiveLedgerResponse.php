<?php

namespace Akashic\Classes;

/** @api */
class ActiveLedgerResponse
{
    /** @api */
    public string $umid;

    public ActiveLedgerResponseSummary $summary;

    public ActiveLedgerResponseStream $streams;

    /**
     * @var array|null
     */
    public ?array $responses = null;

    /**
     * @var mixed
     */
    public $debug = null;
}
