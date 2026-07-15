<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class FinalizacaoParcialException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $totalBipado,
        public readonly int $qtdeTotal,
        public readonly array $pendentesPorCor,
    ) {
        parent::__construct($message);
    }
}
