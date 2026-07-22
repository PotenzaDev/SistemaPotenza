<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Exceptions\FinalizacaoParcialException;
use App\Models\Apontamento;
use App\Models\FichaApontamento;
use App\Models\Operario;
use App\Models\SessaoTrabalho;
use App\Repositories\Contracts\ApontamentoRepositoryInterface;
use App\Repositories\Contracts\FichaApontamentoRepositoryInterface;
use App\Repositories\Contracts\HistoricoLoteRepositoryInterface;
use App\Repositories\Contracts\SessaoTrabalhoRepositoryInterface;
use App\Services\Lote\LoteServiceInterface;
use Carbon\Carbon;

/**
 * Apontamento dedicado às máquinas de corte (seccionadoras) — grupos com
 * EtapaFluxo::apontamento_por_lote = true. Ao contrário do fluxo genérico
 * (ApontamentoService), aqui o apontamento é por LOTE inteiro, não por peça:
 * sem tempo de setup, qualquer peça do mesmo lote é aceita no mesmo
 * apontamento, e a própria primeira bipagem já cria o registro direto em
 * em_producao e já conta como a primeira ficha.
 */
class ApontamentoCorteService
{
    public function __construct(
        private readonly ApontamentoRepositoryInterface      $apontamentoRepo,
        private readonly FichaApontamentoRepositoryInterface $fichaRepo,
        private readonly SessaoTrabalhoRepositoryInterface   $sessaoRepo,
        private readonly HistoricoLoteRepositoryInterface    $historicoRepo,
        private readonly LoteServiceInterface                $loteService,
    ) {}

    /**
     * Bipa uma ficha do lote. Sem apontamento ativo do lote informado, cria
     * um novo já em em_producao e já registra esta bipagem como a primeira
     * ficha. Com apontamento do mesmo lote já ativo, apenas acrescenta mais
     * uma ficha a ele — de qualquer peça, desde que do mesmo lote.
     */
    public function bipar(Operario $operario, array $dados): Apontamento
    {
        $sessao = $this->sessaoRepo->buscarSessaoAtiva($operario);

        if (! $sessao) {
            throw new BusinessException('Operário não possui sessão ativa. Selecione uma máquina primeiro.', 422);
        }

        if (! $sessao->maquina->etapaFluxo?->apontamento_por_lote) {
            throw new BusinessException('Esta máquina não está configurada para apontamento por lote (corte).', 422);
        }

        if ($sessao->pausaOciosaAberta()->exists()) {
            throw new BusinessException('Sessão está pausada. Retome antes de bipar um novo lote.', 422);
        }

        $ativos = $this->apontamentoRepo->buscarApontamentosAtivos($sessao);

        $apontamentoDoLote = $ativos->first(fn (Apontamento $a) => $a->ordem_lote === $dados['ordem_lote']);
        $deOutroLote       = $ativos->first(fn (Apontamento $a) => $a->ordem_lote !== $dados['ordem_lote']);

        if (! $apontamentoDoLote && $deOutroLote) {
            throw new BusinessException(
                "Já existe um apontamento em andamento do lote {$deOutroLote->ordem_lote}. Finalize-o antes de iniciar outro lote.",
                422,
            );
        }

        if ($apontamentoDoLote) {
            return $this->registrarFicha($apontamentoDoLote, $dados);
        }

        return $this->criarApontamento($sessao, $dados);
    }

