# Design — 3 features rápidas (revelar apostas, aviso, /boloes na entrada)

**Data:** 2026-06-27
**Status:** Aprovado — em implementação

Três features pequenas e independentes. Sem mudança de schema.

## Feature 1 — Revelar a aposta dos outros após o fechamento do jogo

**Quando:** assim que o **prazo de aposta do jogo fecha** (kickoff no mata-mata; prazo do dia/rodada nos grupos), os palpites de todos ficam visíveis.

- **Backend** (`TorneioController::palpiteiros`): hoje retorna `nome` + `cupom_codigo`. Estender para incluir o **palpite** de cada apostador (`placar_mandante`, `placar_visitante`, e `selecao_classificada_id` no mata-mata) **apenas quando o prazo do jogo já fechou** (usar a checagem existente em `ServicoFechamentoApostas`). Enquanto aberto, retorna só os nomes (comportamento atual). A trava é no backend — impossível espiar antes.
- **Frontend** (`CupomView`, popover "Quem palpitou"): quando o jogo está fechado, mostra o placar de cada um (ex.: "Fulano — 2×1"); enquanto aberto, só nomes.
- **Teste (backend):** com prazo aberto, a resposta NÃO traz o palpite; com prazo fechado, traz.

## Feature 2 — Aviso do mata-mata (modal ao entrar, 1×)

- **Modal de boas-vindas** exibido **uma vez por usuário** após o login (controle via `localStorage`, chave de versão do aviso; sem backend).
- **Mensagem:** o mata-mata já está disponível; há um **bolão exclusivo só do mata-mata** — entre e palpite; e continue apostando no **bolão completo** também.
- **CTAs:** "Ver bolões" (→ `/boloes`) e "Agora não" (fecha). Não reaparece após visto.
- Novo componente `AvisoMataMata.vue`, montado no layout autenticado.

## Feature 3 — Entrar em /boloes + inverter botões do card

- **Login** (`EntrarView`): redirecionar para **`/boloes`** em vez de `/painel`.
- **`BolaoCard`:** inverter hierarquia — **"Ver bolão"** vira o botão **principal/grande** (define o bolão ativo e vai ao `/painel`); **"Comprar cupom"** vira **secundário/menor**.

## Fora de escopo (YAGNI)
- Notificações por email/push; aviso editável por admin; persistir "aviso visto" no banco (localStorage basta para um aviso pontual).
