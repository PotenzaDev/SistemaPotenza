<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApontamentoJaFinalizadoException;
use App\Exceptions\BusinessException;
use App\Exceptions\ConfirmacaoNecessariaException;
use App\Models\Apontamento;
use App\Models\Operario;
use App\Models\SessaoTrabalho;
use App\Repositories\Contracts\ApontamentoRepositoryInterface;
use App\Repositories\Contracts\FichaApontamentoRepositoryInterface;
use App\Repositories\Contracts\SessaoTrabalhoRepositoryInterface;
use App\Services\Lote\LoteServiceInterface;
use Carbon\Carbon;

/**
 * Apontamento dedicado à Coladeira — grupo com EtapaFluxo::calcula_metragem_borda
 * = true. O fluxo (setup → bipar ficha → finalizar) é idêntico ao genérico
 * (ApontamentoService), por isso pausar/retomar/finalizar-setup/finalizar
 * continuam servidos pelas rotas e pelo ApontamentoService já existentes —
 * são lógica de timer/status agnóstica à etapa. Este serviço só reimplementa
 * bipar() e biparFicha(), os dois pontos que precisam capturar as dimensões
 * da ficha técnica do lote (Comp/Larg/QtdBorComp/QtdBorLarg) e calcular os
 * metros lineares de fita de borda consumidos por ficha/pilha.
 */
class ApontamentoColadeiraService
{
    public function __construct(
        private readonly ApontamentoRepositoryInterface      $apontamentoRepo,
        private readonly FichaApontamentoRepositoryInterface $fichaRepo,
        private readonly SessaoTrabalhoRepositoryInterface   $sessaoRepo,
        private readonly LoteServiceInterface                $loteService,
    ) {}

    /**
     * Bipar do LOTE para iniciar setup. Igual a ApontamentoService::bipar(),
     * mas também grava comprimento/largura/espessura/qtd_bor_comp/qtd_bor_larg
     * (vindos de FbmLoteFichaTecnica) no Apontamento — usados depois em cada
     * biparFicha() para calcular a metragem de borda.
     */
    public function bipar(Operario $operario, array $dados): Apontamento
    {
        $sessao = $this->sessaoRepo->buscarSessaoAtiva($operario);

        if (! $sessao) {
            throw new BusinessException('Operário não possui sessão ativa. Selecione uma máquina primeiro.', 422);
        }

        if (! $sessao->maquina->etapaFluxo?->calcula_metragem_borda) {
            throw new BusinessException('Esta máquina não está configurada para apontamento de coladeira.', 422);
        }

        $ativos = $this->apontamentoRepo->buscarApontamentosAtivos($sessao);
        $prefixoNovo = substr($dados['cod_peca'], 0, 5);

        $deOutroLote = $ativos->first(fn (Apontamento $a) => $a->ordem_lote !== $dados['ordem_lote']);

        if ($deOutroLote) {
            throw new BusinessException(
                "Já existe um apontamento em andamento do lote {$deOutroLote->ordem_lote}. Finalize-o antes de iniciar outro lote.",
                422,
            );
        }

        $mesmaPecaBase = $ativos->first(fn (Apontamento $a) => substr($a->cod_peca, 0, 5) === $prefixoNovo);

        if ($mesmaPecaBase) {
            throw new BusinessException('Já existe um apontamento ativo para esta peça neste lote. Continue bipando fichas nele.', 422);
        }

        $loteJaEmAndamento = $ativos->isNotEmpty();

        if ($loteJaEmAndamento) {
            $regrasLote = $sessao->maquina->regraMaquina;

            if ($regrasLote && ! $regrasLote->permite_pecas_diferentes_lote) {
                throw new BusinessException(
                    'Esta máquina não permite bipar peças diferentes no mesmo lote. Finalize o apontamento ativo antes de iniciar outro.',
                    422,
                );
            }
        }

        if ($sessao->pausaOciosaAberta()->exists()) {
            throw new BusinessException('Sessão está pausada. Retome antes de bipar um novo lote.', 422);
        }

        $etapaFluxoId = $sessao->maquina->etapa_fluxo_id;

        $ultimoFinalizado = $this->apontamentoRepo->buscarUltimoFinalizadoPorLoteEtapa(
            $dados['ordem_lote'],
            $dados['cod_peca'],
            $etapaFluxoId,
        );

        if ($ultimoFinalizado) {
            if ($ultimoFinalizado->finalizado_parcial) {
                return $this->retomarFinalizadoParcial($ultimoFinalizado, $sessao);
            }

            $regrasPassagem = $sessao->maquina->regraMaquina;

            if ($regrasPassagem && ! $regrasPassagem->permite_multiplas_passagens) {
                throw new BusinessException(
                    'Este lote/peça já foi finalizado integralmente nesta etapa. Não é possível iniciar novo apontamento.',
                    422
                );
            }

            throw new ApontamentoJaFinalizadoException(
                'Esta peça já teve um apontamento finalizado nesta etapa. Deseja iniciar uma nova passagem?',
                $ultimoFinalizado->id,
            );
        }

        $loteDados       = $this->loteService->buscarPorOrdemLote($dados['ordem_lote'], $dados['cod_peca']);
        $produto         = $this->loteService->buscarProdutoCompativel(
            $dados['cod_peca'],
            $dados['ordem_lote'],
            $dados['cod_produto'],
            $dados['cor_codigo'],
        );
        $ftecPecaPilha   = $this->loteService->buscarFtecPecaPilha($dados['cod_peca']);
        $totaisVariantes = $this->loteService->buscarTotaisPorPrefixoLote(
            $dados['ordem_lote'],
            $prefixoNovo,
        );

        $qtdeTotal = $totaisVariantes['qtde_total'] ?? $loteDados['qtde_total'];

        $possuiSetup = ! $loteJaEmAndamento && ($sessao->maquina->regraMaquina?->possui_setup ?? true);

        $apontamento = $this->apontamentoRepo->criar([
            'sessao_trabalho_id' => $sessao->id,
            'etapa_fluxo_id'     => $etapaFluxoId,
            'cod_peca'           => $dados['cod_peca'],
            'ordem_lote'         => $dados['ordem_lote'],
            'desc_peca'          => $loteDados['desc_peca'],
            'cod_produto'        => $produto['cod_produto'],
            'qtde_total'         => $qtdeTotal,
            'ftec_peca_pilha'    => $ftecPecaPilha,
            'comprimento'        => $loteDados['comp'],
            'largura'            => $loteDados['larg'],
            'espessura'          => $loteDados['espess'],
            'qtd_bor_comp'       => $loteDados['qtd_bor_comp'],
            'qtd_bor_larg'       => $loteDados['qtd_bor_larg'],
            'status'             => $possuiSetup ? Apontamento::STATUS_EM_SETUP : Apontamento::STATUS_AGUARDANDO_PRODUCAO,
            'setup_inicio'       => $possuiSetup ? Carbon::now() : null,
        ]);

        return $apontamento->load(['etapaFluxo', 'fichas', 'pausas.motivoPausa']);
    }

