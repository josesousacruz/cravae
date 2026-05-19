# Roadmap: Inter World Cup

## Overview

O milestone `v1.0` entregou um MVP funcional de bolao da Copa com backend em Laravel e frontend em Vue, incluindo checkout simulado, multiplos cupons, apostas por cupom, bracket privado e ranking por cupom. A auditoria do milestone encontrou gaps de arquitetura, qualidade e fechamento documental; por isso o roadmap agora segue com fases de correcao antes do arquivamento do milestone.

## Phases

- [x] **Phase 1: Fundacao Do MVP** - Estrutura Laravel + Vue, banco, autenticacao, admin inicial e regras de pontuacao configuraveis
- [x] **Phase 2: Cupons E Experiencia Do Usuario** - Checkout simulado, multiplos cupons e navegacao principal responsiva
- [x] **Phase 3: Fluxo De Apostas** - Apostas da fase de grupos, artilheiro e mata-mata progressivo por cupom com bloqueio por prazo
- [x] **Phase 4: Pontuacao E Ranking** - Calculo auditavel, reprocessamento, ranking por cupom e fechamento operacional do MVP
- [x] **Phase 5: Hardening De Backend E Admin** - Jobs de recalculo, validacoes fortes no admin e isolamento correto do dominio do torneio
- [x] **Phase 6: Corretude Do Frontend De Palpites** - Remover fila implicita, tirar regra de negocio do frontend e sincronizar a progressao de fases sem refresh manual
- [x] **Phase 06.1: UX De Ranking E Operacao Admin** - Melhorar a experiencia visual do ranking e simplificar o fluxo de lancamento de resultados
- [x] **Phase 7: Fechamento Formal Do Milestone** - Alinhar requisitos, summaries, verification e validacao das fases para permitir novo audit

## Phase Details

### Phase 1: Fundacao Do MVP
**Goal**: Entregar a base tecnica do sistema com schema inicial, autenticacao, painel admin minimo e cadastro da competicao mockada
**Depends on**: Nothing (first phase)
**Requirements**: AUTH-01, AUTH-02, AUTH-03, AUTH-04, COMP-01, COMP-02, COMP-03, PONT-01, ADM-01, ADM-02, ADM-03, UI-03
**Success Criteria** (what must be TRUE):
  1. Aplicacao Laravel + Vue esta criada, configurada e conectada ao banco `interWordCup`
  2. Usuario administrador consegue acessar painel protegido
  3. Torneio mockado, grupos, selecoes, jogadores, fases, rodadas, jogos e regras de pontuacao podem ser cadastrados via sistema
  4. Schema e criado somente por migrations e seeds
**Plans**: 3 plans

Plans:
- [x] 01-01: Inicializar backend Laravel, frontend Vue e configuracao base do projeto
- [x] 01-02: Criar migrations, models, seeds e relacionamento do dominio principal
- [x] 01-03: Implementar autenticacao, autorizacao e painel administrativo inicial

### Phase 2: Cupons E Experiencia Do Usuario
**Goal**: Entregar a jornada do usuario para possuir cupons ativos e navegar pelo sistema
**Depends on**: Phase 1
**Requirements**: CUP-01, CUP-02, CUP-03, CUP-04, CUP-05, UI-01, UI-02
**Success Criteria** (what must be TRUE):
  1. Usuario autenticado consegue criar pedido de checkout simulado
  2. Pagamento simulado ativa cupom corretamente
  3. Usuario consegue visualizar e acessar multiplos cupons
  4. Interface principal funciona em layout responsivo
**Plans**: 3 plans

Plans:
- [x] 02-01-PLAN.md - Backend: migrations (telefone, valor_cupom), ajustes no checkout e testes
- [x] 02-02-PLAN.md - Frontend: landpage, modal auth, checkout, header responsivo e infraestrutura UI
- [x] 02-03-PLAN.md - Frontend: painel de cupons, view com tabs, ranking e admin polish

