# Design — Segundo Bolão: Mata-Mata da Copa 2026

**Data:** 2026-06-25
**Status:** Aprovação pendente do usuário

## 1. Visão e escopo

Criar um **segundo bolão independente**, focado **exclusivamente na fase de mata-mata** da Copa do Mundo 2026 (de 32avos até a final). Ele coexiste com o bolão atual (fase de grupos + mata-mata derivado por cupom) sem interferência.

Diferença conceitual central: no bolão atual cada cupom tem **seu próprio** chaveamento, derivado dos palpites do usuário na fase de grupos. No bolão mata-mata **não há fase de grupos** — existe **um único chaveamento real e compartilhado por todos**, que vai sendo preenchido com os times reais conforme a fase de grupos da Copa é decidida e a API publica os confrontos. Todos apostam nos mesmos jogos reais.

**Inclui também (escopo ampliado):** o **bolão atual** já tem 32 jogos reais de mata-mata como placeholders, mas seus **resultados não são sincronizados da API hoje** (a config cobre só as rodadas 1–3 de grupos) — o admin lança manualmente. O sync de knockout que este design introduz é uma **capacidade genérica por torneio** e fecha esse gap nos **dois** bolões.

### Decisões travadas

| Tema | Decisão |
|------|---------|
| Conteúdo do bolão | Só mata-mata (32avos → final). Sem fase de grupos. |
| Estrutura inicial | Esqueleto do bracket criado por **seeder/command** (não CRUD de admin). |
| Preenchimento dos times | **Dirigido pela API (TheSportsDB)** — Approach A, com override manual do admin como rede de segurança. |
| Modelo de aposta | **Por confronto**: abre quando os dois times do jogo são definidos; fecha no kickoff. Reaproveita os tipos de aposta de mata-mata existentes (placar/classificado/pênaltis). |
| Acesso/compra | **Cupom separado, compra independente** (preço, ranking e checkout próprios por bolão). |
| UX do usuário | **Seletor de bolão no header (hub)**; cupons/ranking escopados ao bolão ativo. |
| UX do admin | **Entrada separada por bolão**: tela inicial lista os bolões → entra em um → painel escopado àquele bolão (rota própria). |

### Fora de escopo (YAGNI)

- CRUD de admin para criar torneios arbitrários (o bolão nasce de seeder).
- Combo/desconto de compra entre bolões.
- Palpite de fase de grupos no 2º bolão.
- Webhook em tempo real (o agendamento por cron cobre a atualização).

## 2. Arquitetura

### 2.1 Reusa sem alteração de schema

O banco **já é multi-torneio** (todas as tabelas relevantes têm `torneio_id`). Reaproveitamos:

- `torneios`, `selecoes` (com `id_externo`), `fases` / `rodadas` / `jogos` (times mandante/visitante **nullable** já suportados), `id_evento_externo` em `jogos`, `resultados_jogos`, `regras_pontuacao` por torneio.
- `cupons` / `pedidos_checkout` por torneio (`torneio_id`).
- Motor de pontuação `ServicoPontuacao` + `RecalcularPontuacaoTorneioJob`.
- Tipos de aposta de mata-mata e `ServicoApostas` / `ServicoFechamentoApostas`.
- Frontend multi-bolão já existente: `BoloesView` + `BolaoCard`, rota `/boloes`, checkout ciente de `torneio_id`.
- Cliente `ServicoTheSportsDb` e o de-para sigla→`idTeam` em `config/thesportsdb.php`.

### 2.2 Componentes novos

1. **Seeder/command do bolão mata-mata** — cria:
   - 1 torneio (tipo mata-mata) com `status` e `compras_abertas` controláveis;
   - 48 seleções com `id_externo` (reaproveitando o mapa sigla→idTeam da config);
   - fases: 32avos, 16avos, oitavas, quartas, semifinal, disputa de 3º lugar, final;
   - rodadas correspondentes;
   - esqueleto de jogos placeholder (times nulos) cobrindo o bracket inteiro;
   - regras de pontuação só de knockout: `classificado_mata_mata`, `classificado_e_placar_mata_mata`, `campeao`, `vice_campeao`, `terceiro_colocado`. **`artilheiro` fica de fora** por padrão (é um palpite que abrange o torneio inteiro, incluindo a fase de grupos, que este bolão não acompanha).