    /**
     * Bipar ficha durante a produção. Igual a ApontamentoService::biparFicha(),
     * mas também calcula e grava metros_lineares_borda na ficha:
     * (comprimento * qtd_bor_comp + largura * qtd_bor_larg) * qtd_peca — onde
     * qtd_peca é a quantidade de peças NESTA pilha (lida do código de barras),
     * não o total do lote.
     */
    public function biparFicha(Apontamento $apontamento, array $dados, bool $confirmar = false): Apontamento
    {
        if (! in_array($apontamento->status, [
            Apontamento::STATUS_AGUARDANDO_PRODUCAO,
            Apontamento::STATUS_EM_PRODUCAO,
        ], true)) {
            throw new BusinessException('Apontamento não está aguardando ou em produção.', 422);
        }

        if ($dados['ordem_lote'] !== $apontamento->ordem_lote) {
            throw new BusinessException(
                "Esta ficha é do lote {$dados['ordem_lote']}, mas o apontamento ativo é do lote {$apontamento->ordem_lote}.",
                422
            );
        }

        if (substr($dados['cod_peca'], 0, 5) !== substr($apontamento->cod_peca, 0, 5)) {
            throw new BusinessException(
                "Esta ficha é do produto {$dados['cod_peca']}, incompatível com o apontamento ativo ({$apontamento->cod_peca}).",
                422
            );
        }

        $pilha = (int) $dados['pilha'];

        $produto = $this->loteService->buscarProdutoCompativel(
            $dados['cod_peca'],
            $apontamento->ordem_lote,
            $dados['cod_produto'],
            $dados['cor_codigo'],
        );

        $vezesBipadaAtual = $this->fichaRepo->contarVezesPilhaBipadaNoApontamento(
            $apontamento->id,
            $dados['cod_peca'],
            $pilha,
            $produto['cod_produto'],
            $produto['cor_codigo'],
        );

        if ($vezesBipadaAtual > 0) {
            $passagensEsperadas = $this->loteService->contarFichasLote(
                $apontamento->ordem_lote,
                $dados['cod_peca'],
            );

            if ($vezesBipadaAtual >= $passagensEsperadas) {
                throw new BusinessException(
                    "Pilha {$pilha} já atingiu o limite de {$passagensEsperadas} passagem(ns) neste lote.",
                    422
                );
            }

            if (! $confirmar) {
                throw new ConfirmacaoNecessariaException(
                    "Pilha {$pilha} já foi bipada neste lote. Deseja registrar uma nova passagem?",
                    $vezesBipadaAtual,
                    $passagensEsperadas,
                );
            }
        } elseif (! $confirmar) {
            $vezesBipadaAnterior = $this->fichaRepo->contarVezesPilhaBipadaEmOutrosApontamentos(
                $apontamento->ordem_lote,
                $dados['cod_peca'],
                $apontamento->etapa_fluxo_id,
                $pilha,
                $apontamento->id,
                $produto['cod_produto'],
                $produto['cor_codigo'],
            );

            if ($vezesBipadaAnterior > 0) {
                throw new ConfirmacaoNecessariaException(
                    "Esta ficha já passou por esta etapa em um apontamento anterior. Deseja processá-la novamente?",
                    $vezesBipadaAnterior,
                    $vezesBipadaAnterior + 1,
                );
            }
        }

        $agora = Carbon::now();

        $fichaAnterior = $apontamento->fichas()
            ->whereNull('fim_producao')
            ->latest('bipada_at')
            ->first();

        if ($fichaAnterior) {
            $this->fichaRepo->fecharFicha($fichaAnterior->id, $agora, $fichaAnterior->qtd_peca);
        }

        $qtdPeca = (int) $dados['qtd_peca'];

        $this->fichaRepo->criar([
            'apontamento_id'         => $apontamento->id,
            'cod_peca'               => $dados['cod_peca'],
            'cod_produto'            => $produto['cod_produto'],
            'cor_codigo'             => $produto['cor_codigo'],
            'pilha'                  => $pilha,
            'qtd_peca'               => $qtdPeca,
            'metros_lineares_borda'  => $this->calcularMetrosLinearesBorda($apontamento, $qtdPeca),
            'total_pilhas'           => $produto['total_pilhas'],
            'bipada_at'              => $agora,
        ]);

        if ($apontamento->status === Apontamento::STATUS_AGUARDANDO_PRODUCAO) {
            $apontamento->update([
                'producao_inicio' => $agora,
                'status'          => Apontamento::STATUS_EM_PRODUCAO,
            ]);
        }

        return $apontamento->load(['etapaFluxo', 'fichas', 'pausas.motivoPausa']);
    }

