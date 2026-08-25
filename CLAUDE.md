# Cravaê - Fantasy do Brasileirão

## Visão Geral

Fantasy game de palpites do Campeonato Brasileiro Série A. Fork do bolão Copa 2026 (`C:\Projetos\Bolão Copa 2026`), transformado em produto: usuário compra um cupom por campeonato e, rodada a rodada, crava o placar de cada jogo e o autor de cada gol. Pontuação configurável em banco com ranking por cupom.

## Decisões fundadoras (2026-07-21)

- **Nome**: Cravaê (`cravae.com.br` livre no Registro.br em 21/07/2026)
- **Identidade**: dark "gramado noturno" + verde-lima elétrico (`--color-primary: #a3e635` sobre `--color-bg: #0b120e`); botões usam texto escuro (`text-bg`) sobre o acento
- **Modelo**: cupom por campeonato (temporada inteira), reusando checkout/cupons existentes
- **Palpite**: todos os jogos da rodada; placar + um autor para cada gol do placar (pode repetir jogador), escolhido do elenco real (TheSportsDB `lookup_all_players`, com foto)
- **Pontuação**: níveis exclusivos (acertou resultado < placar exato) + bônus por goleador acertado + bônus cheio por placar exato com todos os autores; valores em banco por torneio/fase
- **Fechamento**: por jogo, no horário de início de cada partida (validação só no backend)
- **Dados**: TheSportsDB free — Brasileirão Série A = liga `4351`; `eventsround` traz a rodada com placares; `lookuptimeline` traz cada gol com autor/minuto/assistência (~5 req/min → sync com throttle)
- **Pendências de domínio** (perguntar antes de implementar): gol contra, palpite 0x0, valores default de pontos, jogador transferido no meio da temporada

## Stack

- **Backend**: Laravel 13, PHP 8.3+, MySQL (`cravafc`), Sanctum (API tokens)
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
    Http/Controllers/   # controllers
    Http/Requests/      # form requests
    Models/             # models
    Services/           # services (Apostas, Checkout, FechamentoApostas, Pontuacao, TheSportsDb...)
    Policies/           # TorneioPolicy
  database/migrations e seeders
  routes/api.php        # Todas as rotas da API

frontend/          # Vue 3 + TypeScript
  src/
    views/              # Inicio, Entrar, Cadastro, Painel, Cupom, Ranking, Admin...
    stores/             # Pinia (autenticacao)
    services/           # api.ts (fetch wrapper com token automático)
    tipos.ts            # Types compartilhados
    style.css           # Tailwind + tema escuro lima
```

## Credenciais de Teste

- Admin: `admin@interworldcup.local` / `12345678` (herdado do banco do bolão; renomear ao criar o seeder do Cravaê)

## Comandos

```bash
# Backend
cd backend && php artisan serve --port=8888
cd backend && php artisan migrate:fresh --seed

# Frontend
cd frontend && npm run dev
cd frontend && npm run build
```

## Planejamento

O roadmap da transformação bolão → Cravaê está em `.planning/CRAVA-FC-ROADMAP.md`. Os demais arquivos de `.planning/` são herança do bolão Copa 2026 (histórico).

Antes de implementar feature nova ou regra de negócio: skill `grilling` (ver CLAUDE.md global).
