---
phase: 02-cupons-e-experiencia-do-usuario
plan: 03
subsystem: ui
tags: [vue3, pinia, painel, cupons, ranking, responsive, admin]

requires:
  - phase: 02-01
    provides: API de cupons, torneio e ranking pronta para consumo
  - phase: 02-02
    provides: infraestrutura UI compartilhada, header responsivo, toast e checkout

provides:
  - Painel de cupons com cards, estado vazio, skeleton e CTA para checkout
  - CupomView com tabs principais e navegacao mobile no footer
  - Ranking publico com destaque opcional por cupom
  - Store compartilhada de torneio com cache
  - AdminPainel alinhado ao design system do produto

affects: [03-01, 03-02, 03-03, 04-03]

tech-stack:
  added: []
  patterns:
    - store Pinia para cache de torneio publicado
    - cards reutilizaveis para cupons e placeholders de loading
    - composicao de tabs com adaptacao mobile via footer navigator

key-files:
  created:
    - frontend/src/stores/torneio.ts
    - frontend/src/components/SkeletonCard.vue
    - frontend/src/components/CupomCard.vue
    - frontend/src/components/InfoTorneio.vue
  modified:
    - frontend/src/views/PainelView.vue
    - frontend/src/views/CupomView.vue
    - frontend/src/views/RankingView.vue
    - frontend/src/views/AdminPainelView.vue

key-decisions:
  - "Painel de cupons prioriza leitura rapida com cards horizontais e CTA direto para palpites"
  - "CupomView concentra palpites, ranking e resultados no mesmo contexto do cupom"
  - "Ranking permanece publico e aceita destaque do cupom atual por contexto de navegacao"
  - "Admin recebeu apenas polish visual na Fase 2, sem alterar fluxo operacional"

patterns-established:
  - "usarTorneioStore como cache compartilhado do torneio ativo"
  - "CupomCard e SkeletonCard como componentes base do painel"
  - "Navegacao mobile fixa no rodape para a tela do cupom"

requirements-completed: [CUP-04, CUP-05, UI-01, UI-02]

duration: 1sessao
completed: 2026-03-27
---

# Phase 02 Plan 03: Painel, CupomView, Ranking e Admin Summary

**Painel de cupons, CupomView com tabs, ranking publico e polish visual do admin entregues e validados por build.**

## Accomplishments

- `PainelView` exibe saudacao, CTA de compra, estado vazio, skeleton e lista de cupons com cards reutilizaveis.
- `CupomView` concentra as tabs de `Palpites`, `Ranking` e `Meus Resultados`, com navegacao mobile fixa no rodape.
- `RankingView` ficou publico, com carregamento do ranking do torneio ativo e destaque opcional por `?cupom=`.
- `AdminPainelView` foi alinhado ao mesmo design system do restante da aplicacao.
- `usarTorneioStore` evita refetch desnecessario dos dados do torneio entre telas autenticadas e publicas.

## Files Created/Modified

- `frontend/src/stores/torneio.ts` - cache compartilhado do torneio ativo
- `frontend/src/components/SkeletonCard.vue` - placeholder de loading para cards
- `frontend/src/components/CupomCard.vue` - card reutilizavel de cupom
- `frontend/src/components/InfoTorneio.vue` - resumo do torneio ativo
- `frontend/src/views/PainelView.vue` - painel de cupons com CTA, empty state e carousel
- `frontend/src/views/CupomView.vue` - tabs principais do cupom e navegacao mobile
- `frontend/src/views/RankingView.vue` - ranking publico com destaque de cupom
- `frontend/src/views/AdminPainelView.vue` - polish visual sem mudanca de logica

## Verification

- `cd frontend && npm run build` passou em `2026-03-27`

## Notes

- O estado atual do codigo ja avancou em partes da Fase 3 dentro de `CupomView`, mas isso nao invalida a conclusao funcional da Fase 2.
- A conclusao formal desta fase destrava o fluxo GSD para seguir com a Fase 3 revisada.

---
*Phase: 02-cupons-e-experiencia-do-usuario*
*Completed: 2026-03-27*
