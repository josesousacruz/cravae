# Requirements: Inter World Cup

**Defined:** 2026-03-26
**Core Value:** Permitir que cada cupom funcione como uma entrada independente, com apostas completas e pontuação auditável, sem depender de integrações externas.

## v1 Requirements

### Autenticacao

- [ ] **AUTH-01**: Usuário pode se cadastrar com nome, email e senha
- [ ] **AUTH-02**: Usuário pode entrar com email e senha
- [ ] **AUTH-03**: Sessão do usuário autenticado persiste durante a navegação
- [ ] **AUTH-04**: Sistema aplica rate limit no login

### Cupons E Checkout

- [ ] **CUP-01**: Usuário pode iniciar checkout simulado para compra de cupom
- [ ] **CUP-02**: Pedido de checkout possui status `pendente`, `pago` ou `cancelado`
- [ ] **CUP-03**: Cupom só é ativado após confirmação de pagamento simulado
- [ ] **CUP-04**: Usuário pode possuir múltiplos cupons
- [ ] **CUP-05**: Cada cupom mantém conjunto independente de apostas

### Competicao Mockada

- [ ] **COMP-01**: Administrador pode cadastrar torneio mockado
- [ ] **COMP-02**: Administrador pode cadastrar grupos, seleções e jogadores
- [ ] **COMP-03**: Administrador pode cadastrar fases, rodadas e jogos
- [ ] **COMP-04**: Administrador pode lançar resultados dos jogos

### Apostas

- [ ] **APO-01**: Usuário pode apostar placares dos jogos da fase de grupos por cupom
- [ ] **APO-02**: Usuário pode apostar classificados de cada grupo por cupom
- [ ] **APO-03**: Usuário pode apostar artilheiro por cupom
- [ ] **APO-04**: Usuário pode apostar placares do mata-mata por cupom
- [ ] **APO-05**: Usuário pode apostar classificados do mata-mata por cupom
- [ ] **APO-06**: Usuário pode apostar campeão, vice e terceiro colocado por cupom
- [ ] **APO-07**: Usuário pode editar apostas até o prazo de fechamento
- [ ] **APO-08**: Backend bloqueia criação e edição de aposta fora do prazo
- [ ] **APO-09**: Sistema registra log de criação e edição de apostas

### Pontuacao E Ranking

- [ ] **PONT-01**: Administrador pode configurar regras de pontuação no banco
- [ ] **PONT-02**: Sistema calcula pontos com base apenas nas regras configuradas
- [ ] **PONT-03**: Sistema registra eventos de pontuação por cupom
- [ ] **PONT-04**: Sistema consolida pontuação total por cupom
- [ ] **PONT-05**: Sistema recalcula pontuação após alteração de resultados
- [ ] **PONT-06**: Sistema exibe ranking geral por cupom
- [ ] **PONT-07**: Sistema aplica critérios de desempate definidos

### Administracao

- [ ] **ADM-01**: Usuário administrador acessa painel administrativo protegido
- [ ] **ADM-02**: Administrador pode gerenciar regras de pontuação
- [ ] **ADM-03**: Administrador pode gerenciar jogos e resultados
- [ ] **ADM-04**: Administrador pode consultar ranking e pontuações dos cupons

### Interface

- [ ] **UI-01**: Aplicação funciona em layout web responsivo
- [ ] **UI-02**: Usuário autenticado consegue navegar entre cupons, apostas e ranking
- [ ] **UI-03**: Interface administrativa permite operação completa do MVP sem uso direto do banco

## v2 Requirements

### Pagamentos Reais

- **PAY-01**: Sistema integra PIX real para pagamento de cupons
- **PAY-02**: Sistema valida webhook de pagamento externo

### Integracoes Externas

- **INT-01**: Sistema sincroniza tabela e jogos por API externa
- **INT-02**: Sistema sincroniza jogadores e resultados por API externa

### Expansao Do Produto

- **EXP-01**: Sistema suporta ligas privadas
- **EXP-02**: Sistema envia notificações automáticas
- **EXP-03**: Sistema oferece app mobile nativo

## Out of Scope

| Feature | Reason |
|---------|--------|
| PIX real | Adia complexidade operacional e fiscal do MVP |
| APIs esportivas externas | MVP deve validar o fluxo com dados mockados |
| App mobile nativo | Estratégia inicial é web responsiva |
| Chat, convites e recursos sociais | Não são essenciais para validar a proposta principal |
| Fantasy por escalação de time | Escopo atual é de apostas por resultados e classificações |

## Traceability

| Requirement | Phase | Status |
|-------------|-------|--------|
| AUTH-01 | Phase 1 | Pending |
| AUTH-02 | Phase 1 | Pending |
| AUTH-03 | Phase 1 | Pending |
| AUTH-04 | Phase 1 | Pending |
| CUP-01 | Phase 2 | Pending |
| CUP-02 | Phase 2 | Pending |
| CUP-03 | Phase 2 | Pending |
| CUP-04 | Phase 2 | Pending |
| CUP-05 | Phase 2 | Pending |
| COMP-01 | Phase 1 | Pending |
| COMP-02 | Phase 1 | Pending |
| COMP-03 | Phase 1 | Pending |
| COMP-04 | Phase 4 | Pending |
| APO-01 | Phase 3 | Pending |
| APO-02 | Phase 3 | Pending |
| APO-03 | Phase 3 | Pending |
| APO-04 | Phase 3 | Pending |
| APO-05 | Phase 3 | Pending |
| APO-06 | Phase 3 | Pending |
| APO-07 | Phase 3 | Pending |
| APO-08 | Phase 3 | Pending |
| APO-09 | Phase 3 | Pending |
| PONT-01 | Phase 1 | Pending |
| PONT-02 | Phase 4 | Pending |
| PONT-03 | Phase 4 | Pending |
| PONT-04 | Phase 4 | Pending |
| PONT-05 | Phase 4 | Pending |
| PONT-06 | Phase 4 | Pending |
| PONT-07 | Phase 4 | Pending |
| ADM-01 | Phase 1 | Pending |
| ADM-02 | Phase 1 | Pending |
| ADM-03 | Phase 1 | Pending |
| ADM-04 | Phase 4 | Pending |
| UI-01 | Phase 2 | Pending |
| UI-02 | Phase 2 | Pending |
| UI-03 | Phase 1 | Pending |

**Coverage:**
- v1 requirements: 35 total
- Mapped to phases: 35
- Unmapped: 0 ✓

---
*Requirements defined: 2026-03-26*
*Last updated: 2026-03-26 after initial definition*
