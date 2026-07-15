# Sistema Potenza — Backend Laravel

## Visão Geral
API REST para o sistema de apontamento de produção (chão de fábrica).
Autenticação via Laravel Sanctum. Três perfis de acesso.

**Repositório:** `GuAncete/SistemaPotenza` (pasta `backend/` ou raiz)
**PHP:** 8.2+
**Framework:** Laravel 11
**Bancos:** PostgreSQL (primário) + SQL Server interno (legado, acesso direto)
**Deploy:** VPS Hostinger — Ubuntu + Nginx + SSL

---

## Arquitetura

### Padrão adotado: Service Layer + Repository (opcional)

```
HTTP Request
  → Route (routes/api.php)
    → FormRequest (validação)
      → Controller (orquestra)
        → Service (lógica de negócio)
          → Model / Repository (acesso a dados)
            → Response (Resource)
```

### Estrutura de Pastas

```
app/
├── Http/
│   ├── Controllers/
│   │   └── Api/           # Todos os controllers da API aqui
│   ├── Requests/          # FormRequests por entidade
│   └── Resources/         # API Resources (transformação de resposta)
├── Services/              # Lógica de negócio (uma classe por domínio)
├── Repositories/          # Acesso a dados (opcional, usar quando necessário)
├── Models/                # Eloquent Models
├── Enums/                 # PHP Enums para status e tipos
├── Exceptions/            # Exceções customizadas
└── Policies/              # Autorização por perfil
```

---

## Regras de Código

### Controllers
- Controllers **finos**: apenas recebem request, chamam service, retornam resource
- Nunca colocar lógica de negócio no controller
- Usar injeção de dependência via construtor
- Retornar sempre `JsonResponse` tipado

```php
// ✅ Correto
public function store(StoreApontamentoRequest $request): JsonResponse
{
    $apontamento = $this->apontamentoService->iniciar(
        maquinaId: $request->validated('maquina_id'),
        userId: $request->user()->id,
    );

    return response()->json(new ApontamentoResource($apontamento), 201);
}

// ❌ Errado — lógica no controller
public function store(Request $request): JsonResponse
{
    $apontamento = Apontamento::where('maquina_id', $request->maquina_id)
        ->where('status', 'em_setup')
        ->first();
    // ...
}
```

### Services
- Uma classe por domínio (`ApontamentoService`, `TurnoService`, etc.)
- Métodos com nomes que descrevem a ação de negócio
- Lançar exceções customizadas (`ApontamentoJaAtivoException`)
- Usar `DB::transaction()` em operações que envolvem múltiplas tabelas

```php
// app/Services/ApontamentoService.php
class ApontamentoService
{
    public function iniciar(int $maquinaId, int $userId): Apontamento
    {
        return DB::transaction(function () use ($maquinaId, $userId) {
            $this->garantirSemApontamentoAtivo($maquinaId);
            // ...
        });
    }
}
```

### Models
- Sempre declarar `$fillable` explicitamente (nunca `$guarded = []`)
- Definir `$casts` para tipos (enums, datas, booleans)
- Relacionamentos em métodos com retorno tipado
- Scopes para queries reutilizáveis

```php
class Apontamento extends Model
{
    protected $fillable = [
        'maquina_id', 'user_id', 'status', 'iniciado_em',
    ];

    protected $casts = [
        'status'      => StatusApontamento::class, // Enum
        'iniciado_em' => 'datetime',
    ];

    public function maquina(): BelongsTo
    {
        return $this->belongsTo(Maquina::class);
    }

    public function scopeAtivos(Builder $query): Builder
    {
        return $query->whereIn('status', [
            StatusApontamento::EmSetup,
            StatusApontamento::EmProducao,
        ]);
    }
}
```

### Enums
- Usar PHP Enums nativos (8.1+) para todos os status
- Sempre backed enum (string ou int)