### Phase 3: Fluxo De Apostas
**Goal**: Permitir que cada cupom receba apostas completas com travas corretas de edicao
**Depends on**: Phase 2
**Requirements**: APO-01, APO-03, APO-04, APO-05, APO-07, APO-08, APO-09
**Success Criteria** (what must be TRUE):
  1. Usuario consegue registrar apostas da fase de grupos e artilheiro por cupom
  2. Usuario consegue registrar apostas do mata-mata progressivo, com penaltis em caso de empate
  3. Backend bloqueia criacao e edicao de apostas apos fechamento da rodada ou jogo
  4. Toda alteracao relevante de aposta fica registrada em log
**Plans**: 3 plans

Plans:
- [x] 03-01: Implementar backend de apostas, validacao por tipo e log de alteracoes
- [x] 03-02: Criar telas de apostas da fase de grupos e artilheiro
- [x] 03-03: Criar telas de apostas do mata-mata progressivo com regras de fechamento

### Phase 03.1: Mata-Mata Por Cupom 2026 (INSERTED)
**Goal**: Derivar o bracket eliminatorio de cada cupom a partir dos palpites da fase de grupos, seguindo o formato oficial da Copa de 2026
**Requirements**: CUP-05, APO-04, APO-05, APO-07, APO-08, PONT-02, PONT-07
**Depends on**: Phase 3
**Plans**: 2 plans

Plans:
- [x] 03.1-01: Implementar backend para derivar Round of 32, oitavas e fases seguintes por cupom
- [x] 03.1-02: Fazer a CupomView consumir o bracket privado do cupom

### Phase 4: Pontuacao E Ranking
**Goal**: Calcular pontos com base em regras configuraveis e exibir ranking confiavel por cupom
**Depends on**: Phase 03.1
**Requirements**: COMP-04, PONT-02, PONT-03, PONT-04, PONT-05, PONT-06, PONT-07, ADM-04
**Success Criteria** (what must be TRUE):
  1. Administrador consegue lancar resultados e disparar recalculo
  2. Sistema registra eventos de pontuacao por cupom
  3. Pontuacao consolidada por cupom pode ser reconstruida a partir dos eventos
  4. Ranking por cupom e exibido com criterios de desempate definidos e com podio derivado do bracket real
**Plans**: 3 plans

Plans:
- [x] 04-01: Implementar motor de pontuacao orientado por regras do banco
- [x] 04-02: Implementar consolidacao de pontuacao, eventos auditaveis e reprocessamento
- [x] 04-03: Implementar ranking por cupom e telas finais de acompanhamento

### Phase 5: Hardening De Backend E Admin
**Goal**: Corrigir gaps criticos do backend e do fluxo administrativo identificados no audit
**Depends on**: Phase 4
**Requirements**: COMP-04, PONT-05, ADM-02, ADM-03, ADM-04, UI-03
**Gap Closure:** Closes gaps from audit
**Success Criteria** (what must be TRUE):
  1. Todo salvamento administrativo relevante despacha job do Laravel para recalculo, sem processamento pesado na request
  2. Backend valida classificado de jogo eliminatorio contra os participantes reais do confronto
  3. Servicos de bracket e pontuacao operam com melhor isolamento por torneio e menos acoplamento a queries repetidas
  4. Admin continua funcional apos as correcoes e com cobertura automatizada atualizada
**Plans**: 2 plans

Plans:
- [x] 05-01: Migrar recalculo administrativo para jobs do Laravel
- [x] 05-02: Endurecer validacoes do admin e isolar melhor o servico de bracket

### Phase 6: Corretude Do Frontend De Palpites
**Goal**: Remover fila implicita e regra de negocio do frontend no fluxo de palpites
**Depends on**: Phase 5
**Requirements**: CUP-05, APO-04, APO-05, APO-07, APO-08, UI-02
**Gap Closure:** Closes gaps from audit
**Success Criteria** (what must be TRUE):
  1. Frontend nao mantem fila implicita nem concorrencia descontrolada no autosave
  2. Regra de desbloqueio, completude e progressao de fases deixa de ser reconstruida no frontend
  3. Proxima fase aparece automaticamente apos salvar, sem refresh manual
  4. CupomView passa a consumir estado derivado pela API em vez de recomputar negocio localmente
