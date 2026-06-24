<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rotina extends Model
{
    use HasFactory;

    protected $fillable = [
        'nome',
        'slug',
        'pagina',
        'icone',
        'parent_id',
        'ordem',
        'ativo',
    ];

    protected $casts = [
        'ativo' => 'boolean',
        'ordem' => 'integer',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Rotina::class, 'parent_id');
    }

    public function filhos(): HasMany
    {
        return $this->hasMany(Rotina::class, 'parent_id')->orderBy('ordem');
    }

    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'rotina_user');
    }

    public function scopeTopLevel(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function scopeAtiva(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }

    public function isPai(): bool
    {
        return $this->parent_id === null;
    }
}
