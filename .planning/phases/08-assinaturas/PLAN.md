# Fase 08 — Assinaturas (modelo de negócio)

## Objetivo

Substituir a venda de cupom pelo modelo de assinatura mensal com tier gratuito.
O cupom permanece como entidade (1 por usuário por torneio); a assinatura passa
a controlar a **elegibilidade a prêmios**, não o acesso ao jogo.

## Decisões de domínio (grilling 2026-08-25, validadas pelo Almir)

| # | Decisão | Valor |
|---|---------|-------|
| 1 | Cobrança | Mensal recorrente via **Asaas Assinaturas** (webhook existente confirma) |
| 2 | Preço cheio | **R$ 9,90/mês** (configurável em banco) |
| 3 | Preço fundador | **R$ 4,95/mês (50%) para sempre**, para os **primeiros N assinantes** (N configurável, default 100); cancelou → perde a condição |
| 4 | Cancelamento/inadimplência | Rebaixa a **gratuito**: mantém cupom, palpites e histórico; sai da disputa; voltando a pagar, volta a concorrer dali em diante |
| 5 | Prêmio | Por **ciclo mensal**: concorre quem tem assinatura ativa no mês; pontos do mês contam por inteiro mesmo assinando no meio (padrão Cartola PRO) |
| 6 | Gratuito | Palpita, pontua e aparece no **mesmo ranking** (sem selo); não concorre |
| 7 | Entradas | **1 cupom por usuário** por enquanto; modelo preparado para níveis futuros com múltiplos cupons concorrendo individualmente |
| 8 | Valores | Mensalidade, preço fundador, N de vagas e prêmios ficam em banco |

Pendências deliberadas (não bloqueiam): valores dos prêmios mensais; texto de
regulamento/termos.

## Plano 1 — Modelo de dados

- [ ] Migration `planos`: nome, slug, preco_centavos, preco_fundador_centavos,
      vagas_fundador, max_cupons (default 1), ativo. Seed: "Assinante" 990/495/100.
- [ ] Migration `assinaturas`: usuario_id (unique por plano ativo), plano_id,
      status (`ativa`, `inadimplente`, `cancelada`), fundador (bool),
      preco_centavos_contratado, id_assinatura_asaas, assinada_em, cancelada_em.
- [ ] Migration `ciclos_assinatura`: assinatura_id, competencia (`YYYY-MM`),
      status (`pendente`, `pago`, `falhou`), pago_em, id_cobranca_asaas.
      Elegibilidade do mês = ciclo da competência com status `pago`.
- [ ] Cupom: coluna `origem` (`gratuito` | `assinatura`); cupom gratuito nasce
      `ativo` sem pedido de checkout.
- [ ] Models + relacionamentos + factories.

## Plano 2 — Backend de fluxo

- [ ] `ServicoAssinatura`: contratar (define fundador se `count(fundador ativas) < vagas`),
      cancelar (status + fim no Asaas), rebaixar por inadimplência,
      `elegivelNoMes(usuario, competencia)`.
- [ ] Integração Asaas Assinaturas em `ServicoAsaas` (criar/cancelar subscription,
      ciclo mensal); sandbox: rota de confirmação manual como a atual
      (`confirmar-sandbox`).
- [ ] Webhook: eventos de pagamento de assinatura marcam o ciclo da competência
      como pago e reativam assinatura `inadimplente`.
- [ ] Cadastro/entrada no bolão: cria automaticamente o cupom gratuito
      (1/usuário/torneio ativo). Remover checkout de cupom do fluxo do usuário
      (rotas de compra passam a responder 410 ou redirecionar para assinatura).
- [ ] Endpoint público `GET /planos` com contador de vagas de fundador restantes.
- [ ] Command agendável `assinaturas:fechar-ciclo` — gera ciclos `pendente` da
      competência corrente e rebaixa quem não pagou o ciclo anterior.

## Plano 3 — Elegibilidade no ranking

- [ ] Ranking: flag `assinante` (ciclo pago da competência corrente) por item.
- [ ] Apuração do prêmio mensal: filtro apenas assinantes elegíveis na competência.
- [ ] Testes de elegibilidade: free não concorre; assinou no meio do mês concorre
      com pontos do mês inteiro; inadimplente do ciclo não concorre; reativou,
      concorre no ciclo pago.

## Plano 4 — Frontend

- [ ] Landing: seção de planos (Gratuito × Assinante) com preço fundador e
      contador "restam X vagas de fundador"; CTAs migram de "comprar cupom"
      para "assinar"/"jogar grátis".
- [ ] Painel: cartão de status da assinatura (gratuito → CTA assinar; ativa →
      preço/ciclo/cancelar; inadimplente → regularizar).
- [ ] Ranking: selo de assinante nos itens elegíveis.
- [ ] Checkout de cupom (CheckoutView) substituído pelo fluxo de assinatura.

## Verificação

- [ ] `php artisan test` verde (novos testes de assinatura/elegibilidade).
- [ ] Fluxo sandbox completo no browser: cadastro → cupom grátis → palpita →
      assina (sandbox) → selo aparece → cancela → selo some, palpites intactos.
- [ ] Contador de fundador decrementa e trava em 0 vagas.
