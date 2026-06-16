# Sistema Potenza — API Bridge

## Visão Geral
API HTTP interna que expõe consultas ao banco de dados legado (terceirizado),
para ser consumida exclusivamente pelo `backend/` (Sistema Potenza).

**PHP:** 8.2+
**Framework:** Laravel 11
**Banco:** SQL Server interno `db1Fabri` (rede local `192.168.0.x`), conexão `read_only`
**Deploy:** máquina na rede interna com acesso ao SQL Server (não exposta à internet)

---

## Arquitetura

```
backend/ (VPS)
  → HTTP request (X-Bridge-Token)
    → bridge/ (rede interna)
      → Route (routes/api.php)
        → VerifyBridgeToken (middleware)
          → FormRequest (validação)
            → Controller (orquestra)
              → Service (consulta SQL Server)
                → Response (JSON)
```

### Estrutura de Pastas

```
app/
├── Http/
│   ├── Controllers/Api/   # Controllers da API (um por domínio)
│   ├── Middleware/         # VerifyBridgeToken
│   └── Requests/            # FormRequests por endpoint
├── Services/                # Lógica de consulta ao SQL Server (interface + impl + fake)
└── Exceptions/              # RegistroNaoEncontradoException, etc.
```

---

## Autenticação

- Toda rota da API exige o header `X-Bridge-Token`, validado por
  `App\Http\Middleware\VerifyBridgeToken` (comparação com `hash_equals`).
- Token configurado em `config/bridge.php` via `BRIDGE_API_TOKEN` (`.env`).
- Se o token esperado estiver vazio ou não bater, retorna `401`.

---

## Regras de Código

### Services (padrão Interface + Real + Fake)

Cada domínio tem:
- `XxxServiceInterface` — contrato consumido pelo Controller
- `XxxService` — implementação real, faz `DB::select`/`DB::selectOne` no SQL Server
- `FakeXxxService` — implementação sem dependência do SQL Server, usada quando
  `DB_HOST` está vazio (ambiente local/testes)

O binding fica em `AppServiceProvider::register()`:

```php
$this->app->bind(FichaTecnicaServiceInterface::class, function () {
    if (empty(env('DB_HOST'))) {
        return new FakeFichaTecnicaService();
    }

    return new FichaTecnicaService();
});
```

- Registros não encontrados lançam `RegistroNaoEncontradoException`
  (renderizada como `404` em `bootstrap/app.php`).

### Controllers
- Finos: validam via FormRequest, chamam o Service, retornam `JsonResponse`.
- Nunca montar SQL ou lógica de mapeamento no controller.

### Rotas
- Todas em `routes/api.php`, dentro do grupo `middleware('verify.bridge.token')`.
- Agrupar por prefixo e nomear (`->name('ficha-tecnica.lote')`).

---

## Testes

- **Feature Tests** cobrem cada endpoint: `401` sem token/token inválido,
  `200` com dados (via `Fake*Service`), `404` quando não encontrado.
- Em ambiente de teste, `DB_HOST` vazio → binding usa o `Fake*Service`,
  então os testes não precisam de conexão real ao SQL Server.
- Rodar com: `php artisan test`

---

## Comandos Úteis

```bash
php artisan serve              # servidor local
php artisan test               # rodar testes
php artisan route:list --path=api  # listar rotas da API
```

---

## O que NÃO fazer

- Nunca remover a verificação de `X-Bridge-Token` das rotas da API.
- Nunca expor esta API publicamente na internet — apenas na rede interna,
  acessível pelo `backend/`.
- Nunca retornar dados do SQL Server sem passar pelo mapeamento do Service
  (não expor nomes de colunas/tabelas internas do ERP legado diretamente).
- Nunca commitar `.env`, token ou credenciais do SQL Server.
