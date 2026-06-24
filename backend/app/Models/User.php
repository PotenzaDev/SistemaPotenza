<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'must_change_password',
        'ativo',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at'    => 'datetime',
        'must_change_password' => 'boolean',
        'ativo'                => 'boolean',
    ];

    public function operario(): HasOne
    {
        return $this->hasOne(Operario::class);
    }

    public function rotinas(): BelongsToMany
    {
        return $this->belongsToMany(Rotina::class, 'rotina_user');
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isGestor(): bool
    {
        return in_array($this->role, ['gestor', 'admin'], true);
    }

    public function isOperario(): bool
    {
        return $this->role === 'operario';
    }

    public function isFuncionario(): bool
    {
        return $this->role === 'funcionario';
    }

    public function podeAcessarRotina(string $slug): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if (! $this->isFuncionario()) {
            return true;
        }

        return $this->rotinas->contains('slug', $slug);
    }
}
