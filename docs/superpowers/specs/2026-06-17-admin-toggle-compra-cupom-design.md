# Admin habilita/desabilita a compra de cupom — Design

**Data:** 2026-06-17
**Status:** Aprovado (design)

## Problema

A compra de cupons é controlada por uma flag estática de ambiente
(`config('checkout.compras_abertas')` ← `CHECKOUT_COMPRAS_ABERTAS`, padrão `false`). Mudar o
estado exige editar `.env` e fazer deploy. O frontend, hoje, exibe o aviso "Compra de cupons
encerrada" **fixo no código** (não há botão de compra ativo).

## Objetivo

Permitir que o **administrador** abra/feche a compra de cupons **em tempo real pelo painel**,
sem deploy, e que o participante veja o estado correspondente.

## Não-objetivos

- Agendamento automático de abertura/fechamento por data (YAGNI — apenas toggle manual).
- Gating dos CTAs da landing page (eles tratam de criar conta, não de comprar).

## Decisões (confirmadas)

| Decisão | Escolha |
|---|---|
| Armazenamento da flag | Coluna `compras_abertas` na tabela `torneios` (por torneio) |
| Estado fechado (UX) | Mantém aviso "Compra de cupons encerrada" (botão desabilitado) |
| Default | `false` (igual ao comportamento atual) |

## Backend

### Migration
Adiciona `compras_abertas` (boolean, `default false`, `after('valor_cupom')`) em `torneios`.

### Model `Torneio`
- `fillable`: incluir `compras_abertas`.
- `casts`: `compras_abertas => 'boolean'`.

### Gate de compra
`PedidoCheckoutController::garantirComprasAbertas()` passa a resolver o **torneio publicado**
(o mesmo critério já usado em `ServicoCheckout::criarPedido`:
`Torneio::where('status','publicado')->latest('id')`) e checar `compras_abertas`. Se não houver
torneio publicado ou a flag for `false` → `abort(403, 'A compra de cupons esta encerrada.')`.

Remover a dependência de `config('checkout.compras_abertas')` e o arquivo `config/checkout.php`
(e a referência em `TestCase`).

### Endpoint admin
`PUT /admin/torneios/{torneio}/compras` (grupo `can:acessar-area-admin`), corpo
`{ compras_abertas: boolean }`, validado por FormRequest. Atualiza a flag e retorna o torneio.
Método `PainelAdministradorController::atualizarComprasAbertas`.

### Exposição da flag
- `TorneioController::publico` (`/torneio`): incluir `compras_abertas` no torneio retornado.
- `/admin/dados`: incluir `compras_abertas` para o painel refletir o estado atual.

## Frontend

### Tipos / store
- `tipos.ts`: adicionar `compras_abertas: boolean` ao tipo `Torneio`.
- O store de torneio já consome `/torneio`; a flag fica disponível via `torneio.compras_abertas`.

### PainelView
- Quando `compras_abertas === true`: botão ativo **"Comprar cupom"** que navega para `/checkout`
  (no header do painel e no empty-state).
- Quando `false`: mantém o aviso/disabled atual "Compra de cupons encerrada".

### AdminPainelView
- **Toggle** "Compra de cupons" (aberta/fechada) que chama
  `PUT /admin/torneios/{id}/compras`, reflete o estado atual vindo de `/admin/dados` e dá
  feedback (toast) ao alternar.

## Testes (TDD)

`backend/tests/Feature/CheckoutFluxoTest.php` e `TestCase`:
- Substituir o uso de `config('checkout.compras_abertas')` por definição da flag no torneio.
- Novos casos:
  - `store` e `pagamentoCupom` retornam 403 quando o torneio publicado tem `compras_abertas=false`.
  - `store` cria pedido (201) quando `compras_abertas=true`.
  - `PUT /admin/torneios/{torneio}/compras` altera a flag (admin) e retorna 403 para não-admin.

## Verificação

- `php artisan test` verde (incluindo os novos casos).
- Visual: painel admin alterna o estado; PainelView mostra botão de compra quando aberto e aviso
  quando fechado; `/torneio` retorna a flag.

## Riscos

- **Tests acoplados ao config antigo** — mitigado atualizando `TestCase`/`CheckoutFluxoTest`.
- **Resolução do torneio no gate** — usar exatamente o mesmo critério de `ServicoCheckout` para
  evitar divergência entre "qual torneio" valida o valor e "qual torneio" abre a compra.
