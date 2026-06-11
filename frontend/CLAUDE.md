# Sistema Potenza — Frontend

## Visão Geral do Projeto
Sistema de apontamento de produção (chão de fábrica) para uso industrial.
Interface touchscreen em tablets/monitores na linha de produção.

**Repositório:** `GuAncete/SistemaPotenza`
**Backend:** Laravel + PostgreSQL (VPS Hostinger) + SQL Server interno (`terceirizado`)
**Frontend:** React + Vite + TypeScript

---

## Stack Técnica

### Core
- **React 18** + **Vite** + **TypeScript**
- **Tailwind CSS** + **shadcn/ui** (componentes base)
- **TanStack Query v5** — server state, cache, refetch automático
- **Zustand** — client state (usuário logado, turno ativo)
- **React Hook Form** + **Zod** — formulários e validação
- **Axios** — cliente HTTP (instância configurada com base URL do .env)

### Estrutura de Pastas
```
src/
├── components/         # Componentes reutilizáveis
│   ├── ui/             # shadcn/ui (não editar manualmente)
│   └── factory/        # Componentes específicos do chão de fábrica
├── pages/              # Uma pasta por rota principal
│   ├── login/
│   ├── maquinas/
│   ├── apontamento/
│   └── gestao/
├── hooks/              # Custom hooks (use*.ts)
├── services/           # Chamadas de API (*.service.ts)
├── stores/             # Zustand stores (*.store.ts)
├── types/              # Interfaces e types TypeScript (*.types.ts)
└── lib/                # Utilitários, config axios, helpers
```

---

## Regras de Código

### TypeScript
- Sempre tipar props de componentes com `interface`, nunca `type` inline
- Nunca usar `any` — se o tipo for incerto, usar `unknown` e fazer narrowing
- Exportar types de `src/types/` e importar de lá, nunca redefinir

### Componentes
- Componentes de página: `src/pages/NomePagina/index.tsx`
- Componentes reutilizáveis: `src/components/factory/NomeComponente.tsx`
- Nomear sempre em **PascalCase**
- Um componente por arquivo

### Chamadas de API
- Toda chamada de API fica em `src/services/`
- Nunca fazer fetch/axios direto dentro de componente ou hook de página
- Usar TanStack Query para GET (useQuery) e POST/PUT/DELETE (useMutation)

### Estado
- Estado de servidor → TanStack Query
- Estado global de UI (usuário, turno, máquina selecionada) → Zustand
- Estado local de formulário → React Hook Form
- Estado local simples → useState

---

## Fluxo de Apontamento (regra de negócio crítica)

```
LOGIN (crachá/badge)
  → Seleção de Máquina
    → bipar (início setup)
      → finalizar-setup
        → bipar-ficha (início produção)
          → [pausas: interrompido_turno | pausa_normal]
            → finalizar (fim produção)
```

**Status possíveis de um apontamento:**
- `em_setup` — entre bipar e finalizar-setup
- `em_producao` — entre bipar-ficha e finalizar
- `pausado` — pausa dentro do turno
- `interrompido_turno` — fim de turno com apontamento em aberto
- `finalizado` — concluído

---

## Perfis de Usuário

| Perfil | Acesso |
|--------|--------|
| `operario` | Tela de apontamento da própria máquina |
| `gestor` | Visão de todas as máquinas + relatórios |
| `admin` | Gestão de usuários + configurações |

---

## UX — Chão de Fábrica

### Obrigatório
- **Botões grandes** (mínimo `h-16` / 64px) — uso com luvas ou dedos grossos
- **Fonte legível** (mínimo 16px corpo, 24px+ em ações principais)
- **Contraste alto** — ambiente com iluminação industrial variável
- **Feedback visual imediato** — loading states em todas as ações
- **Sem modais complexos** — confirmações simples, tela cheia quando possível

### Evitar
- Dropdowns pequenos
- Inputs de texto longos (preferir scanner de código de barras)
- Tabelas com muitas colunas (colapsar em mobile/tablet)
- Animações pesadas (não é prioridade de performance aqui)

---

## Variáveis de Ambiente

```env
VITE_API_URL=https://seu-vps.com/api   # produção
VITE_API_URL=http://localhost:8000/api  # desenvolvimento local
```

A instância do Axios fica em `src/lib/axios.ts` e lê `VITE_API_URL`.

---

## Comandos Úteis

```bash
npm run dev          # Inicia dev server (porta 5173)
npm run build        # Build de produção
npm run lint         # ESLint
npm run type-check   # tsc --noEmit
```

---

## Contexto de Infraestrutura

- **VPS Hostinger** (Ubuntu): app Laravel em produção, Nginx + SSL
- **Servidor interno Windows** (`192.168.0.x`): SQL Server + apps Python
- **Banco primário:** PostgreSQL (no VPS)
- **Banco legado:** SQL Server interno (acesso via API Bridge do Laravel)
- **Auth:** Laravel Sanctum (tokens por sessão)

---

## O que NÃO fazer

- Não tentar modificar nada do banco terceirizado "SQL Server", apenas leitura
- Não commitar `.env` com dados reais
- Não adicionar lógica de negócio diretamente em componentes de página
- Não duplicar types que já existem em `src/types/`
- Não usar `console.log` em produção (usar variável de ambiente para debug)
- Não criar componentes dentro de outros arquivos de componente
