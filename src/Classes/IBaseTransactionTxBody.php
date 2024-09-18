<?php

namespace Akashic\Classes;

/** @api */
class IBaseTransactionTxBody
{
    /**
     * @var string|null
     */
    public ?string $entry = null;

    /**
     * @var string
     */
    public string $contract;

    /**
     * @var string
     */
    public string $namespace;

    /**
     * @var mixed
     */
    public $i;

    /**
     * @var mixed|null
     */
    public $o;

    /**
     * @var mixed|null
     * @api
     */
    public $r;

    /**
     * TxBody constructor.
     *
     * @param string $contract
     * @param string $namespace
     * @param mixed  $i
     */
    public function __construct(string $contract, string $namespace, $i)
    {
        $this->contract = $contract;
        $this->namespace = $namespace;
        $this->i = $i;
    }
}
