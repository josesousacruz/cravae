# Inter World Cup - Bolão Copa 2026

## Visão Geral

Sistema web de bolão para a Copa do Mundo 2026. Usuários se cadastram, compram cupons e fazem palpites independentes por cupom. Pontuação configurável em banco com ranking por cupom.

## Stack

- **Backend**: Laravel 13, PHP 8.3+, MySQL (`interWordCup`), Sanctum (API tokens)
- **Frontend**: Vue 3 + TypeScript, Pinia, Vue Router, Tailwind CSS
- **Porta do backend**: `8888` (Coolify ocupa a 8000)
- **Porta do frontend**: `5173`

## Convenções

- **Idioma do domínio**: Português em todo o código (tabelas, models, controllers, services, views, rotas)
- **Campos estruturais do Laravel** permanecem em inglês: `id`, `created_at`, `updated_at`, `password`, `remember_token`
- Schema criado exclusivamente por migrations
- Pontuação configurável em banco por torneio e fase (nunca hardcoded)
- Validação de prazos de apostas apenas no backend
- Frontend usa Tailwind CSS com tema escuro (cores definidas em `style.css` via `@theme`)

## Estrutura

```
backend/           # Laravel 13
  app/
    Http/Controllers/   # 7 controllers
    Http/Requests/      # 7 form requests
    Models/             # 17 models
    Services/           # 4 services (Apostas, Checkout, FechamentoApostas, Pontuacao)
    Policies/           # TorneioPolicy
  database/
    migrations/         # 5 migrations
    seeders/            # 3 seeders (Admin, Torneio, Database)
  routes/api.php        # Todas as rotas da API

frontend/          # Vue 3 + TypeScript
  src/
    views/              # 7 views (Inicio, Entrar, Cadastro, Painel, Cupom, Ranking, Admin)
    stores/             # Pinia (autenticacao)
    services/           # api.ts (fetch wrapper com token automático)
    tipos.ts            # Types compartilhados
    style.css           # Tailwind + tema escuro
```

## Credenciais de Teste

- Admin: `admin@interworldcup.local` / `12345678`

## Comandos

```bash
# Backend
cd backend && php artisan serve --port=8888
cd backend && php artisan migrate:fresh --seed

# Frontend
cd frontend && npm run dev
cd frontend && npm run build
```

## GSD (Get Shit Done) Planning

Este projeto usa o framework GSD para planejamento. Toda a documentação de planejamento fica em `.planning/`.

### Arquivos GSD

- `.planning/PROJECT.md` — Visão, requisitos, restrições e decisões-chave
- `.planning/ROADMAP.md` — 4 fases com planos detalhados
- `.planning/STATE.md` — Estado atual do progresso (fonte de verdade)
- `.planning/config.json` — Configuração do GSD
- `.planning/phases/` — Planos detalhados por fase
- `.planning/research/` — Pesquisas técnicas

### Workflow GSD

1. **Antes de iniciar trabalho**: Ler `STATE.md` para saber onde parou
2. **Ao iniciar uma fase/plano**: Atualizar `STATE.md` com status `executing`
3. **Ao concluir um plano**: Gerar SUMMARY, atualizar `STATE.md` e `ROADMAP.md`
4. **Decisões importantes**: Registrar na tabela Key Decisions do `PROJECT.md`

### Estado Atual

- **Fase 1** (Fundação do MVP): Plans 01-01 e 01-02 concluídos, 01-03 em execução
- **Fases 2-4**: Não iniciadas (mas o código já implementa boa parte das fases 2-4)
