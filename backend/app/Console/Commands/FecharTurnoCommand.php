<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SessaoTrabalho;
use App\Models\Turno;
use App\Repositories\Contracts\SessaoTrabalhoRepositoryInterface;
use App\Services\SessaoTrabalhoService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class FecharTurnoCommand extends Command
{
    protected $signature = 'apontamento:fechar-turno';

    protected $description = 'Encerra sessões de trabalho ativas ao fim do turno do dia, marcando-as como interrompida_turno.';

    public function handle(SessaoTrabalhoRepositoryInterface $sessaoRepo, SessaoTrabalhoService $sessaoService): int
    {
        $agora = Carbon::now();
        $turno = Turno::doDia($agora->dayOfWeekIso);

        if (! $turno || $agora->format('H:i:s') < $turno->hora_fim) {
            return self::SUCCESS;
        }

        $sessoesAtivas = SessaoTrabalho::where('status', SessaoTrabalho::STATUS_ATIVA)->get();

        foreach ($sessoesAtivas as $sessao) {
            $sessaoService->autoPausarApontamentoAtivo($sessao);
            $sessaoRepo->encerrarSessao($sessao, true);
        }

        $this->info("Turno encerrado: {$sessoesAtivas->count()} sessão(ões) interrompida(s).");

        return self::SUCCESS;
    }
}
