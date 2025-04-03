<?php

declare(strict_types=1);

namespace Akashic\Classes;

/** @api */
class ActiveLedgerResponseSummary
{
    /** @var int */
    public $total;

    /** @var int */
    public $vote;

    /** @var int */
    public $commit;

    /** @var array|null */
    public $errors = null;
}
