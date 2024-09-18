<?php

namespace Akashic\Classes;

/** @api */
class IBaseTransaction
{
    /**
     * @var bool|null
     * @api
     */
    public ?bool $selfsign = null;

    /**
     * @var mixed
     */
    public $sigs;

    /**
     * @var IBaseTransactionTxBody
     */
    public IBaseTransactionTxBody $tx;

    /**
     * BaseTransaction constructor.
     *
     * @param mixed                  $sigs
     * @param IBaseTransactionTxBody $tx
     */
    public function __construct($sigs, IBaseTransactionTxBody $tx)
    {
        $this->sigs = $sigs;
        $this->tx = $tx;
    }
}
