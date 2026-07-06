<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

class Maquina extends Model
{
    use HasFactory;

    protected $fillable = [
        'etapa_fluxo_id',
        'nome',
        'codigo',
        'ano',
        'descricao',
        'foto',
        'ativa',
        'prioridade',
    ];

    protected $casts = [
        'ativa' => 'boolean',
        'ano' => 'integer',
    ];

    protected $appends = ['foto_url'];

    public function getFotoUrlAttribute(): ?string
    {
        return $this->foto ? Storage::disk('public')->url($this->foto) : null;
    }

    public function etapaFluxo(): BelongsTo
    {
        return $this->belongsTo(EtapaFluxo::class);
    }

    public function sessoesTrabalho(): HasMany
    {
        return $this->hasMany(SessaoTrabalho::class);
    }

    public function sessaoAtiva(): HasOne
    {
        return $this->hasOne(SessaoTrabalho::class)->whereNull('fim');
    }

    public function ordensManutencao(): HasMany
    {
        return $this->hasMany(OrdemManutencao::class);
    }

    public function configuracaoCabecote(): HasOne
    {
        return $this->hasOne(ConfiguracaoCabecoteMaquina::class);
    }
}
