<?php

declare(strict_types=1);

namespace Akashic\Classes;

/** @api */
class IBaseTransactionTxBody
{
    /** @var string|null */
    public $entry = null;

    /** @var string */
    public $contract;

    /** @var string */
    public $namespace;

    /** @var mixed */
    public $i;

    /** @var mixed|null */
    public $o;

    /** @var mixed|null */
    public $r;

    /**
     * @param mixed  $i
     */
    public function __construct(string $contract, string $namespace, $i)
    {
        $this->contract  = $contract;
        $this->namespace = $namespace;
        $this->i         = $i;
    }
}
