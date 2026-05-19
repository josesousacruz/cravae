# Phase 4: Pontuacao E Ranking - Context

**Gathered:** 2026-03-27
**Status:** Ready for planning

<domain>
## Phase Boundary

Fechar a operacao do MVP depois que as apostas estiverem completas: pontuar cupons com base nas regras do banco, registrar eventos auditaveis, consolidar pontuacao total, recalcular quando resultados mudarem e expor ranking confiavel por cupom.

</domain>

<decisions>
## Implementation Decisions

- **D-01:** `ServicoPontuacao` existente sera aproveitado e endurecido; a fase prioriza corretude, cobertura e auditabilidade.
- **D-02:** Evento de pontuacao e a unidade auditavel do sistema; consolidado de cupom precisa poder ser reconstruido desses eventos.
- **D-03:** Toda alteracao de resultado de jogo, resultado final de torneio ou regra de pontuacao deve disparar recalculo consistente.
- **D-04:** Ranking continua sendo por cupom, nao por usuario.
- **D-05:** Desempate minimo do MVP: pontuacao total, placares exatos, classificados corretos, acertos derivados do mata-mata e, por ultimo, criterio estavel de ordenacao.
- **D-06:** Campeao, vice e terceiro deixam de ser palpites manuais e passam a ser inferidos do bracket do mata-mata do cupom.
- **D-07:** Frontend de ranking e "meus resultados" pode ser simples; a prioridade e refletir com fidelidade o backend.

</decisions>

<canonical_refs>
## Canonical References

- `.planning/REQUIREMENTS.md` - PONT-02 a PONT-07, ADM-04, COMP-04
- `.planning/ROADMAP.md` - Goal e success criteria da Phase 4
- `backend/app/Services/ServicoPontuacao.php` - motor atual de pontuacao
- `backend/app/Http/Controllers/PainelAdministradorController.php` - pontos de entrada para resultados e regras
- `backend/app/Http/Controllers/TorneioController.php` - ranking publico atual
- `backend/app/Models/EventoPontuacao.php` - ledger atual de eventos
- `frontend/src/views/RankingView.vue` - ranking publico
- `frontend/src/views/CupomView.vue` - tabs de ranking e meus resultados

</canonical_refs>

<specifics>
## Specific Ideas

- O codigo ja recalcula ao salvar resultados e regras, entao o plano deve fortalecer testabilidade e consistencia dos cenarios.
- O ranking atual ja ordena por multiplos campos; a fase deve transformar isso em comportamento confiavel e visivel.
- "Meus resultados" deve virar superficie util para auditoria do proprio cupom.

</specifics>

<deferred>
## Deferred Ideas

- Exportacao de ranking, historico temporal e analytics
- Reprocessamento assinc para grandes volumes

</deferred>

---

*Phase: 04-pontuacao-e-ranking*
*Context gathered: 2026-03-27*
