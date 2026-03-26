---
phase: 02-cupons-e-experiencia-do-usuario
plan: 02
subsystem: ui
tags: [vue3, tailwind, toast, modal, checkout, landpage, responsive]

# Dependency graph
requires:
  - phase: 02-01
    provides: tipos.ts com Torneio/RegraPontuacao/PedidoCheckout/Cupom, autenticacao store, api.ts, style.css tema escuro
provides:
  - Landpage completa com hero, como funciona, regras da API, CTA comprar, FAQ e footer
  - Modal de autenticacao com tabs entrar/cadastro e toast feedback
  - Tela de checkout dedicada com pagamento simulado
  - AppHeader responsivo com dropdown desktop e hamburger mobile
  - MobileMenu slide-in com scroll lock
  - Sistema global de toast notifications
  - AvatarIniciais com iniciais do nome
  - Router atualizado com /checkout, redirect /entrar e /cadastro, scrollBehavior
affects: [02-03, 03-01]

# Tech tracking
tech-stack:
  added: []
  patterns: [composable singleton via module-level ref, emit-based modal control from App.vue, query param modal redirect]

key-files:
  created:
    - frontend/src/composables/useToast.ts
    - frontend/src/components/ToastContainer.vue
    - frontend/src/components/AvatarIniciais.vue
    - frontend/src/components/AppHeader.vue
    - frontend/src/components/MobileMenu.vue
    - frontend/src/components/ModalAuth.vue
    - frontend/src/views/CheckoutView.vue
  modified:
    - frontend/src/App.vue
    - frontend/src/router/index.ts
    - frontend/src/views/InicioView.vue

key-decisions:
  - "Modal auth controlado pelo App.vue via emit, query param ?modal= abre automaticamente"
  - "Dropdown de usuario fecha com click-outside via div overlay invisivel"
  - "InicioView emite abrirModalAuth para App.vue que gerencia estado do modal"

patterns-established:
  - "Composable singleton: useToast com ref module-level compartilhada entre consumidores"
  - "Modal via Teleport com transitions separadas para overlay e conteudo"
  - "Focus trap e body scroll lock em modais fullscreen"

requirements-completed: [CUP-01, CUP-03, UI-01]

# Metrics
duration: 8min
completed: 2026-03-26
---

# Phase 02 Plan 02: Landpage, Modal Auth e Checkout Summary

**Landpage completa com 6 secoes (regras da API), modal auth com tabs entrar/cadastro, checkout com pagamento simulado, header responsivo com dropdown/hamburger e toast system global**

## Performance

- **Duration:** 8 min
- **Started:** 2026-03-26T19:24:51Z
- **Completed:** 2026-03-26T19:33:03Z
- **Tasks:** 2
- **Files modified:** 10

## Accomplishments
- Landpage com hero, como funciona (4 passos), regras de pontuacao da API, CTA comprar cupom, FAQ accordion e footer
- Modal de autenticacao com tabs entrar/cadastro, focus trap, scroll lock, toast feedback e redirect ao painel
- Tela de checkout dedicada com valor do torneio formatado, pagamento simulado (POST pedidos-checkout + simular-pagamento) e redirect com toast
- AppHeader responsivo com user dropdown (nome, email, telefone, sair) no desktop e hamburger no mobile
- MobileMenu slide-in com overlay, scroll lock e watch route para fechar
- Sistema global de toast (useToast composable + ToastContainer) com auto-dismiss 4s
- AvatarIniciais extraindo iniciais do nome em circulo bg-primary
- Router atualizado com /checkout, redirect /entrar e /cadastro para landpage com query param modal, scrollBehavior e guard atualizado

## Task Commits

Each task was committed atomically:

1. **Task 1: Infraestrutura de UI (toast, avatar, header, mobile menu) e roteador** - `61bce0d` (feat)
2. **Task 2: Landpage completa, modal de autenticacao e tela de checkout** - `ab66998` (feat)

## Files Created/Modified
- `frontend/src/composables/useToast.ts` - Sistema global de toast com singleton ref
- `frontend/src/components/ToastContainer.vue` - Renderizacao de toasts com TransitionGroup, max 3 visiveis
- `frontend/src/components/AvatarIniciais.vue` - Avatar com iniciais do nome (sm/md)
- `frontend/src/components/AppHeader.vue` - Header fixo responsivo com dropdown e hamburger
- `frontend/src/components/MobileMenu.vue` - Menu lateral slide-in com overlay e scroll lock
- `frontend/src/components/ModalAuth.vue` - Modal de login/cadastro com tabs, focus trap e scroll lock
- `frontend/src/views/InicioView.vue` - Landpage reescrita com 6 secoes completas
- `frontend/src/views/CheckoutView.vue` - Tela de checkout com pagamento simulado
- `frontend/src/App.vue` - Reescrito com AppHeader, MobileMenu, ToastContainer, ModalAuth e fade transition
- `frontend/src/router/index.ts` - /checkout, redirect /entrar e /cadastro, scrollBehavior, guard atualizado

## Decisions Made
- Modal auth controlado centralmente pelo App.vue via eventos emit, com query param ?modal= para suporte a redirects de /entrar e /cadastro
- User dropdown fecha com click-outside usando div overlay invisivel (evita complexidade de event listeners globais)
- InicioView nao duplica header (AppHeader ja esta no App.vue)
- Checkout usa dois POSTs sequenciais: criar pedido e simular pagamento, conforme API existente

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Placeholder ModalAuth e CheckoutView para Task 1**
- **Found during:** Task 1
- **Issue:** App.vue importa ModalAuth e router importa CheckoutView, mas esses componentes sao criados na Task 2
- **Fix:** Criados placeholders minimos para que o build da Task 1 passe
- **Files modified:** frontend/src/components/ModalAuth.vue, frontend/src/views/CheckoutView.vue
- **Verification:** Build passou com sucesso
- **Committed in:** 61bce0d (Task 1 commit, substituidos na Task 2)

**2. [Rule 2 - Missing Critical] Query param modal handling no App.vue**
- **Found during:** Task 2
- **Issue:** Plano mencionava que InicioView deveria verificar query param modal, mas isso nao funcionaria bem com emit para App.vue
- **Fix:** Adicionado watch no App.vue para route.query.modal que abre o ModalAuth automaticamente e limpa o query param
- **Files modified:** frontend/src/App.vue
- **Verification:** Build passou, logica de redirect /entrar -> ?modal=entrar funciona
- **Committed in:** ab66998 (Task 2 commit)

---

**Total deviations:** 2 auto-fixed (1 blocking, 1 missing critical)
**Impact on plan:** Ambas auto-fixes necessarias para corretude. Sem scope creep.

## Issues Encountered
- node_modules nao existia no worktree, necessario npm install antes do build

## Known Stubs
None - all data sources wired to API endpoints.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Infraestrutura de UI completa (header, toast, avatar, modal) pronta para reuso nas proximas views
- Checkout funcional para compra simulada de cupons
- Landpage pronta para visitantes com todas as informacoes do torneio
- Proximo passo: painel de cupons (02-03) pode reusar AppHeader, toast, avatar

## Self-Check: PASSED

All 10 created/modified files verified present. Both commit hashes (61bce0d, ab66998) verified in git log.

---
*Phase: 02-cupons-e-experiencia-do-usuario*
*Completed: 2026-03-26*
