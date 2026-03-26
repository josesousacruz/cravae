# Research Summary: Inter World Cup

## Objective

Registrar as decisões iniciais de stack e implementação para orientar o planejamento do MVP.

## Key Findings

- Laravel é a base correta para o backend deste projeto por causa das exigências de migrations, autenticação, validação, autorização e modelagem relacional.
- Vue 3 com Vue Router e Pinia é a base correta para o frontend responsivo do MVP.
- O MVP não deve depender de APIs externas; dados mockados simplificam a validação do produto.
- O domínio precisa permanecer em português, mas campos estruturais do Laravel devem seguir convenções do framework quando evitam customização desnecessária.
- A pontuação deve ser parametrizada no banco para evitar regras fixas no código.

## Recommended Direction

- Backend: Laravel com Eloquent, Form Requests, Policies, Services, Jobs e Resources.
- Frontend: Vue 3 com Composition API, `<script setup>`, Vue Router, Pinia e formulários com `v-model`.
- Banco: MySQL `interWordCup`, schema criado somente por migrations e seeds.
- Fluxo de entrega: fundação/admin → cupons/checkout → apostas → pontuação/ranking.

## Planning Impact

- Fase 1 precisa estabelecer o domínio completo e o painel administrativo mínimo.
- Fase 3 deve validar fechamento de apostas no backend, nunca no frontend.
- Fase 4 precisa construir eventos auditáveis de pontuação antes do ranking final.

---
*Created: 2026-03-26*
