<?php

declare(strict_types=1);

namespace Akashic\Classes;

/** @api */
class IBaseTransaction
{
    public ?bool $selfsign = null;

    /** @var mixed */
    public $sigs;

    public IBaseTransactionTxBody $tx;

    /**
     * @param mixed                  $sigs
     */
    public function __construct($sigs, IBaseTransactionTxBody $tx)
    {
        $this->sigs = $sigs;
        $this->tx   = $tx;
    }
}