    private function criarApontamento(SessaoTrabalho $sessao, array $dados): Apontamento
    {
        $etapaFluxoId = $sessao->maquina->etapa_fluxo_id;

        $loteDados  = $this->loteService->buscarPorOrdemLote($dados['ordem_lote'], $dados['cod_peca']);
        $produto    = $this->loteService->buscarProdutoCompativel(
            $dados['cod_peca'],
            $dados['ordem_lote'],
            $dados['cod_produto'],
            $dados['cor_codigo'],
        );
        $fichasLote = $this->loteService->buscarFichasDoLote($dados['ordem_lote']);

        $qtdeTotalLote = $fichasLote !== []
            ? array_sum(array_column($fichasLote, 'qtde_total'))
            : $loteDados['qtde_total'];

        $agora = Carbon::now();

        $apontamento = $this->apontamentoRepo->criar([
            'sessao_trabalho_id' => $sessao->id,
            'etapa_fluxo_id'     => $etapaFluxoId,
            'cod_peca'           => $dados['cod_peca'],
            'ordem_lote'         => $dados['ordem_lote'],
            'desc_peca'          => $loteDados['desc_peca'],
            'cod_produto'        => $loteDados['cod_produto'],
            'qtde_total'         => $qtdeTotalLote,
            'ftec_peca_pilha'    => $this->loteService->buscarFtecPecaPilha($dados['cod_peca']),
            'status'             => Apontamento::STATUS_EM_PRODUCAO,
            'producao_inicio'    => $agora,
        ]);

        $this->fichaRepo->criar([
            'apontamento_id' => $apontamento->id,
            'cod_peca'       => $dados['cod_peca'],
            'cod_produto'    => $produto['cod_produto'],
            'cor_codigo'     => $produto['cor_codigo'],
            'pilha'          => (int) $dados['pilha'],
            'qtd_peca'       => (int) $dados['qtd_peca'],
            'bipada_at'      => $agora,
        ]);

        return $apontamento->load(['etapaFluxo', 'fichas', 'pausas.motivoPausa']);
    }

    private function registrarFicha(Apontamento $apontamento, array $dados): Apontamento
    {
        if ($apontamento->status !== Apontamento::STATUS_EM_PRODUCAO) {
            throw new BusinessException('Apontamento pausado. Retome antes de bipar novas fichas.', 422);
        }

        $pilha = (int) $dados['pilha'];

        $vezesBipada = $this->fichaRepo->contarVezesPilhaBipadaNoApontamento(
            $apontamento->id,
            $dados['cod_peca'],
            $pilha,
        );

        if ($vezesBipada > 0) {
            throw new BusinessException(
                "Pilha {$pilha} da peça {$dados['cod_peca']} já foi bipada neste lote.",
                422,
            );
        }

        $produto = $this->loteService->buscarProdutoCompativel(
            $dados['cod_peca'],
            $apontamento->ordem_lote,
            $dados['cod_produto'],
            $dados['cor_codigo'],
        );

        $agora = Carbon::now();

        $fichaAnterior = $apontamento->fichas()
            ->whereNull('fim_producao')
            ->latest('bipada_at')
            ->first();

        if ($fichaAnterior) {
            $this->fichaRepo->fecharFicha($fichaAnterior->id, $agora, $fichaAnterior->qtd_peca);
        }

        $this->fichaRepo->criar([
            'apontamento_id' => $apontamento->id,
            'cod_peca'       => $dados['cod_peca'],
            'cod_produto'    => $produto['cod_produto'],
            'cor_codigo'     => $produto['cor_codigo'],
            'pilha'          => $pilha,
            'qtd_peca'       => (int) $dados['qtd_peca'],
            'bipada_at'      => $agora,
        ]);

        return $apontamento->load(['etapaFluxo', 'fichas', 'pausas.motivoPausa']);
    }

    /**
     * Checklist do lote inteiro: cada peça esperada no ERP cruzada com o que
     * já foi bipado neste apontamento — inclusive peças ainda não bipadas
     * nenhuma vez. Alimenta a tela de corte, que mostra tudo que falta do
     * lote, não só a última peça escaneada.
     */
    public function checklistDoLote(Apontamento $apontamento): array
    {
        $fichasLote = $this->loteService->buscarFichasDoLote($apontamento->ordem_lote);

        // Chave composta: o mesmo cod_peca (CodiSemiAcabado) pode aparecer em
        // mais de uma ficha física do lote para produtos/cores diferentes —
        // agrupar só por cod_peca colapsaria essas fichas distintas.
        $bipadoPorFicha = $apontamento->fichas->groupBy(
            fn (FichaApontamento $f) => "{$f->cod_peca}|{$f->cod_produto}|{$f->cor_codigo}"
        )->map(fn ($fichas) => (int) $fichas->sum('qtd_peca'));

        return array_map(function (array $ficha) use ($bipadoPorFicha) {
            $chave     = "{$ficha['cod_peca']}|{$ficha['cod_produto']}|{$ficha['cor_codigo']}";
            $qtdBipada = (int) ($bipadoPorFicha[$chave] ?? 0);

            return [
                'cod_peca'     => $ficha['cod_peca'],
                'desc_peca'    => $ficha['desc_peca'],
                'cod_produto'  => $ficha['cod_produto'],
                'cor_codigo'   => $ficha['cor_codigo'],
                'qtde_total'   => $ficha['qtde_total'],
                'total_pilhas' => $ficha['total_pilhas'],
                'qtd_bipada'   => $qtdBipada,
                'falta'        => max(0, $ficha['qtde_total'] - $qtdBipada),
            ];
        }, $fichasLote);
    }

