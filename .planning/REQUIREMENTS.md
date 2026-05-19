# Requirements: Inter World Cup

**Defined:** 2026-03-26
**Core Value:** Permitir que cada cupom funcione como uma entrada independente, com apostas completas e pontuacao auditavel, sem depender de integracoes externas.

## v1 Requirements

### Autenticacao

- [ ] **AUTH-01**: Usuario pode se cadastrar com nome, email e senha
- [ ] **AUTH-02**: Usuario pode entrar com email e senha
- [ ] **AUTH-03**: Sessao do usuario autenticado persiste durante a navegacao
- [ ] **AUTH-04**: Sistema aplica rate limit no login

### Cupons E Checkout

- [ ] **CUP-01**: Usuario pode iniciar checkout simulado para compra de cupom
- [ ] **CUP-02**: Pedido de checkout possui status `pendente`, `pago` ou `cancelado`
- [ ] **CUP-03**: Cupom so e ativado apos confirmacao de pagamento simulado
- [ ] **CUP-04**: Usuario pode possuir multiplos cupons
- [ ] **CUP-05**: Cada cupom mantem conjunto independente de apostas

### Competicao Mockada

- [ ] **COMP-01**: Administrador pode cadastrar torneio mockado
- [ ] **COMP-02**: Administrador pode cadastrar grupos, selecoes e jogadores
- [ ] **COMP-03**: Administrador pode cadastrar fases, rodadas e jogos
- [ ] **COMP-04**: Administrador pode lancar resultados dos jogos

### Apostas

- [ ] **APO-01**: Usuario pode apostar placares dos jogos da fase de grupos por cupom
- [ ] **APO-02**: Sistema deriva automaticamente a classificacao dos grupos a partir dos palpites da fase de grupos
- [ ] **APO-03**: Usuario pode apostar artilheiro por cupom
- [ ] **APO-04**: Usuario pode apostar placares do mata-mata por cupom
- [ ] **APO-05**: Usuario pode apostar classificados do mata-mata por cupom
- [ ] **APO-06**: Sistema deriva campeao, vice e terceiro colocado do bracket do proprio cupom
- [ ] **APO-07**: Usuario pode editar apostas ate o prazo de fechamento
- [ ] **APO-08**: Backend bloqueia criacao e edicao de aposta fora do prazo
- [ ] **APO-09**: Sistema registra log de criacao e edicao de apostas

### Pontuacao E Ranking

- [ ] **PONT-01**: Administrador pode configurar regras de pontuacao no banco
- [ ] **PONT-02**: Sistema calcula pontos com base apenas nas regras configuradas
- [ ] **PONT-03**: Sistema registra eventos de pontuacao por cupom
- [ ] **PONT-04**: Sistema consolida pontuacao total por cupom
- [ ] **PONT-05**: Sistema recalcula pontuacao apos alteracao de resultados por meio de jobs do Laravel
- [ ] **PONT-06**: Sistema exibe ranking geral por cupom
- [ ] **PONT-07**: Sistema aplica criterios de desempate definidos

### Administracao

- [ ] **ADM-01**: Usuario administrador acessa painel administrativo protegido
- [ ] **ADM-02**: Administrador pode gerenciar regras de pontuacao
- [ ] **ADM-03**: Administrador pode gerenciar jogos e resultados com validacoes de dominio corretas
- [ ] **ADM-04**: Administrador pode consultar ranking e pontuacoes dos cupons

### Interface

- [ ] **UI-01**: Aplicacao funciona em layout web responsivo
- [ ] **UI-02**: Usuario autenticado consegue navegar entre cupons, apostas e ranking sem refresh manual indevido
- [ ] **UI-03**: Interface administrativa permite operacao completa do MVP sem uso direto do banco

## v2 Requirements

### Pagamentos Reais

- **PAY-01**: Sistema integra PIX real para pagamento de cupons
- **PAY-02**: Sistema valida webhook de pagamento externo

### Integracoes Externas

- **INT-01**: Sistema sincroniza tabela e jogos por API externa
- **INT-02**: Sistema sincroniza jogadores e resultados por API externa

### Expansao Do Produto

- **EXP-01**: Sistema suporta ligas privadas
- **EXP-02**: Sistema envia notificacoes automaticas
- **EXP-03**: Sistema oferece app mobile nativo

## Out of Scope

| Feature | Reason |
|---------|--------|
| PIX real | Adia complexidade operacional e fiscal do MVP |
| APIs esportivas externas | MVP deve validar o fluxo com dados mockados |
| App mobile nativo | Estrategia inicial e web responsiva |
| Chat, convites e recursos sociais | Nao sao essenciais para validar a proposta principal |
| Fantasy por escalacao de time | Escopo atual e de apostas por resultados e bracket |

## Traceability

| Requirement | Phase | Status |
|-------------|-------|--------|
| AUTH-01 | Phase 7 | Pending |
| AUTH-02 | Phase 7 | Pending |
| AUTH-03 | Phase 7 | Pending |
| AUTH-04 | Phase 7 | Pending |
| CUP-01 | Phase 7 | Pending |
| CUP-02 | Phase 7 | Pending |
| CUP-03 | Phase 7 | Pending |
| CUP-04 | Phase 7 | Pending |
| CUP-05 | Phase 6 | Pending |
| COMP-01 | Phase 7 | Pending |
| COMP-02 | Phase 7 | Pending |
| COMP-03 | Phase 7 | Pending |
| COMP-04 | Phase 5 | Pending |
| APO-01 | Phase 7 | Pending |
| APO-02 | Phase 7 | Pending |
| APO-03 | Phase 7 | Pending |
| APO-04 | Phase 6 | Pending |
| APO-05 | Phase 6 | Pending |
| APO-06 | Phase 7 | Pending |
| APO-07 | Phase 6 | Pending |
| APO-08 | Phase 6 | Pending |
| APO-09 | Phase 7 | Pending |
| PONT-01 | Phase 7 | Pending |
| PONT-02 | Phase 7 | Pending |
| PONT-03 | Phase 7 | Pending |
| PONT-04 | Phase 7 | Pending |
| PONT-05 | Phase 5 | Pending |
| PONT-06 | Phase 7 | Pending |
| PONT-07 | Phase 7 | Pending |
| ADM-01 | Phase 7 | Pending |
| ADM-02 | Phase 5 | Pending |
| ADM-03 | Phase 5 | Pending |
| ADM-04 | Phase 06.1 | Pending |
| UI-01 | Phase 7 | Pending |
| UI-02 | Phase 06.1 | Pending |
| UI-03 | Phase 06.1 | Pending |

**Coverage:**
- v1 requirements: 35 total
- Mapped to phases: 35
- Unmapped: 0

---
*Requirements defined: 2026-03-26*
*Last updated: 2026-03-29 after milestone gap planning*
