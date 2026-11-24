<?php

namespace App\Exceptions;

use RuntimeException;

/** Domain error that is safe to show to the client. */
class MarketplaceException extends RuntimeException
{
    public function __construct(string $message, public readonly string $errorCode = 'marketplace_error', public readonly int $status = 422)
    {
        parent::__construct($message);
    }

    public function render($request)
    {
        return response()->json(['message' => $this->getMessage(), 'code' => $this->errorCode], $this->status);
    }
}
