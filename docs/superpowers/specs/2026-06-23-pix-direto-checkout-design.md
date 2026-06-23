# Pix direto na compra de cupom (CheckoutView)

**Data:** 2026-06-23
**Status:** Aprovado (Abordagem A)

## Problema

A tela "Comprar cupom" (`CheckoutView.vue`) só oferece pagamento via **Asaas** ("Gerar Pix"),
que exige CPF/CNPJ e confirma automaticamente. Queremos oferecer também o **Pix direto**
(chave fixa `71997200967`, confirmação manual) como segunda opção — **sem remover o Asaas**.

O fluxo de Pix direto já existe e é usado nos cupons existentes (`ModalPixPagamento.vue`,
`services/pix.ts`, `config/pagamento.ts`). Falta apenas disponibilizá-lo na compra de um cupom novo.

## Objetivo

No checkout, o usuário escolhe entre:
- **Asaas** (existente, inalterado) — gera Pix pelo gateway, confirma sozinho.
- **Pix direto** (novo na tela) — reusa o `ModalPixPagamento`, paga na chave fixa, envia
  comprovante no WhatsApp; o admin libera manualmente.

## Design (Abordagem A — reusar o modal + criar o cupom na hora)

### Backend
- O endpoint de checkout (`POST /pedidos-checkout`) passa a aceitar `forma_pagamento: 'pix_direto'`.
- Quando `pix_direto`: cria o **pedido + cupom em `aguardando_pagamento`** e **NÃO** chama o Asaas
  (pula `prepararPagamentoPix`). Não exige CPF/CNPJ. Retorna o cupom (com `codigo`).
- Quando ausente / `'pix'`: comportamento atual (Asaas), inalterado.
- Liberação reaproveita o que já existe: `marcarCupomComoPago` + lista de "cupons pendentes" no Admin.

### Frontend (`CheckoutView.vue`)
- Abaixo do botão "Gerar Pix" (Asaas), adicionar **"Pagar com Pix direto (confirmação manual)"**.
- Esse botão **não** exige CPF/CNPJ.
- Ao clicar: chama o checkout com `forma_pagamento: 'pix_direto'`, recebe o cupom e abre o
  `ModalPixPagamento` (reuso total) passando `cupomCodigo` e `valor`.
- Asaas e Pix direto coexistem; o usuário escolhe.

### Fluxo de dados
clicar "Pix direto" → backend cria pedido+cupom (`aguardando_pagamento`, `forma_pagamento='pix_direto'`)
→ front abre modal com QR/copia-cola da chave fixa → usuário paga + envia comprovante no WhatsApp
→ admin confirma em "cupons pendentes" (`marcar-pago`) → cupom vira `ativo`.

## Casos de borda
- Fechar o modal sem pagar: o cupom fica em `aguardando_pagamento`; o usuário o reencontra no painel.
- Reabrir/reentrar no checkout não deve duplicar cupom para o mesmo intento (idempotência razoável:
  se já houver pedido/cupom pendente para aquele usuário+torneio sem pagamento, reutilizar).

## Fora de escopo
- Geração de BR Code no backend (o `services/pix.ts` já faz no front).
- Mudanças no fluxo Asaas.
- Confirmação automática do Pix direto (continua manual, por design).

## Testes / verificação
- Backend: `forma_pagamento='pix_direto'` cria cupom em `aguardando_pagamento` sem tocar no Asaas;
  `'pix'`/ausente mantém o fluxo Asaas.
- Front: botão abre o modal com código e valor corretos; CPF/CNPJ não é exigido no caminho direto.
- O cupom pendente aparece para o admin e a liberação manual o ativa.
