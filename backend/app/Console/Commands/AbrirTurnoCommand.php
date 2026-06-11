<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SessaoTrabalho;
use App\Models\Turno;
use App\Repositories\Contracts\SessaoTrabalhoRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Console\Command;

class AbrirTurnoCommand extends Command
{
    protected $signature = 'apontamento:abrir-turno';

    protected $description = 'Reabre sessões de trabalho interrompidas pelo fim do turno anterior, ao início do turno do dia.';

    public function handle(SessaoTrabalhoRepositoryInterface $sessaoRepo): int
    {
        $agora = Carbon::now();
        $turno = Turno::doDia($agora->dayOfWeekIso);

        if (! $turno || $agora->format('H:i:s') < $turno->hora_inicio) {
            return self::SUCCESS;
        }

        // Só reabre sessões interrompidas em um dia anterior: evita reabrir
        // imediatamente uma sessão encerrada manualmente ("Finalizar Turno")
        // ainda hoje, antes do fim do expediente.
        $sessoesInterrompidas = SessaoTrabalho::where('status', SessaoTrabalho::STATUS_INTERROMPIDA_TURNO)
            ->where('fim', '<', $agora->copy()->startOfDay())
            ->get();

        foreach ($sessoesInterrompidas as $sessao) {
            $sessaoRepo->reabrirSessao($sessao);
        }

        $this->info("Turno reaberto: {$sessoesInterrompidas->count()} sessão(ões) reaberta(s).");

        return self::SUCCESS;
    }
}