```php
// app/Enums/StatusApontamento.php
enum StatusApontamento: string
{
    case EmSetup           = 'em_setup';
    case EmProducao        = 'em_producao';
    case Pausado           = 'pausado';
    case InterrompidoTurno = 'interrompido_turno';
    case Finalizado        = 'finalizado';
}
```

### FormRequests
- Uma FormRequest por operação (não reutilizar entre create/update)
- Sempre implementar `authorize()` e `rules()`
- Mensagens customizadas em `messages()` quando necessário

```php
// app/Http/Requests/StoreApontamentoRequest.php
class StoreApontamentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole('operario');
    }

    public function rules(): array
    {
        return [
            'maquina_id' => ['required', 'integer', 'exists:maquinas,id'],
        ];
    }
}
```

### API Resources
- Sempre usar Resources para transformar resposta (nunca `$model->toArray()`)
- Nunca expor campos sensíveis (passwords, tokens internos)
- Usar `ResourceCollection` para listas

```php
// app/Http/Resources/ApontamentoResource.php
class ApontamentoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'status'      => $this->status->value,
            'maquina'     => new MaquinaResource($this->whenLoaded('maquina')),
            'iniciado_em' => $this->iniciado_em?->toISOString(),
        ];
    }
}
```

### Rotas
- Todas as rotas da API em `routes/api.php`
- Agrupar por prefixo e middleware
- Nomear todas as rotas (`->name('apontamento.iniciar')`)
- Usar Route Model Binding sempre que possível

```php
Route::middleware(['auth:sanctum'])->group(function () {

    Route::prefix('apontamento')->name('apontamento.')->group(function () {
        Route::get('ativo', [ApontamentoController::class, 'ativo'])->name('ativo');
        Route::post('/', [ApontamentoController::class, 'store'])->name('store');
        Route::post('{apontamento}/finalizar-setup', [ApontamentoController::class, 'finalizarSetup'])->name('finalizar-setup');
        Route::post('{apontamento}/finalizar', [ApontamentoController::class, 'finalizar'])->name('finalizar');
        Route::post('{apontamento}/interromper-turno', [ApontamentoController::class, 'interromperTurno'])->name('interromper-turno');
    });

});
```

---

## Fluxo de Apontamento (regra de negócio crítica)

```
POST /apontamento              → status: em_setup
POST /apontamento/{id}/finalizar-setup   → status: em_producao
POST /apontamento/{id}/pausar            → status: pausado
POST /apontamento/{id}/retomar           → status: em_producao
POST /apontamento/{id}/finalizar         → status: finalizado
POST /apontamento/{id}/interromper-turno → status: interrompido_turno
```

**Regras:**
- Apenas 1 apontamento ativo por máquina por turno
- Pausas registradas em `intervalos_producao` com `inicio` e `fim`
- `interrompido_turno` gerado automaticamente ao fim do turno
- Tempo de setup/produção calculado por diferença de timestamps

---

## Perfis e Autorização

| Perfil | Permissões |
|--------|-----------|
| `operario` | Apontar na própria máquina |
| `gestor` | Ver todos os apontamentos + relatórios |
| `admin` | CRUD de usuários, máquinas, configurações |

Usar **Laravel Policies** para autorização, nunca verificar role direto no controller.

---

## Banco de Dados

### PostgreSQL (primário — VPS)
- Tabelas do sistema Potenza
- Migrations versionadas no repositório

### SQL Server (legado — servidor interno `192.168.0.x`)
- Banco `db1Fabri` (ERP legado) — acesso **direto** a partir do `backend/`,
  já que o sistema roda na mesma rede interna do SQL Server
- Conexão dedicada `sqlsrv_legado` em `config/database.php` (`read_only`),
  configurada via `SQLSRV_HOST`/`SQLSRV_PORT`/`SQLSRV_DATABASE`/
  `SQLSRV_USERNAME`/`SQLSRV_PASSWORD`/`SQLSRV_ENCRYPT`/
  `SQLSRV_TRUST_SERVER_CERTIFICATE` no `.env` — nunca reutilizar as
  variáveis `DB_*` (essas são do Postgres primário)
