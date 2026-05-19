---
phase: 05
slug: hardening-de-backend-e-admin
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-03-29
---

# Phase 05 - Validation Strategy

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit |
| **Config file** | `backend/phpunit.xml` |
| **Quick run command** | `cd backend && php artisan test --filter=MvpFluxoApiTest` |
| **Focused commands** | `php artisan test --filter=BracketCupomApiTest`, `php artisan test --filter=PainelAdministrador` |
| **Full suite command** | `cd backend && php artisan test` |
| **Estimated runtime** | ~60 seconds |

## Sampling Rate

- Depois de cada task de backend admin: `php artisan test --filter=MvpFluxoApiTest`
- Depois de cada bloco de servico: `php artisan test --filter=BracketCupomApiTest`
- Antes de fechar a fase: `php artisan test`

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | Status |
|---------|------|------|-------------|-----------|-------------------|--------|
| 05-01-01 | 01 | 1 | PONT-05 | feature | `php artisan test --filter=MvpFluxoApiTest` | pending |
| 05-01-02 | 01 | 1 | ADM-02 | feature | `php artisan test --filter=MvpFluxoApiTest` | pending |
| 05-02-01 | 02 | 2 | COMP-04 | feature | `php artisan test --filter=PainelAdministrador` | pending |
| 05-02-02 | 02 | 2 | ADM-03 | feature | `php artisan test --filter=BracketCupomApiTest` | pending |
| 05-02-03 | 02 | 2 | ADM-04 | regression | `php artisan test` | pending |

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Admin continua utilizavel apos salvar resultado e regra | UI-03 | fluxo visual | Abrir painel admin, salvar resultado e regra, verificar feedback de sucesso e recarga consistente |

## Validation Sign-Off

- [ ] Todos os tasks possuem verify automatizado ou manual declarado
- [ ] Cobertura inclui fila/backend, validacao admin e regressao de pontuacao
- [ ] Full suite verde antes do summary
- [ ] `nyquist_compliant: true` atualizado ao final
