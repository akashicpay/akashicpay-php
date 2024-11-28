<?php

declare(strict_types=1);

namespace Akashic\Constants;

use Exception;

class AkashicException extends Exception
{
    private ?string $details;

    /**
     * @param string $message The error code.
     * @param string|null $details Additional details about the error.
     */
    public function __construct(string $message, ?string $details = null)
    {
        parent::__construct($message);

        // Set the error name and details
        $this->details = $details ?? AkashicErrorDetail::get($message);
    }

    /**
     * Get additional details about the error.
     */
    public function getDetails(): ?string
    {
        return $this->details;
    }
}
