<?php

namespace Akashic\Classes;
namespace Akashic\ActiveLedgerResponseStreams;
namespace Akashic\ActiveLedgerResponseSummary;

class ActiveLedgerResponse
{
    public string $umid;

    public ActiveLedgerResponseSummary $summary;

    public ActiveLedgerResponseStreams $streams;

    /** @var array|null */
    public ?array $responses = null;

    /** @var mixed */
    public $debug = null;
}