    /**
     * Finaliza o apontamento de corte. "Incompleto" é decidido pelo checklist
     * do lote inteiro (todas as peças/prefixos), não pelo progresso por cor
     * de uma única peça base como no fluxo genérico.
     */
    public function finalizar(Apontamento $apontamento, array $fichasQtd, bool $confirmarParcial = false): Apontamento
    {
        if ($apontamento->status !== Apontamento::STATUS_EM_PRODUCAO) {
            throw new BusinessException('Apontamento não está em produção.', 422);
        }

        $checklist = $this->checklistDoLote($apontamento);
        $pendentes = array_values(array_filter($checklist, fn (array $c) => $c['falta'] > 0));

        $qtdeTotal   = (int) $apontamento->qtde_total;
        $totalBipado = (int) $apontamento->fichas->sum('qtd_peca');
        $incompleto  = ($qtdeTotal > 0 && $totalBipado < $qtdeTotal) || $pendentes !== [];

        $regras = $apontamento->sessaoTrabalho->maquina->regraMaquina;

        if ($incompleto && $regras && ! $regras->permite_finalizacao_parcial) {
            throw new BusinessException(
                "Esta máquina não permite finalização parcial. Bipe todas as peças do lote antes de finalizar. Bipado: {$totalBipado} de {$qtdeTotal} peças.",
                422,
            );
        }

        if ($incompleto && ! $confirmarParcial) {
            throw new FinalizacaoParcialException(
                "Bipe todas as peças do lote antes de finalizar, ou confirme a finalização parcial. Bipado: {$totalBipado} de {$qtdeTotal} peças.",
                $totalBipado,
                $qtdeTotal,
                $pendentes,
            );
        }

        foreach ($fichasQtd as $item) {
            $this->fichaRepo->atualizarQtdProduzida((int) $item['ficha_id'], (int) $item['qtd_produzida']);
        }

        $fim = Carbon::now();

        $pausasProducao = (int) $apontamento->pausas()
            ->where('fase', 'producao')
            ->whereNotNull('fim')
            ->sum('duracao_segundos');

        $duracaoTotal = max(0, (int) $apontamento->producao_inicio->diffInSeconds($fim) - $pausasProducao);

        $ultimaFicha = $apontamento->fichas()->whereNull('fim_producao')->latest('bipada_at')->first();

        if ($ultimaFicha) {
            $this->fichaRepo->fecharFicha($ultimaFicha->id, $fim);
        }

        $totalPausas = (int) $apontamento->pausas()->whereNotNull('fim')->sum('duracao_segundos');

        $apontamento->update([
            'producao_fim'              => $fim,
            'producao_duracao_segundos' => $duracaoTotal,
            'total_pausa_segundos'      => $totalPausas,
            'status'                    => Apontamento::STATUS_FINALIZADO,
            'finalizado_parcial'        => $incompleto,
        ]);

        $apontamento->load(['etapaFluxo', 'fichas']);

        $this->atualizarHistoricoLote($apontamento);

        return $apontamento;
    }

    private function atualizarHistoricoLote(Apontamento $apontamento): void
    {
        $historico = $this->historicoRepo->buscarOuCriar(
            $apontamento->etapa_fluxo_id,
            $apontamento->cod_peca,
            $apontamento->ordem_lote
        );

        $this->historicoRepo->incrementarPilhaConcluida($historico);

        $totalProd = $this->apontamentoRepo->somarQtdProduzida(
            $apontamento->etapa_fluxo_id,
            $apontamento->ordem_lote
        );

        if ($apontamento->qtde_total && $totalProd >= $apontamento->qtde_total) {
            $this->historicoRepo->concluir($historico);
        }
    }
}