- `App\Services\Lote\LoteService` (implementa `LoteServiceInterface`) e
  `App\Services\Produto\ProdutoImportService` (implementa
  `ProdutoImportServiceInterface`) consultam `DB::connection('sqlsrv_legado')`
  diretamente; se o SQL Server legado estiver inacessível, uma
  `BusinessException` (503) é lançada (ou, em métodos com fallback
  documentado no contrato da interface, um valor seguro é retornado)
- O container `backend` precisa do driver ODBC/`pdo_sqlsrv` instalado
  (ver `Dockerfile`) para essa conexão funcionar
- **Projeto `bridge/` (API HTTP legada):** mantido no repositório sem uso.
  Era o mecanismo antigo de acesso ao SQL Server quando `backend/` e o banco
  não estavam na mesma rede. Não editar/depender dele — se a topologia de
  rede mudar novamente, ele pode voltar a ser necessário, mas hoje nada em
  `backend/` o chama

### Conventions de Migration
```php
// Sempre incluir:
$table->timestamps();          // created_at, updated_at
$table->softDeletes();         // deleted_at (para entidades importantes)
$table->index(['status']);     // indexes em colunas de filtro frequente
```

---

## Tratamento de Erros

Centralizar no `bootstrap/app.php` (Laravel 11) ou `app/Exceptions/Handler.php`:

```php
// Exceções de domínio → 422
$exceptions->render(function (ApontamentoJaAtivoException $e, Request $request) {
    return response()->json(['message' => $e->getMessage()], 422);
});

// Model not found → 404 padronizado
$exceptions->render(function (ModelNotFoundException $e, Request $request) {
    return response()->json(['message' => 'Registro não encontrado.'], 404);
});
```

---

## Testes

- **Feature Tests** para todos os endpoints (`tests/Feature/Api/`)
- **Unit Tests** para Services com lógica complexa (`tests/Unit/Services/`)
- Usar factories para dados de teste
- Rodar com: `php artisan test --parallel`

```php
// Exemplo de feature test
public function test_operario_pode_iniciar_apontamento(): void
{
    $operario = User::factory()->operario()->create();
    $maquina  = Maquina::factory()->create();

    $response = $this->actingAs($operario)
        ->postJson('/api/apontamento', ['maquina_id' => $maquina->id]);

    $response->assertCreated()
        ->assertJsonPath('status', 'em_setup');
}
```

---

## Comandos Úteis

```bash
php artisan serve                  # servidor local
php artisan test --parallel        # rodar testes
php artisan migrate --step         # migrations passo a passo
php artisan route:list --path=api  # listar rotas da API
php artisan make:model Modelo -mfsc # model + migration + factory + seeder + controller
php artisan tinker                 # REPL interativo
```

---

## Infraestrutura VPS

- **OS:** Ubuntu (Hostinger VPS)
- **Web server:** Nginx
- **PHP-FPM:** 8.2
- **SSL:** Let's Encrypt (via Certbot)
- **Firewall:** UFW ativo — só portas 80, 443 e 22 abertas publicamente
- **Deploy:** push no GitHub → pull manual no VPS (ou GitHub Actions)
- **Env:** `.env` no VPS, nunca commitar

---

## O que NÃO fazer

- Nunca colocar lógica de negócio no Controller
- Nunca usar `$guarded = []` nos Models
- Nunca retornar `$model->toArray()` direto na API — usar Resource
- Nunca fazer query dentro de loop (N+1 — usar `with()` eager loading)
- Nunca commitar `.env`, chaves ou senhas
- Nunca expor conexão com o SQL Server diretamente ao frontend
- Nunca usar `DB::statement` para lógica que o Eloquent resolve
- Nunca ignorar falha de transaction sem rollback
