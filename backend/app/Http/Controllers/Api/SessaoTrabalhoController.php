<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sessao\IniciarSessaoRequest;
use App\Http\Resources\SessaoTrabalhoResource;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Apontamento;
use App\Models\Maquina;
use App\Models\Turno;
use App\Services\SessaoTrabalhoService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessaoTrabalhoController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly SessaoTrabalhoService $sessaoService,
    ) {}

    public function disponiveis(Request $request): JsonResponse
    {
        $operario = $request->user()->operario;

        $maquinasComPendencia = Apontamento::whereIn('status', [
            Apontamento::STATUS_EM_PAUSA_SETUP,
            Apontamento::STATUS_EM_PAUSA_PRODUCAO,
        ])
            ->where('created_at', '>=', Carbon::now()->subDays(3))
            ->whereHas('sessaoTrabalho', fn ($q) => $q
                ->where('operario_id', $operario->id)
                ->whereNotNull('fim')
            )
            ->whereHas('pausas', fn ($q) => $q->whereNull('fim'))
            ->with('sessaoTrabalho:id,maquina_id')
            ->get()
            ->pluck('sessaoTrabalho.maquina_id')
            ->unique()
            ->flip()
            ->toArray();

        $maquinas = Maquina::where('ativa', true)
            ->when(
                $operario && $operario->etapa_fluxo_id,
                fn ($q) => $q->where('etapa_fluxo_id', $operario->etapa_fluxo_id)
            )
            ->with('etapaFluxo')
            ->orderBy('nome')
            ->get()
            ->map(fn ($maquina) => array_merge($maquina->toArray(), [
                'tem_pendencia' => array_key_exists($maquina->id, $maquinasComPendencia),
            ]));

        return $this->successResponse($maquinas, 'Máquinas disponíveis.');
    }

    public function iniciar(IniciarSessaoRequest $request): JsonResponse
    {
        $operario = $request->user()->operario;
        $sessao   = $this->sessaoService->iniciar($operario, $request->validated()['maquina_id']);

        return $this->successResponse(
            new SessaoTrabalhoResource($sessao),
            'Sessão iniciada com sucesso.',
            201
        );
    }

    public function encerrar(Request $request): JsonResponse
    {
        $this->sessaoService->encerrar($request->user()->operario);

        return $this->successResponse(null, 'Sessão encerrada com sucesso.');
    }

    public function encerrarTurno(Request $request): JsonResponse
    {
        $this->sessaoService->encerrarTurno($request->user()->operario);

        return $this->successResponse(null, 'Turno finalizado com sucesso.');
    }

    public function turnoHoje(Request $request): JsonResponse
    {
        $turno = Turno::doDia(Carbon::now()->dayOfWeekIso);

        if (! $turno) {
            return $this->errorResponse('Nenhum turno configurado para hoje.', 404);
        }

        return $this->successResponse([
            'hora_inicio'                     => $turno->hora_inicio,
            'hora_fim'                        => $turno->hora_fim,
            'tolerancia_finalizacao_minutos'  => $turno->tolerancia_finalizacao_minutos,
        ], 'Turno de hoje.');
    }

    public function ativa(Request $request): JsonResponse
    {
        $sessao = $this->sessaoService->ativa($request->user()->operario);

        if (! $sessao) {
            return $this->errorResponse('Nenhuma sessão ativa.', 404);
        }

        return $this->successResponse(new SessaoTrabalhoResource($sessao), 'Sessão ativa.');
    }
}