    /**
     * Reabre (mesmo id) um apontamento finalizado parcialmente no meio do
     * lote — mesma regra de ApontamentoService::retomarFinalizadoParcial().
     */
    private function retomarFinalizadoParcial(Apontamento $apontamento, SessaoTrabalho $sessao): Apontamento
    {
        $apontamento->update([
            'sessao_trabalho_id' => $sessao->id,
            'status'             => Apontamento::STATUS_EM_PRODUCAO,
            'producao_inicio'    => Carbon::now(),
            'producao_fim'       => null,
            'finalizado_parcial' => false,
        ]);

        return $apontamento->load(['etapaFluxo', 'fichas', 'pausas.motivoPausa']);
    }

    /**
     * comprimento/largura vêm de FbmLoteFichaTecnica já em metro (confirmado
     * com dado real: Comp=0.4820/Larg=0.3010 para uma peça de porta de
     * 48,2x30,1cm — só espessura vem em milímetro, e não entra nesta conta).
     * Sem conversão de unidade aqui.
     */
    private function calcularMetrosLinearesBorda(Apontamento $apontamento, int $qtdPeca): float
    {
        $comprimento = (float) ($apontamento->comprimento ?? 0);
        $largura     = (float) ($apontamento->largura ?? 0);
        $qtdBorComp  = (int) ($apontamento->qtd_bor_comp ?? 0);
        $qtdBorLarg  = (int) ($apontamento->qtd_bor_larg ?? 0);

        return (($comprimento * $qtdBorComp) + ($largura * $qtdBorLarg)) * $qtdPeca;
    }
}
