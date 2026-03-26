---
phase: 02-cupons-e-experiencia-do-usuario
plan: 01
subsystem: api, database
tags: [laravel, migrations, sanctum, tdd, phpunit, typescript, vue, pinia]

requires:
  - phase: 01-fundacao-do-mvp
    provides: Backend Laravel com models, migrations, seeders e frontend Vue base

provides:
  - Campo telefone obrigatorio no cadastro de usuarios
  - Campo valor_cupom configuravel no torneio
  - ServicoCheckout lendo valor do torneio publicado
  - Testes cobrindo fluxo de checkout completo e telefone
  - Tipos TypeScript sincronizados com backend

affects: [02-cupons-e-experiencia-do-usuario]

tech-stack:
  added: []
  patterns:
    - TDD RED-GREEN para features de backend
    - UsuarioFactory para testes de integracao

key-files:
  created:
    - backend/database/migrations/2026_03_26_200000_add_telefone_to_usuarios_table.php
    - backend/database/migrations/2026_03_26_200001_add_valor_cupom_to_torneios_table.php
    - backend/tests/Feature/TelefoneRegistroTest.php
    - backend/tests/Feature/CheckoutFluxoTest.php
    - backend/database/factories/UsuarioFactory.php
  modified:
    - backend/app/Models/Usuario.php
    - backend/app/Models/Torneio.php
    - backend/app/Http/Requests/CadastrarUsuarioRequest.php
    - backend/app/Http/Controllers/AutenticacaoController.php
    - backend/app/Services/ServicoCheckout.php
    - backend/database/seeders/TorneioMockadoSeeder.php
    - frontend/src/tipos.ts
    - frontend/src/stores/autenticacao.ts
    - frontend/src/views/CadastroView.vue
    - frontend/src/env.d.ts

key-decisions:
  - "telefone nullable na migration para nao quebrar usuarios existentes (admin seeder)"
  - "valor_cupom com default 10.00 para compatibilidade retroativa"
  - "CadastroView atualizado com campo telefone para manter frontend funcional"

patterns-established:
  - "UsuarioFactory: factory padrao para testes de integracao do Usuario"
  - "TDD: escrever testes primeiro, confirmar RED, implementar, confirmar GREEN"

requirements-completed: [CUP-01, CUP-02, CUP-03, CUP-04]

duration: 15min
completed: 2026-03-26
---

# Phase 02 Plan 01: Backend Adjustments Summary

**Migrations de telefone e valor_cupom, ServicoCheckout lendo valor do torneio, 7 testes TDD cobrindo cadastro e checkout completo**

## Performance

- **Duration:** 15 min
- **Started:** 2026-03-26T19:04:20Z
- **Completed:** 2026-03-26T19:19:49Z
- **Tasks:** 2
- **Files modified:** 14

## Accomplishments

- Campo telefone obrigatorio no cadastro com validacao e retorno na API /usuario
- Campo valor_cupom no torneio com ServicoCheckout lendo valor dinamicamente
- 7 testes TDD passando (3 telefone + 4 checkout)
- Tipos TypeScript e store Pinia sincronizados com as novas fields do backend

## Task Commits

Each task was committed atomically:

1. **Task 1: Migrations, models e ajustes backend (TDD RED)** - `e7f2215` (test)
2. **Task 1: Migrations, models e ajustes backend (TDD GREEN)** - `237b27d` (feat)
3. **Task 2: Atualizar tipos TypeScript e store de autenticacao** - `87359da` (feat)

_Note: Prerequisite code commit `49fd49a` brought forward Phase 01-03 files needed for this plan._

## Files Created/Modified

