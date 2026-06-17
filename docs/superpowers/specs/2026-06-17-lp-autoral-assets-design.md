# LP Autoral + Regeneração de Assets — Design

**Data:** 2026-06-17
**Status:** Aprovação pendente

## Problema

A landing page (`frontend/src/views/InicioView.vue`) e seus assets foram copiados de outro
bolão de referência (`bolao.mvalabs.com.br`). As imagens de mockup de celular ainda exibem a
marca **"Bolão AI - Kainan"**, partidas de futebol de clubes brasileiros (Vasco x Bahia) e a
identidade "Bolão aí!". O troféu decorativo é uma taça genérica, não a taça da Copa usada no
chaveamento do próprio sistema. O texto da página descreve a referência, não o que o
InterWorldCup é hoje.

## Objetivo

Deixar a LP com cara autoral do **Inter World Cup**, sem vestígios da referência:

1. Regenerar os 9 mockups de celular a partir de **screenshots reais do app** rodando, dentro
   de molduras de iPhone com identidade IW (titânio esmeralda).
2. Substituir o troféu decorativo pela taça da Copa usada no chaveamento.
3. Restylizar a LP mantendo a estrutura forte de seções, com identidade visual própria.
4. Reescrever todo o conteúdo textual para o que o sistema é hoje.

## Não-objetivos

- Não reestruturar a ordem/quantidade de seções da LP (decisão: restyle, não rebuild).
- Não alterar funcionalidade do app (rotas, API, lógica). Apenas a LP e os assets.
- Seed de demonstração é **somente local** para captura; não vai para produção.

## Decisões (confirmadas com o usuário)

| Decisão | Escolha |
|---|---|
| Método das imagens | Screenshots do app real em molduras de celular |
| Profundidade do redesign | Reestilizar mantendo a estrutura |
| Estilo da moldura | iPhone titânio esmeralda (cara IW) |
| Seed de demo local | Permitido (somente ambiente local) |
| Troféu | Usar `taca-copa-transparente.png` (taça do chaveamento) |

## Mapeamento: tela real → asset

Todos capturados do app rodando com seed (`TorneioMockadoSeeder` já popula 12 grupos, seleções
reais de 2026, 72 jogos de grupos, chaveamento completo e regras de pontuação).

| Asset (`frontend/src/assets/`) | Tela real | Observação |
|---|---|---|
| `Hero-center.webp` | Palpites — Fase de Grupos | peça central, reta |
| `hero-left.webp` | Chaveamento (bracket) | lateral, rotação via CSS |
| `Hero-right.webp` | Ranking | lateral, rotação via CSS |
| `step-01.webp` | Login / cadastro | "Entrar na conta" |
| `step-02.webp` | Checkout / compra de cupom | "Comprar cupom" |
| `step-03.webp` | Palpites fase de grupos | "Registrar palpites" |
| `step-04.webp` | Meus Resultados / pontuação | "Somar pontos" |
| `step-05.webp` | Ranking | "Subir no ranking" |
| `feature-phone-tilted.webp` | Detalhe de partida (ex.: Brasil x Argentina) | inclinação 3D embutida |

## Pipeline de produção das imagens

### Etapa 0 — De-risk (spike)
Antes de produzir os 9, validar com **1 phone** que a composição gera PNG com **fundo
transparente** e que a **inclinação 3D** da feature-phone funciona. Se transparência via
screenshot não for confiável, usar canvas/SVG→PNG como fallback.

### Etapa 1 — Seed de demonstração (local)
Criar um seeder local (`DemonstracaoSeeder`, não incluído no `DatabaseSeeder` de produção, ou
rodado manualmente) que gera:
- Usuário de teste com cupom **ativo**.
- Alguns palpites preenchidos na fase de grupos.
- Um punhado de participantes/cupons com pontuação variada, para o ranking aparecer populado.

### Etapa 2 — Captura
- `php artisan serve --port=8888` + `npm run dev` (5173) com banco seedado.
- Viewport mobile (~390×844) em alta densidade (DPR 2–3) para nitidez.
- Login como usuário de demo; navegar e capturar cada tela alvo via ferramentas de preview.
- Verificar se o app renderiza bandeiras ou siglas das seleções; ajustar enquadramento.

### Etapa 3 — Composição da moldura
- Compositor em browser (canvas/DOM) que envolve cada screenshot numa moldura de iPhone com
  trilho/borda esmeralda, cantos arredondados, notch/ilha.
- 8 molduras retas (fundo transparente) + 1 com perspectiva 3D embutida (feature-phone).
- Exportar para `.webp` (ou `.png` quando precisar de transparência garantida).

### Etapa 4 — Integração
- Substituir os arquivos em `frontend/src/assets/` mantendo os mesmos nomes (imports não
  mudam) — ou ajustar imports se a extensão mudar.

## Troféu

Trocar o `trophyAsset` que hoje aponta para `trophy.webp` por `taca-copa-transparente.png` nos
três usos decorativos do `InicioView.vue` (hero, seção vantagens, footer), com tratamento de
brilho/opacidade coerente. Manter `trophy.webp` no repo (não referenciado) ou remover.

## Restyle autoral da LP (`InicioView.vue`)

Manter a ordem das seções: header → hero → como funciona → vantagens → pontuação → perfis de
uso → cobertura → FAQ → footer.

Trabalhar identidade visual própria (sem reescrever a arquitetura da página):
- Refinar o tratamento de marca IW (logo, badge, tipografia, detalhes esmeralda).
- Reescrever **todo** o texto (headlines, subtítulos, passos, benefícios, FAQ, footer) focando
  no que o sistema é: cupons independentes, palpites por fase, chaveamento visual, pontuação
  configurável e auditável, ranking por cupom, painel admin.
- Garantir zero menção/estética que remeta à referência "Bolão AI".

## Verificação

- Rodar o frontend e inspecionar a LP no preview (desktop + mobile).
- Conferir que todos os 9 assets exibem conteúdo InterWorldCup (nenhum "Bolão AI", nenhum jogo
  de clube brasileiro fora de contexto).
- Conferir o troféu da Copa nas três posições decorativas.
- `npm run build` sem erros.

## Riscos

- **Transparência/inclinação 3D na composição** — mitigado pelo spike da Etapa 0.
- **Qualidade visual das telas** (bandeiras, densidade de dados) — mitigado pelo seed de demo.
- **Nitidez dos mockups** — mitigado capturando em DPR alto.