2. **Sync de knockout (genérico, serve os dois bolões)** — estende a config de rodadas e a lógica de casamento para os jogos de mata-mata. O `jogos:vincular-eventos` atual casa por **par de times** e cobre só rodadas 1–3. O sync de knockout precisa lidar com **duas estratégias de descoberta de participantes**, conforme o torneio:
   - **Torneio com fase de grupos (bolão atual):** os participantes reais já são **derivados internamente** por `ServicoResultadosTorneio` (classificação dos grupos + vencedores anteriores). O sync **consulta esses participantes derivados** e casa o jogo ao evento da API **por par de times**, trazendo apenas o **resultado**. Não precisa inventar quem joga.
   - **Torneio mata-mata puro (2º bolão):** sem fase de grupos, **quem joga vem da API** — o sync casa o evento ao jogo-placeholder por **rodada + ordem/data**, atribui os times (via `id_externo`) e, quando encerra, grava o resultado.
   - Em ambos, ao gravar resultado, dispara `RecalcularPontuacaoTorneioJob`.

3. **Trava de aposta por jogo** — `ServicoApostas` só aceita aposta num jogo quando **ambos os times estão definidos** e o prazo (kickoff) ainda não passou. Caso contrário, jogo bloqueado / rótulo "A definir".

4. **Scheduler (cron)** — agendar em `routes/console.php` (ou `Console/Kernel`) a execução periódica de: vincular eventos, sync de knockout e sync de resultados. Fecha o gap atual de "nada dispara o sync".

### 2.3 Pesquisa pendente (resolver na fase de planejamento)

- **Round codes** da TheSportsDB para o knockout 2026 (32avos→final) no free tier — confirmar quais `intRound`/endpoints retornam os confrontos eliminatórios (usuário relatou que 32avos já saiu na API).
- Estratégia exata de casamento evento↔placeholder (por `intRound` + ordenação por data/idEvent vs. `ordem_na_fase`).
- Confirmar que `ServicoPontuacao` calcula corretamente um torneio **sem** fase de grupos.
- **Decidir entre duas estratégias ou unificá-las:** manter o casamento por par de times (consultando `ServicoResultadosTorneio`) para o bolão atual e por rodada+ordem para o 2º bolão, **ou** unificar persistindo os participantes derivados na linha do jogo (`selecao_*_id`) para que ambos usem o mesmo casamento por `id_externo`. Avaliar o risco de mexer na derivação dinâmica atual do bolão de grupos.
- Confirmar que casar por par de times funciona com os participantes **derivados dinamicamente** do bolão atual (não persistidos na linha do jogo).

## 3. Fluxo de preenchimento

### 3.1 — 2º bolão (mata-mata puro, Approach A)

1. Seed cria o bolão com bracket vazio (32 jogos placeholder) e 48 seleções com `id_externo`.
2. Cron roda o sync de knockout: busca eventos eliminatórios na TheSportsDB.
3. Para cada evento com confronto definido, casa com o jogo-placeholder (rodada + ordem/data) e seta `selecao_mandante_id` / `selecao_visitante_id` via `id_externo`.
4. Ao setar os times, a aposta daquele jogo **abre** (prazo até o kickoff).
5. Quando o evento encerra, o sync grava `resultados_jogos`, marca o jogo como encerrado e dispara `RecalcularPontuacaoTorneioJob`.
6. **Rede de segurança:** quando a API atrasa ou não entrega o classificado (decisão por pênaltis), o admin usa os endpoints já existentes — vincular evento, salvar/limpar resultado — para corrigir manualmente.

