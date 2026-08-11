<?php

declare(strict_types=1);

namespace App\Exports;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class RelatorioApontamentosExport implements FromArray, ShouldAutoSize, WithHeadings, WithMapping
{
    private const ROTULOS_TIPO = [
        'setup'    => 'Setup',
        'producao' => 'Produção',
        'pausa'    => 'Pausa',
    ];

    /** @param array<int, array<string, mixed>> $linhas */
    public function __construct(private readonly array $linhas) {}

    public function array(): array
    {
        return $this->linhas;
    }

    public function headings(): array
    {
        return [
            'Data', 'ID Máquina', 'Máquina', 'Setor', 'ID Usuário', 'Usuário', 'Lote',
            'Qtd Total Produzida', 'Fichas', 'Tipo', 'Motivo Pausa', 'Início', 'Fim', 'Duração',
        ];
    }

    /** @param array<string, mixed> $linha */
    public function map($linha): array
    {
        return [
            Carbon::parse($linha['data'])->format('d/m/Y'),
            $linha['maquina_id'],
            $linha['maquina'],
            $linha['setor'],
            $linha['user_id'],
            $linha['usuario'],
            $linha['lote'],
            $linha['qtd_total_produzida'],
            $linha['fichas'],
            self::ROTULOS_TIPO[$linha['tipo']] ?? $linha['tipo'],
            $linha['motivo_pausa'] ?? '',
            Carbon::parse($linha['inicio'])->format('H:i'),
            Carbon::parse($linha['fim'])->format('H:i'),
            $this->formatarDuracao((int) $linha['duracao_segundos']),
        ];
    }

    private function formatarDuracao(int $segundos): string
    {
        return sprintf('%02d:%02d:%02d', intdiv($segundos, 3600), intdiv($segundos % 3600, 60), $segundos % 60);
    }
}
