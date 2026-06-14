<?php

namespace App\Exceptions;

use RuntimeException;

class ExcecaoAsaas extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $statusCode = 502,
        private readonly ?string $codigoAsaas = null,
    ) {
        parent::__construct($message);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function codigoAsaas(): ?string
    {
        return $this->codigoAsaas;
    }
}
