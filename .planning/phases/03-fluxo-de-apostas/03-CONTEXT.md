# Phase 3: Fluxo De Apostas - Context

**Gathered:** 2026-03-27
**Status:** Ready for planning

<domain>
## Phase Boundary

Fechar o fluxo funcional de apostas por cupom para o MVP. Esta fase cobre entrada, edicao e persistencia das apostas de grupos, artilheiro e mata-mata progressivo, com bloqueio real no backend por prazo e log auditavel de criacao/edicao.

O foco desta fase e funcionamento confiavel. Ajustes visuais entram apenas quando forem necessarios para destravar o uso real do produto.

</domain>

<decisions>
## Implementation Decisions

### Prioridade de entrega
- **D-01:** Backend continua sendo a fonte de verdade para validacao de tipo de aposta, ownership do cupom e prazo de fechamento.
- **D-02:** O frontend pode mostrar estados de bloqueio e prazo, mas nunca decide sozinho se uma aposta pode ou nao ser salva.
- **D-03:** A fase deve ser planejada para aproveitar o que ja existe em `ServicoApostas`, `ServicoFechamentoApostas`, `ApostaController` e `CupomView`, reforcando e completando o comportamento em vez de reescrever do zero.

### Escopo funcional
- **D-04:** Cada cupom mantem um conjunto independente de apostas em todos os tipos suportados.
- **D-05:** Fase de grupos precisa permitir placares por jogo e artilheiro.
- **D-06:** Nao existe mais aba ou fluxo separado de classificacao de grupos.
- **D-07:** Mata-mata precisa permitir placar da partida e, em caso de empate, penalidades + classificado.
- **D-08:** Nao existe mais aba de palpites finais manuais; campeao, vice e terceiro passam a ser derivados do proprio bracket do mata-mata do cupom.
- **D-09:** Toda criacao e toda edicao relevante de aposta precisam registrar log auditavel.

### Fechamento e edicao
- **D-10:** Jogos de grupos fecham por rodada (ou fallback pela hora do jogo menos 1h).
- **D-11:** Jogos eliminatorios fecham pelo inicio do proprio jogo, mantendo o backend como fonte de verdade.
- **D-12:** Artilheiro permanece como escolha separada e continua fechando antes do inicio do torneio, salvo ajuste futuro.
- **D-13:** Oitavas so desbloqueiam quando todos os jogos da fase de grupos daquele cupom tiverem palpite valido.
- **D-14:** Quartas, semifinais e finais desbloqueiam imediatamente quando a fase anterior estiver completamente preenchida no cupom.

### UX minima necessaria
- **D-15:** A tela do cupom precisa permitir preencher todos os tipos exigidos pelo MVP sem navegacao quebrada.
- **D-16:** Mobile precisa funcionar em largura de tela sem overflow horizontal destrutivo, mas sem perseguir polish visual alem do necessario para uso.
- **D-17:** Autosave continua aceitavel desde que o backend valide corretamente e o usuario receba feedback claro de salvo/bloqueado.

### Fora desta fase
- **D-18:** Calculo final de pontos, reprocessamento massivo, ranking consolidado e auditoria operacional completa ficam na Phase 4.

</decisions>

<canonical_refs>
## Canonical References

### Requirements
- `.planning/REQUIREMENTS.md` - APO-01 a APO-09, CUP-05, UI-02
- `.planning/ROADMAP.md` - Goal e success criteria da Phase 3

### Existing Backend
- `backend/app/Http/Controllers/ApostaController.php` - endpoint atual de lote e gatilho de recalculo
- `backend/app/Http/Requests/SalvarApostasRequest.php` - validacao atual, ainda generica
- `backend/app/Services/ServicoApostas.php` - normalizacao dos tipos e persistencia com log
- `backend/app/Services/ServicoFechamentoApostas.php` - regra atual de fechamento
- `backend/app/Models/Aposta.php` - shape persistido por cupom
- `backend/app/Models/LogAposta.php` - trilha de criacao/edicao

### Existing Frontend
- `frontend/src/views/CupomView.vue` - tela principal de palpites por cupom
- `frontend/src/tipos.ts` - tipos usados pelo fluxo
- `frontend/src/services/api.ts` - cliente HTTP compartilhado

### Admin / Results
- `frontend/src/views/AdminPainelView.vue` - superficie atual de resultados e regras
- `backend/app/Http/Controllers/PainelAdministradorController.php` - entrada atual de resultados

</canonical_refs>

<specifics>
## Specific Ideas

- A tela atual de cupom ja cobre boa parte do fluxo de grupos e parte do mata-mata, mas o novo desenho exige desbloqueio progressivo de fases.
- O backend atual aceita tipos que deixam de fazer sentido no novo fluxo; o plano deve simplificar o modelo e evitar campos manuais redundantes.
- O fluxo de campeao/vice/terceiro passa a ser inferido do bracket do proprio cupom.

</specifics>

<deferred>
## Deferred Ideas

- Refinamentos visuais de navegacao mobile alem do minimo operacional
- Insights sociais, membros ou ligas privadas
- Melhorias de performance e cache do ranking alem do necessario para o MVP

</deferred>

---

*Phase: 03-fluxo-de-apostas*
*Context gathered: 2026-03-27*
