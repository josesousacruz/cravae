# Phase 1 Research: Fundacao Do MVP

## Objective

Identificar as decisões técnicas e a ordem correta para fundar o MVP com Laravel + Vue, dados mockados, painel administrativo e pontuação configurável em banco.

## Recommended Architecture

- Estruturar o projeto em dois aplicativos no mesmo repositório:
  - `backend/` para Laravel
  - `frontend/` para Vue 3 + Vite
- Manter `.planning/` na raiz como fonte de verdade do GSD.
- Usar MySQL no backend apontando para o banco `interWordCup`.
- Usar autenticação SPA com Laravel Sanctum para a integração entre Laravel e Vue.
- Manter o domínio em português, mas preservar colunas estruturais esperadas pelo Laravel.

## Why This Direction

- Separar `backend/` e `frontend/` evita atrito entre o ciclo do Laravel e o ciclo do Vite.
- Laravel cobre autenticação, migrations, policies, validação e jobs sem necessidade de customizações pesadas.
- Vue 3 com Router e Pinia oferece base limpa para painel do usuário e painel administrativo.
- Sanctum é a opção natural para SPA first-party com Laravel.

## Implementation Decisions For Phase 1

### Backend

- Criar aplicação Laravel em `backend/`
- Configurar `.env` local para `DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`, `DB_DATABASE=interWordCup`, `DB_USERNAME=root`, `DB_PASSWORD=`
- Adotar namespace e código em português para domínio:
  - Models como `Usuario`, `Cupom`, `Torneio`
  - Controllers como `AutenticacaoController`, `TorneioController`
- Manter timestamps padrão do Laravel:
  - `created_at`
  - `updated_at`

### Frontend

- Criar aplicação Vue em `frontend/`
- Usar Vue Router
- Usar Pinia
- Usar Composition API com `<script setup>`
- Criar layout público, autenticado e administrativo já na fundação

### Banco

- Toda estrutura por migrations
- Toda carga mockada por seeders
- Regras de pontuação em `regras_pontuacao`
- Não codificar pontos fixos em services ou controllers

## Risks To Plan Around

- Misturar português completo com convenções internas do Laravel pode gerar retrabalho se tentar renomear campos estruturais.
- Começar autenticação antes de estabilizar a base do schema gera refactor cedo demais.
- Painel admin sem seeds e modelos claros atrasa o restante do produto.

## Phase Planning Implications

- Plan 01-01 deve fundar os dois apps e a infraestrutura mínima.
- Plan 01-02 deve fechar schema, models e seeds do domínio.
- Plan 01-03 deve conectar autenticação, autorização e painel admin mínimo sobre o schema já criado.

## Deliverables Needed From Phase 1

- `backend/` funcional
- `frontend/` funcional
- Migrations e seeders do domínio principal
- Autenticação inicial
- Painel admin inicial
- Regras de pontuação persistidas e administráveis

---
*Created: 2026-03-26 for Phase 1 planning*
