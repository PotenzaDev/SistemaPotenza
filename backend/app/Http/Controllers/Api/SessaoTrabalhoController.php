<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sessao\IniciarSessaoRequest;
use App\Http\Resources\SessaoTrabalhoResource;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Maquina;
use App\Models\SessaoTrabalho;
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
        $desde    = Carbon::now()->subDays(3);

        $maquinasPorStatus = function (string $status) use ($operario, $desde) {
            return SessaoTrabalho::where('operario_id', $operario->id)
                ->where('status', $status)
                ->where('fim', '>=', $desde)
                ->pluck('maquina_id')
                ->unique()
                ->flip()
                ->toArray();
        };

        // Fim de turno: continua sendo retomada automaticamente ao iniciar.
        $maquinasComInterrompida = $maquinasPorStatus(SessaoTrabalho::STATUS_INTERROMPIDA_TURNO);

        // Pausa manual: ao iniciar, o operário escolhe entre retomar ou começar uma sessão nova.
        $maquinasComPausada = $maquinasPorStatus(SessaoTrabalho::STATUS_PAUSADA);

        $maquinas = Maquina::where('ativa', true)
            ->when(
                $operario && $operario->etapa_fluxo_id,
                fn ($q) => $q->where('etapa_fluxo_id', $operario->etapa_fluxo_id)
            )
            ->with('etapaFluxo')
            ->orderBy('nome')
            ->get()
            ->map(fn ($maquina) => array_merge($maquina->toArray(), [
                'tem_sessao_interrompida' => array_key_exists($maquina->id, $maquinasComInterrompida),
                'tem_sessoes_pausadas'    => array_key_exists($maquina->id, $maquinasComPausada),
            ]));

        return $this->successResponse($maquinas, 'Máquinas disponíveis.');
    }

    public function iniciar(IniciarSessaoRequest $request): JsonResponse
    {
        $operario = $request->user()->operario;
        $dados    = $request->validated();

        $sessao = $this->sessaoService->iniciar(
            $operario,
            $dados['maquina_id'],
            $dados['sessao_pausada_id'] ?? null,
            $dados['turno_informado_inicio'] ?? null,
            $dados['turno_informado_fim'] ?? null,
        );

        return $this->successResponse(
            new SessaoTrabalhoResource($sessao),
            'Sessão iniciada com sucesso.',
            201
        );
    }

    public function pausadas(Request $request): JsonResponse
    {
        $operario = $request->user()->operario;

        $maquinaId = (int) $request->validate([
            'maquina_id' => ['required', 'integer', 'exists:maquinas,id'],
        ])['maquina_id'];

        $sessoes = $this->sessaoService->listarSessoesPausadas($operario, $maquinaId);

        return $this->successResponse($sessoes, 'Sessões pausadas.');
    }

    public function encerrar(Request $request): JsonResponse
    {
        $this->sessaoService->encerrar($request->user()->operario);

        return $this->successResponse(null, 'Sessão encerrada com sucesso.');
    }

    public function pausar(Request $request): JsonResponse
    {
        $sessao = $this->sessaoService->pausar($request->user()->operario);

        return $this->successResponse(new SessaoTrabalhoResource($sessao), 'Sessão pausada com sucesso.');
    }

    public function cancelar(Request $request): JsonResponse
    {
        $this->sessaoService->cancelar($request->user()->operario);

        return $this->successResponse(null, 'Sessão cancelada com sucesso.');
    }

    /** Pausa manual da sessão ociosa (sem apontamento em andamento), com motivo. */
    public function pausarOciosa(Request $request): JsonResponse
    {
        $data = $request->validate([
            'motivo_pausa_id' => ['required', 'integer', 'exists:motivos_pausa,id'],
        ]);

        $sessao = $this->sessaoService->pausarOciosa($request->user()->operario, $data['motivo_pausa_id']);

        return $this->successResponse(new SessaoTrabalhoResource($sessao), 'Sessão pausada com sucesso.');
    }

    public function retomarOciosa(Request $request): JsonResponse
    {
        $sessao = $this->sessaoService->retomarOciosa($request->user()->operario);

        return $this->successResponse(new SessaoTrabalhoResource($sessao), 'Sessão retomada com sucesso.');
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
