<?php

declare(strict_types=1);

namespace Akashic\Classes;

/** @api */
class IBaseTransactionTxBody
{
    public ?string $entry = null;

    public string $contract;

    public string $namespace;

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
