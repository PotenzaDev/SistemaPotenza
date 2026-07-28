<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class ApontamentoJaFinalizadoException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $apontamentoOrigemId,
    ) {
        parent::__construct($message);
    }
}
