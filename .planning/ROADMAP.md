# Roadmap: Inter World Cup

## Overview

O MVP será entregue em quatro fases amplas: fundação técnica e administração do torneio mockado, experiência do usuário com autenticação e compra de cupons, fluxo completo de apostas por cupom e, por fim, cálculo de pontuação com ranking auditável. A sequência foi organizada para validar primeiro a base estrutural e administrativa, depois o uso real do produto e só então consolidar a lógica de pontuação e classificação.

## Phases

- [ ] **Phase 1: Fundacao Do MVP** - Estrutura Laravel + Vue, banco, autenticação, admin inicial e regras de pontuação configuráveis
- [ ] **Phase 2: Cupons E Experiencia Do Usuario** - Checkout simulado, múltiplos cupons e navegação principal responsiva
- [ ] **Phase 3: Fluxo De Apostas** - Apostas da fase de grupos, mata-mata e palpites finais com bloqueio por prazo
- [ ] **Phase 4: Pontuacao E Ranking** - Cálculo auditável, reprocessamento, ranking por cupom e fechamento operacional do MVP

## Phase Details

### Phase 1: Fundacao Do MVP
**Goal**: Entregar a base técnica do sistema com schema inicial, autenticação, painel admin mínimo e cadastro da competição mockada
**Depends on**: Nothing (first phase)
**Requirements**: AUTH-01, AUTH-02, AUTH-03, AUTH-04, COMP-01, COMP-02, COMP-03, PONT-01, ADM-01, ADM-02, ADM-03, UI-03
**Success Criteria** (what must be TRUE):
  1. Aplicação Laravel + Vue está criada, configurada e conectada ao banco `interWordCup`
  2. Usuário administrador consegue acessar painel protegido
  3. Torneio mockado, grupos, seleções, jogadores, fases, rodadas, jogos e regras de pontuação podem ser cadastrados via sistema
  4. Schema é criado somente por migrations e seeds
**Plans**: 3 plans

Plans:
- [x] 01-01: Inicializar backend Laravel, frontend Vue e configuração base do projeto
- [x] 01-02: Criar migrations, models, seeds e relacionamento do domínio principal
- [x] 01-03: Implementar autenticação, autorização e painel administrativo inicial

### Phase 2: Cupons E Experiencia Do Usuario
**Goal**: Entregar a jornada do usuário para possuir cupons ativos e navegar pelo sistema
**Depends on**: Phase 1
**Requirements**: CUP-01, CUP-02, CUP-03, CUP-04, CUP-05, UI-01, UI-02
**Success Criteria** (what must be TRUE):
  1. Usuário autenticado consegue criar pedido de checkout simulado
  2. Pagamento simulado ativa cupom corretamente
  3. Usuário consegue visualizar e acessar múltiplos cupons
  4. Interface principal funciona em layout responsivo
**Plans**: 3 plans

Plans:
- [x] 02-01-PLAN.md — Backend: migrations (telefone, valor_cupom), ajustes no checkout e testes
- [ ] 02-02-PLAN.md — Frontend: landpage, modal auth, checkout, header responsivo e infraestrutura UI
- [ ] 02-03-PLAN.md — Frontend: painel de cupons, view com tabs, ranking e admin polish

### Phase 3: Fluxo De Apostas
**Goal**: Permitir que cada cupom receba apostas completas com travas corretas de edição
**Depends on**: Phase 2
**Requirements**: APO-01, APO-02, APO-03, APO-04, APO-05, APO-06, APO-07, APO-08, APO-09
**Success Criteria** (what must be TRUE):
  1. Usuário consegue registrar apostas da fase de grupos, artilheiro e classificação de grupos
  2. Usuário consegue registrar apostas do mata-mata e palpites finais
  3. Backend bloqueia criação e edição de apostas após fechamento da rodada ou fase
  4. Toda alteração relevante de aposta fica registrada em log
**Plans**: 3 plans

Plans:
- [ ] 03-01: Implementar backend de apostas, validação por tipo e log de alterações
- [ ] 03-02: Criar telas de apostas da fase de grupos, classificação e artilheiro
- [ ] 03-03: Criar telas de apostas do mata-mata e palpites finais com regras de fechamento

### Phase 4: Pontuacao E Ranking
**Goal**: Calcular pontos com base em regras configuráveis e exibir ranking confiável por cupom
**Depends on**: Phase 3
**Requirements**: COMP-04, PONT-02, PONT-03, PONT-04, PONT-05, PONT-06, PONT-07, ADM-04
**Success Criteria** (what must be TRUE):
  1. Administrador consegue lançar resultados e disparar recálculo
  2. Sistema registra eventos de pontuação por cupom
  3. Pontuação consolidada por cupom pode ser reconstruída a partir dos eventos
  4. Ranking por cupom é exibido com critérios de desempate definidos
**Plans**: 3 plans

Plans:
- [ ] 04-01: Implementar motor de pontuação orientado por regras do banco
- [ ] 04-02: Implementar consolidação de pontuação, eventos auditáveis e reprocessamento
- [ ] 04-03: Implementar ranking por cupom e telas finais de acompanhamento

## Progress

**Execution Order:**
Phases execute in numeric order: 1 → 2 → 3 → 4

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 1. Fundacao Do MVP | 3/3 | Complete | - |
| 2. Cupons E Experiencia Do Usuario | 0/3 | Planned | - |
| 3. Fluxo De Apostas | 0/3 | Not started | - |
| 4. Pontuacao E Ranking | 0/3 | Not started | - |
