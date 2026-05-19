# Phase 05 Research: Hardening De Backend E Admin

**Date:** 2026-03-29
**Status:** Complete

## Objective

Definir a melhor abordagem para tirar o recalculo pesado das requests administrativas, endurecer a validacao de resultados do mata-mata e reduzir acoplamento no backend sem quebrar o MVP funcional.

## Findings

### 1. Recalculo administrativo deve ser assíncrono no backend

Estado atual:
- `PainelAdministradorController` persiste resultado/regra e chama `ServicoPontuacao->recalcularTorneio()` dentro da mesma request.
- `recalcularTorneio()` itera todos os cupons com apostas do torneio e recalcula um a um.

Risco:
- latencia alta na operacao admin
- acoplamento controller -> processamento pesado
- dificuldade de escalar e de reprocessar com rastreabilidade

Abordagem recomendada:
- criar um job dedicado de recalculo por torneio, por exemplo `RecalcularPontuacaoTorneioJob`
- o controller persiste o estado e despacha o job
- o job chama `ServicoPontuacao->recalcularTorneio($torneioAtualizado)`
- testes devem cobrir que a request foi respondida e o job foi enfileirado

### 2. Validacao de classificado no admin precisa usar o dominio real do confronto

Estado atual:
- `SalvarResultadoJogoRequest` so valida `exists:selecoes,id`
- `AdminPainelView` oferece qualquer selecao para jogo eliminatorio
- `PainelAdministradorController` aceita o valor sem verificar se a selecao participa do confronto

Risco:
- classificado impossivel grava no banco
- podio real e recalculo passam a operar sobre dado corrompido

Abordagem recomendada:
- mover a regra para backend, usando um servico que resolva os participantes reais do confronto administrativo
- para jogos de grupos, classificado pode continuar nulo
- para eliminatorias, `selecao_classificada_id` deve ser obrigatoria e pertencer ao par mandante/visitante real daquele jogo
- complementar com ajuste no frontend admin para mostrar so as opcoes validas, mas sem confiar nisso

### 3. `ServicoBracketCupom` precisa operar com escopo do torneio carregado

Estado atual:
- `resolverJogoIdPorOrigem()` busca `Fase` por `slug` globalmente e depois jogos por `fase_id`
- `faseBloqueadaNoCupom()` refaz varias queries para descobrir primeira eliminatoria, fase anterior e contagens
- `carregarTorneioDoCupom()` cai em fallback global se o cupom ainda nao tiver `torneio_id` nas apostas

Risco:
- comportamento incorreto com mais de um torneio
- repeticao de queries durante uma mesma derivacao
- dificuldade de testar e manter

Abordagem recomendada:
- resolver e carregar o torneio explicitamente uma vez
- construir mapas internos de fases/jogos por torneio durante `gerar()`
- reusar esses mapas tanto para derivacao quanto para bloqueio
- evitar qualquer busca por `slug` global fora do contexto do torneio atual

## Plan Shape Recommendation

Planejar em dois blocos:

1. **Infra operacional**
- job de recalculo
- controller admin passa a despachar job
- testes de queue fake + persistencia

2. **Hardening de dominio**
- validacao real de classificado do mata-mata
- ajuste do frontend admin para refletir opcoes corretas
- refactor de `ServicoBracketCupom` para reduzir queries e escopo global
- testes de admin + bracket/pontuacao

## Validation Architecture

### Automated checks
- `php artisan test --filter=MvpFluxoApiTest`
- `php artisan test --filter=BracketCupomApiTest`
- `php artisan test --filter=Admin`
- `php artisan test`

### Required assertions
- request admin despacha job de recalculo em vez de chamar recalculo inline
- jogo eliminatorio rejeita classificado que nao pertence ao confronto
- servico de bracket continua derivando o mata-mata corretamente apos refactor
- ranking/pontuacao continuam consistentes apos resultados salvos via admin

## Conclusion

A fase deve atacar primeiro a infraestrutura operacional do admin e depois o hardening de dominio. O ganho principal e restaurar um backend com responsabilidade correta: request curta, fila no backend, validacao forte no servidor e servicos menos acoplados a queries globais.
