<?php

declare(strict_types=1);

namespace Akashic\Classes;

/** @api */
class ActiveLedgerResponseSummary
{
    public int $total;

    public int $vote;

    public int $commit;

    /** @var array|null */
    public ?array $errors = null;
}