- `backend/database/migrations/2026_03_26_200000_add_telefone_to_usuarios_table.php` - Coluna telefone na tabela usuarios
- `backend/database/migrations/2026_03_26_200001_add_valor_cupom_to_torneios_table.php` - Coluna valor_cupom na tabela torneios
- `backend/tests/Feature/TelefoneRegistroTest.php` - 3 testes do campo telefone no cadastro
- `backend/tests/Feature/CheckoutFluxoTest.php` - 4 testes do fluxo completo de checkout
- `backend/database/factories/UsuarioFactory.php` - Factory para testes de integracao
- `backend/app/Models/Usuario.php` - telefone adicionado ao fillable
- `backend/app/Models/Torneio.php` - valor_cupom adicionado ao fillable e casts
- `backend/app/Http/Requests/CadastrarUsuarioRequest.php` - Validacao required para telefone
- `backend/app/Http/Controllers/AutenticacaoController.php` - telefone no create do cadastro
- `backend/app/Services/ServicoCheckout.php` - Leitura de valor_cupom do torneio publicado
- `backend/database/seeders/TorneioMockadoSeeder.php` - valor_cupom no seed
- `frontend/src/tipos.ts` - telefone em UsuarioAutenticado, valor_cupom em Torneio
- `frontend/src/stores/autenticacao.ts` - Gerencia telefone ref e envia no cadastro
- `frontend/src/views/CadastroView.vue` - Campo telefone no formulario

## Decisions Made

- telefone nullable na migration para compatibilidade com usuario admin existente (sem telefone)
- valor_cupom com default 10.00 para retrocompatibilidade com torneios sem valor configurado
- CadastroView atualizado com campo telefone (desvio Rule 2 - funcionalidade critica faltante)

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Criacao de UsuarioFactory para testes**
- **Found during:** Task 1 (TDD RED)
- **Issue:** Testes usam Usuario::factory() mas nenhuma factory existia
- **Fix:** Criado UsuarioFactory com campos padrao incluindo telefone
- **Files modified:** backend/database/factories/UsuarioFactory.php
- **Verification:** Todos os 7 testes passam com factory
- **Committed in:** e7f2215 (TDD RED commit)

**2. [Rule 3 - Blocking] Copia de arquivos prerequisito da Phase 01-03**
- **Found during:** Task 1 setup
- **Issue:** Worktree nao continha controllers, services, requests da Phase 01-03 (existiam apenas como uncommitted changes no master)
- **Fix:** Copiados todos os arquivos necessarios e commitados
- **Files modified:** 49 arquivos (controllers, requests, services, policies, views, etc.)
- **Verification:** migrate:fresh --seed e todos os testes passam
- **Committed in:** 49fd49a

**3. [Rule 2 - Missing Critical] Campo telefone no CadastroView**
- **Found during:** Task 2
- **Issue:** Formulario de cadastro nao teria campo telefone, causando erro 422 ao cadastrar
- **Fix:** Adicionado input de telefone e parametro na chamada da store
- **Files modified:** frontend/src/views/CadastroView.vue
- **Verification:** TypeScript compila sem erros
- **Committed in:** 87359da (Task 2 commit)

**4. [Rule 3 - Blocking] Vue module declarations faltando**
- **Found during:** Task 2 (verificacao tsc)
- **Issue:** tsc nao reconhecia imports de .vue sem declarations
- **Fix:** Adicionado declare module '*.vue' em env.d.ts
- **Files modified:** frontend/src/env.d.ts
- **Verification:** npx tsc --noEmit passa limpo
- **Committed in:** 87359da (Task 2 commit)

---

**Total deviations:** 4 auto-fixed (2 blocking, 1 missing critical, 1 blocking)
**Impact on plan:** Todos os auto-fixes necessarios para correcao e funcionalidade. Sem scope creep.

## Issues Encountered

None beyond the deviations documented above.

## User Setup Required

None - no external service configuration required.

## Known Stubs

None - all data is wired through real backend endpoints and models.

## Next Phase Readiness

- Backend pronto para Phase 02-02 (reescrita do frontend de cupons)
- Telefone e valor_cupom disponiveis via API para consumo do frontend
- Testes garantem contrato estavel para desenvolvimento paralelo

---
*Phase: 02-cupons-e-experiencia-do-usuario*
*Completed: 2026-03-26*
