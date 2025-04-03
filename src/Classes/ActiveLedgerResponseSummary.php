<?php

namespace Akashic\Classes;

/** @api */
class ActiveLedgerResponseSummary
{
    public int $total;

    /** @api */
    public int $vote;

    public int $commit;

    /**
     * @var array|null
     */
    public ?array $errors = null;
}
