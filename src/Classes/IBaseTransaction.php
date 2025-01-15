<?php

declare(strict_types=1);

namespace Akashic\Classes;

/** @api */
class IBaseTransaction
{
    /** @var bool|null */
    public $selfsign = null;

    /** @var mixed */
    public $sigs;

    /** @var IBaseTransactionTxBody */
    public $tx;

    /**
     * @param mixed                  $sigs
     */
    public function __construct($sigs, IBaseTransactionTxBody $tx)
    {
        $this->sigs = $sigs;
        $this->tx   = $tx;
    }
}