**Plans**: 2 plans

Plans:
- [x] 06-01: Corrigir autosave da CupomView com single-flight e refetch do estado do cupom
- [x] 06-02: Passar a consumir estado derivado do bracket vindo do backend

### Phase 06.1: UX De Ranking E Operacao Admin (INSERTED)
**Goal**: Aproximar o ranking da referencia visual e tornar o fluxo administrativo de resultados mais facil de operar
**Depends on**: Phase 6
**Requirements**: ADM-04, UI-02, UI-03
**Gap Closure:** Extends milestone quality before closure
**Success Criteria** (what must be TRUE):
  1. Ranking exibe destaque visual para `1º`, `2º` e `3º` antes da lista geral
  2. Ranking continua mostrando a classificacao geral abaixo do destaque principal
  3. Painel admin oferece um fluxo mais rapido e claro para salvar resultados
  4. Feedback de salvar/processar resultados fica mais compreensivel para operacao humana
**Plans**: 2 plans

Plans:
- [x] 06.1-01: Redesenhar o ranking com podio visual de `1º`, `2º` e `3º`
- [x] 06.1-02: Simplificar o fluxo operacional de resultados no admin

### Phase 7: Fechamento Formal Do Milestone
**Goal**: Atualizar requisitos e completar a cadeia de validacao e fechamento do milestone
**Depends on**: Phase 06.1
**Requirements**: AUTH-01, AUTH-02, AUTH-03, AUTH-04, CUP-01, CUP-02, CUP-03, CUP-04, CUP-05, COMP-01, COMP-02, COMP-03, COMP-04, APO-01, APO-03, APO-04, APO-05, APO-07, APO-08, APO-09, PONT-01, PONT-02, PONT-03, PONT-04, PONT-05, PONT-06, PONT-07, ADM-01, ADM-02, ADM-03, ADM-04, UI-01, UI-02, UI-03
**Gap Closure:** Closes gaps from audit
**Success Criteria** (what must be TRUE):
  1. `REQUIREMENTS.md` reflete o produto real, removendo requisitos obsoletos e corrigindo rastreabilidade
  2. Fases 03 e 04 possuem `SUMMARY.md`
  3. Todas as fases possuem `VERIFICATION.md` ou artefatos equivalentes aceitos no GSD
  4. Milestone pode ser re-auditado sem gaps documentais bloqueando o fechamento
**Plans**: 3 plans

Plans:
- [x] 07-01: Atualizar estado global do milestone e rastreabilidade das fases finais
- [x] 07-02: Fechar summaries e verification documents faltantes
- [x] 07-03: Executar validacao final completa do milestone

## Progress

**Execution Order:**
Phases execute in numeric order: 1 -> 2 -> 3 -> 03.1 -> 4 -> 5 -> 6 -> 06.1 -> 7

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 1. Fundacao Do MVP | 3/3 | Complete | 2026-03-26 |
| 2. Cupons E Experiencia Do Usuario | 3/3 | Complete | 2026-03-27 |
| 3. Fluxo De Apostas | 3/3 | Complete | 2026-03-29 |
| 03.1. Mata-Mata Por Cupom 2026 | 2/2 | Complete | 2026-03-29 |
| 4. Pontuacao E Ranking | 3/3 | Complete | 2026-03-29 |
| 5. Hardening De Backend E Admin | 2/2 | Complete | 2026-03-29 |
| 6. Corretude Do Frontend De Palpites | 2/2 | Complete | 2026-03-29 |
| 06.1. UX De Ranking E Operacao Admin | 2/2 | Complete | 2026-03-29 |
| 7. Fechamento Formal Do Milestone | 3/3 | Complete | 2026-03-29 |
