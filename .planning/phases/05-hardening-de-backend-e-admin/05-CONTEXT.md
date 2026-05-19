# Phase 05: Hardening De Backend E Admin - Context

**Gathered:** 2026-03-29
**Status:** Ready for planning
**Source:** Gap closure after `v1.0-MILESTONE-AUDIT.md`

<domain>
## Phase Boundary

Corrigir os gaps criticos do backend e do fluxo administrativo encontrados no audit do milestone. Esta fase cobre a migracao do recalculo de pontuacao para jobs do Laravel, o endurecimento das validacoes do admin para resultados do mata-mata e a reducao de acoplamento/queries repetidas nos servicos que derivam bracket e pontuacao.

Nao inclui remover fila implicita do frontend nem reestruturar a `CupomView`; isso fica na fase 06. Nao inclui tambem o fechamento documental do milestone; isso fica na fase 07.

</domain>

<decisions>
## Implementation Decisions

### Recalculo e operacao admin
- **D-01:** Todo salvamento administrativo que altere resultado de jogo, resultado final do torneio ou regra de pontuacao deve despachar job do Laravel para recalculo, em vez de recalcular tudo de forma sincronica dentro da request.
- **D-02:** A request administrativa deve continuar persistindo o estado principal imediatamente e responder sem ficar bloqueada pelo recalculo integral do torneio.
- **D-03:** O ponto de entrada do admin continua em `PainelAdministradorController`, mas a orquestracao de recalculo deve sair do controller e ir para job/servico dedicados.

### Validacao de dominio
- **D-04:** Resultado administrativo de jogo eliminatorio so pode aceitar `selecao_classificada_id` que pertença aos participantes reais daquele confronto.
- **D-05:** Em jogo de grupos, o classificado deve permanecer opcional ou nulo; a validacao forte de classificado vale para eliminatorias.
- **D-06:** O backend deve ser a fonte de verdade dessa validacao; o frontend admin pode melhorar as opcoes, mas nao decide sozinho.

### Hardening de servicos
- **D-07:** `ServicoBracketCupom` nao deve depender de buscas por fase apenas por `slug` sem escopo forte de torneio.
- **D-08:** O servico deve reduzir queries repetidas durante derivacao do bracket e verificacao de bloqueio, preferindo operar com dados ja carregados do torneio/fases/jogos.
- **D-09:** `ServicoPontuacao` deve continuar usando o bracket derivado, mas sem ficar acoplado a recalculo sincrono no fluxo administrativo.

### the agent's Discretion
- Estrategia exata de job: job unico por torneio, job por cupom, ou fan-out controlado, desde que o recalculo deixe a request e mantenha consistencia
- Como dividir responsabilidades entre controller, job e servicos sem inflar demais a superficie de classes

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Audit e roadmap
- `.planning/v1.0-MILESTONE-AUDIT.md` - gaps que originam esta fase
- `.planning/ROADMAP.md` - objetivo, requisitos e success criteria da fase 5
- `.planning/REQUIREMENTS.md` - requisitos `COMP-04`, `PONT-05`, `ADM-02`, `ADM-03`, `ADM-04`, `UI-03`

### Backend atual
- `backend/app/Http/Controllers/PainelAdministradorController.php` - fluxo administrativo atual e recalculo sincrono
- `backend/app/Http/Requests/SalvarResultadoJogoRequest.php` - validacao atual insuficiente para classificado do mata-mata
- `backend/app/Services/ServicoPontuacao.php` - motor atual e ponto de acoplamento com recalculo
- `backend/app/Services/ServicoBracketCupom.php` - derivacao do bracket e hotspots de queries/escopo

### Testes existentes
- `backend/tests/Feature/MvpFluxoApiTest.php`
- `backend/tests/Feature/BracketCupomApiTest.php`
- `backend/tests/Feature/FechamentoApostasTest.php`

</canonical_refs>

<specifics>
## Specific Ideas

- O audit apontou explicitamente que o admin hoje aceita `selecao_classificada_id` invalida em jogo eliminatorio.
- O audit tambem apontou que `PONT-05` ainda nao esta coerente com a expectativa do produto porque o recalculo administrativo nao usa jobs.
- `ServicoBracketCupom` ja concentra a derivacao do mata-mata por cupom; a fase deve aproveitar isso para validar participantes do confronto tambem no admin.

</specifics>

<deferred>
## Deferred Ideas

- Reestruturacao da `CupomView` e do autosave
- Fechamento formal de `SUMMARY.md`, `VERIFICATION.md` e rastreabilidade do milestone

</deferred>

---

*Phase: 05-hardening-de-backend-e-admin*
*Context gathered: 2026-03-29*