### 3.2 — Bolão atual (grupos + mata-mata)

1. Os participantes reais de cada jogo de knockout já são **derivados** por `ServicoResultadosTorneio` à medida que os resultados de grupos/fases anteriores chegam.
2. Cron roda o sync de knockout: para cada jogo de mata-mata, consulta os participantes derivados e casa com o evento da API **por par de times**.
3. Quando o evento encerra, grava `resultados_jogos` (placar + classificado), marca como encerrado e dispara o recálculo — eliminando o lançamento manual de hoje.
4. Mesma rede de segurança manual do admin permanece disponível.

## 4. UX / UI

### 4.1 Usuário — seletor de bolão no header (hub)

- Um seletor de bolão fixo no header (dropdown). O **bolão ativo** define o escopo de painel, cupons e ranking.
- `/painel` mostra os cupons do **bolão ativo**; `/boloes` continua sendo a vitrine para entrar/comprar em outro bolão.
- Trocar de bolão muda todo o contexto da navegação. A preferência de bolão ativo persiste (ex: localStorage) entre sessões.
- **`CupomView` no modo mata-mata** renderiza o **bracket real compartilhado** (não o derivado por cupom). Cada jogo exibe:
  - times definidos + prazo aberto → input de palpite habilitado;
  - times nulos → card bloqueado com rótulo "A definir";
  - prazo encerrado → bloqueado, exibindo palpite do usuário + resultado real.
  - A variação de renderização é guiada pelo `tipo` do torneio (grupos+mata-mata vs. mata-mata puro), consumindo o estado derivado entregue pelo backend.

### 4.2 Admin — entrada separada por bolão

- O admin abre numa **tela inicial que lista os bolões**.
- Ao entrar em um bolão, o painel passa a operar **somente** sobre aquele torneio, em **rota própria** (ex: `/admin/boloes/:torneio`), com indicação clara do bolão em operação.
- Trocar de bolão = voltar à lista e entrar em outro (isolamento máximo — reduz risco de lançar resultado no bolão errado).
- As operações já existentes (lançar/limpar resultado, vincular evento, regras, cupons, abrir/fechar compras, pódio) passam a receber o `torneio_id` do contexto ativo.

## 5. Tratamento de erros e bordas

- API indisponível/instável: o sync falha de forma idempotente (não corrompe dados); admin pode operar manualmente.
- Evento sem confronto definido: jogo permanece placeholder; nenhuma aposta liberada.
- Empate em mata-mata sem classificado da API: resultado de placar é gravado, classificado fica para decisão manual do admin (pênaltis).
- Aposta após kickoff ou em jogo sem times: rejeitada no backend (validação de prazo só no backend, conforme convenção do projeto).

## 6. Testes

- **Seeder**: cria 1 torneio mata-mata, 48 seleções com `id_externo`, 32 jogos placeholder e regras de knockout.
- **Sync de knockout (2º bolão)**: dado payload da API com confronto definido, o jogo-placeholder correto recebe os times; com placar final, grava resultado e dispara recálculo.
- **Sync de knockout (bolão atual)**: dado um jogo de mata-mata com participantes derivados e um evento da API correspondente encerrado, grava `resultados_jogos` (placar + classificado) e dispara recálculo, sem lançamento manual.
- **Trava de aposta**: rejeita aposta em jogo sem times definidos e após o kickoff; aceita quando válido.
- **Pontuação**: motor calcula corretamente num torneio só de mata-mata.
- **Escopo por torneio**: operações de admin afetam apenas o `torneio_id` em contexto; cupons/ranking não vazam entre bolões.

## 7. Convenções

- Domínio em português; campos estruturais do Laravel em inglês.
- Schema só por migrations (nenhuma alteração de schema prevista — apenas seeder/command).
- Pontuação configurável em banco por torneio/fase (nunca hardcoded).
- Validação de prazos só no backend.
- Frontend em Tailwind, tema escuro.
