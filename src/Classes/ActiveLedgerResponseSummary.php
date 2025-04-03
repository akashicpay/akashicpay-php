<?php

namespace Akashic\Classes;

class ActiveLedgerResponseSummary
{
    public int $total;

    public int $vote;

    public int $commit;

    /**
     * @var array|null
     */
    public ?array $errors = null;
}
