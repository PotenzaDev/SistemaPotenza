<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Apontamento extends Model
{
    use HasFactory;

    protected $fillable = [
        'sessao_trabalho_id',
        'etapa_fluxo_id',
        'cod_peca',
        'ordem_lote',
        'desc_peca',
        'cod_produto',
        'qtde_total',
        'ftec_peca_pilha',
        'status',
        'setup_inicio',
        'setup_fim',
        'setup_duracao_segundos',
        'producao_inicio',
        'producao_fim',
        'producao_duracao_segundos',
        'total_pausa_segundos',
    ];

    protected $casts = [
        'qtde_total'                => 'integer',
        'ftec_peca_pilha'           => 'integer',
        'setup_inicio'              => 'datetime',
        'setup_fim'                 => 'datetime',
        'setup_duracao_segundos'    => 'integer',
        'producao_inicio'           => 'datetime',
        'producao_fim'              => 'datetime',
        'producao_duracao_segundos' => 'integer',
        'total_pausa_segundos'      => 'integer',
    ];

    public const STATUS_EM_SETUP            = 'em_setup';
    public const STATUS_AGUARDANDO_PRODUCAO = 'aguardando_producao';
    public const STATUS_EM_PRODUCAO         = 'em_producao';
    public const STATUS_EM_PAUSA_SETUP      = 'em_pausa_setup';
    public const STATUS_EM_PAUSA_PRODUCAO   = 'em_pausa_producao';
    public const STATUS_FINALIZADO          = 'finalizado';

    public function sessaoTrabalho(): BelongsTo
    {
        return $this->belongsTo(SessaoTrabalho::class);
    }

    public function etapaFluxo(): BelongsTo
    {
        return $this->belongsTo(EtapaFluxo::class);
    }

    public function fichas(): HasMany
    {
        return $this->hasMany(FichaApontamento::class)->orderBy('pilha');
    }

    public function pausas(): HasMany
    {
        return $this->hasMany(Pausa::class)->orderBy('inicio');
    }

    public function isAtivo(): bool
    {
        return in_array($this->status, [
            self::STATUS_EM_SETUP,
            self::STATUS_AGUARDANDO_PRODUCAO,
            self::STATUS_EM_PRODUCAO,
            self::STATUS_EM_PAUSA_SETUP,
            self::STATUS_EM_PAUSA_PRODUCAO,
        ], true);
    }
}
