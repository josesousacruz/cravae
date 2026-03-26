# Inter World Cup

## What This Is

Sistema web responsivo de bolão para a Copa em que usuários se cadastram, compram um ou mais cupons e fazem conjuntos independentes de apostas por cupom. O MVP cobre fluxo completo com dados mockados, painel administrativo, regras de fechamento de apostas, pontuação configurável em banco e ranking por cupom.

## Core Value

Permitir que cada cupom funcione como uma entrada independente, com apostas completas e pontuação auditável, sem depender de integrações externas.

## Requirements

### Validated

(None yet — ship to validate)

### Active

- [ ] Entregar MVP web responsivo em Laravel + Vue com dados mockados da competição
- [ ] Permitir cadastro, login, compra simulada de cupom e múltiplos cupons por usuário
- [ ] Permitir apostas completas por cupom com bloqueio automático no backend
- [ ] Calcular pontuação por regras configuráveis no banco e exibir ranking por cupom
- [ ] Disponibilizar painel administrativo para gerenciar torneio, jogos, resultados e regras

### Out of Scope

- APIs externas de times, jogadores, tabela ou resultados — MVP usará dados mockados para reduzir dependências
- PIX real — checkout será apenas simulado nesta fase inicial
- App mobile nativo — foco exclusivo em web responsiva
- Recursos sociais, ligas privadas e notificações avançadas — não são centrais para validar o produto

## Context

- O projeto será implementado com backend em Laravel seguindo convenções oficiais do framework.
- O frontend seguirá Vue 3 com Vue Router, Pinia e Composition API.
- O banco MySQL já existe com o nome `interWordCup`, host `127.0.0.1`, usuário `root` e sem senha no ambiente local.
- O schema deve ser criado exclusivamente por migrations.
- Toda a linguagem do domínio deve ficar em português: tabelas, models, controllers, serviços, telas e documentação.
- Para reduzir atrito com o Laravel, campos estruturais do framework permanecem no padrão esperado (`id`, `created_at`, `updated_at`, `password`, `remember_token`, `email_verified_at`).
- A pontuação não pode ficar hardcoded; deve ser configurável no banco por torneio e fase.

## Constraints

- **Stack**: Laravel no backend e Vue.js no frontend — exigência explícita do projeto
- **Dados**: MVP com dados mockados — evita bloqueio por API externa nesta etapa
- **Banco**: MySQL `interWordCup` em `127.0.0.1` — ambiente já provisionado
- **Idioma**: Domínio e código em português — requisito de consistência do projeto
- **Pontuação**: Regras configuráveis em banco — evita acoplamento da regra de negócio no código
- **Segurança**: Validação de prazos apenas no backend — frontend não é fonte de verdade

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| Usar dados mockados no MVP | Validar fluxo principal antes de integrar APIs externas | — Pending |
| Ranking por cupom, não por usuário | Cada cupom representa uma entrada independente no bolão | — Pending |
| Checkout apenas simulado | Reduz complexidade e acelera validação do produto | — Pending |
| Pontuação configurável em banco | Permite ajuste de regra sem redeploy | — Pending |
| Backend em Laravel e frontend em Vue | Alinhamento com a diretriz técnica definida | — Pending |

---
*Last updated: 2026-03-26 after initial GSD project setup*
