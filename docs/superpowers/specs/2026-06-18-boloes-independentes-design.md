# Bolões Independentes — Design

**Data:** 2026-06-18
**Status:** Aprovado para planejamento
**Contexto:** Hoje existe um único bolão (Copa 2026 inteira). Queremos suportar
múltiplos bolões independentes e simultâneos — o primeiro caso de uso é um bolão
apenas da fase mata-mata, mas a capacidade deve ser genérica (qualquer
competição, qualquer escopo de fases).

---

## 1. Conceito central: Bolão = Torneio

Não criamos uma entidade nova. Um **bolão é um registro na tabela `torneios`**.
A tabela já carrega tudo que um bolão precisa: `nome`, `edicao`, `status`,
`valor_cupom`, `compras_abertas`, `data_inicio`, `data_fim`, e relações próprias
de `fases`, `jogos`, `selecoes`, `grupos`, `regras_pontuacao` e `resultado`.

**Nomenclatura:** a tabela e os models continuam `Torneio` no código (interno).
A palavra "Bolão" aparece **somente na UI e nas rotas públicas** (ex.:
`/api/boloes`, tela "Bolões"). Não renomeamos o domínio — renomear seria caro e
arriscado, sem ganho funcional.

**Por que funciona:** a maior parte do schema já é chaveada por `torneio_id`
(`apostas`, `fases`, `jogos`, `selecoes`, `grupos`, `regras_pontuacao`,
`resultados_torneio`). Ranking e pontuação já filtram por torneio. O bolão de
mata-mata é simplesmente um torneio que só tem as fases oitavas → final.

---

## 2. Mudanças de schema

Cada bolão é totalmente **independente** (decisão do usuário): jogos, seleções e
resultados próprios. Mesmo sendo a mesma Copa, o bolão de mata-mata terá seus
próprios jogos e resultados lançados separadamente.

Duas FKs novas, ambas para `torneios`:

| Tabela              | Coluna nova   | Regra                                            |
|---------------------|---------------|--------------------------------------------------|
| `pedidos_checkout`  | `torneio_id`  | FK, define o bolão da compra (e o valor cobrado) |
| `cupons`            | `torneio_id`  | FK, o bolão a que o cupom pertence               |

- O cupom **herda** `torneio_id` do pedido no momento da geração.
- Campos `valor_cupom` e `compras_abertas` já existem em `torneios` — nada a
  fazer ali.

### Ciclo de vida (`status` do torneio)

- `rascunho` — em montagem, invisível ao usuário.
- `publicado` — **bolão ativo**: aparece na lista, aceita compra (se
  `compras_abertas`) e palpites.
- `encerrado` — sai da lista de ativos, vai para a aba "Encerrados"; ranking
  final continua acessível.

---

## 3. Backend: eliminar os `latest('id')`

Cinco arquivos hoje assumem "o torneio mais recente". Todos passam a receber o
torneio **explicitamente**.

- **Nova rota** `GET /api/boloes` — lista torneios `publicado` (ativos) e
  `encerrado`, separados por status. Cada item: `id`, `nome`, `edicao`,
  `valor_cupom`, `compras_abertas`, `status`, e link/ID para ranking.
- `ServicoCheckout::criarPedido(Usuario, Torneio, ?Cupom)`
  ([ServicoCheckout.php:29](../../../backend/app/Services/ServicoCheckout.php)) —
  valor vem de `$torneio->valor_cupom`; cupom gerado em `marcarComoPago`/
  `marcarCupomComoPago` herda `torneio_id` do pedido.
- `PedidoCheckoutController` — recebe `torneio_id` na requisição; valida
  `$torneio->compras_abertas` do bolão escolhido (não mais o global/latest).
- `CupomController` — escopa cupons/eventos por torneio.
- `TorneioController` — ranking e detalhes já recebem `{torneio}`; remover os
  `latest('id')` de fallback.

Validação de prazos de aposta continua **só no backend** (convenção do projeto),
agora escopada ao torneio do cupom.

---

## 4. Criação de bolão: seeder dedicado por caso

Não haverá command interativo. **Quando o usuário fornecer os jogos e horários**
de um bolão novo, escrevemos um **seeder dedicado** para aquele caso, que cria:

1. O `Torneio` (nome, edição, valor, datas, `status = publicado`).
2. As **fases** do escopo escolhido (ex.: só oitavas→final para o mata-mata).
3. As `selecoes`, `grupos` (se houver) e `jogos` com seus horários.
4. As regras de pontuação, via o helper de template (seção 5).

**Helper reutilizável (parte da arquitetura):** uma função/serviço
`aplicarTemplatePontuacao(Torneio $torneio)` que insere o conjunto-base de
`RegraPontuacao`. Todo seeder novo chama esse helper em vez de duplicar regras.

---

## 5. Template de pontuação copiado

Ao criar um bolão, um **conjunto-base** de `RegraPontuacao` é **copiado** para o
torneio (não referenciado). Cada bolão fica com regras próprias e editáveis,
independentes dos demais. O template vive em um único lugar (o helper da seção 4)
para manter consistência. Constraint atual `unique(torneio_id, fase_id, chave)`
já garante isolamento.

---

## 6. Admin: seletor de bolão

O painel ganha um **dropdown de bolão** no topo. Todas as ações passam a operar
sobre o torneio selecionado:

- Lançar resultados de jogos.
- Abrir/fechar compra (`compras_abertas` por bolão).
- Ver ranking e recalcular pontuação.

Remove os `latest('id')`/`firstOrFail` de
[PainelAdministradorController.php](../../../backend/app/Http/Controllers/PainelAdministradorController.php)
(linhas ~69, ~126, ~208, ~282), passando o torneio escolhido.

---

## 7. Frontend: lista de bolões

- **Nova seção "Bolões"**: cards dos bolões **ativos** (nome, valor, botão
  "Comprar" quando `compras_abertas`, link "Ranking"). **Aba "Encerrados"** lista
  os bolões com `status = encerrado`.
- Views existentes (Cupom, Ranking, Painel) **parametrizadas por `torneio_id`**
  na rota — o usuário sempre opera dentro de um bolão escolhido.
- Tema escuro / Tailwind conforme convenção; layout fiel às referências quando
  houver mockup.

---

## 8. Plano de migração de produção ⚠️

A coluna `torneio_id` em `cupons`/`pedidos_checkout` **não pode** nascer
`NOT NULL` direto — quebraria os registros já existentes.

- **Dev:** `migrate:fresh --seed`. Simples, sem dados a preservar.
- **Produção (deploy à parte, documentado):**
  1. Migration adiciona `torneio_id` **nullable**.
  2. **Backfill**: todos os cupons/pedidos atuais recebem o `id` da Copa 2026
     (o torneio existente).
  3. Migration seguinte torna `torneio_id` **NOT NULL** + FK.

---

## 9. Testes

- Atualizar fluxos existentes para torneio explícito: `CheckoutFluxoTest`,
  `MvpFluxoApiTest`, `FechamentoApostasTest`.
- Novos testes de isolamento entre dois bolões coexistindo:
  - Cupons separados por bolão.
  - Rankings independentes.
  - `compras_abertas` independente (um vendendo, outro fechado).
  - Valor de cupom correto por bolão no checkout.
  - Pontuação/regras isoladas.

---

## 10. Regra do mata-mata: redesenho "pela realidade"

Vale para **o bolão atual (Copa inteira) e para qualquer bolão novo**. Substitui o
modelo atual de "bracket de fantasia".

### Problema do modelo atual

Hoje o chaveamento do mata-mata é **derivado dos palpites de grupo de cada cupom**
([ServicoBracketCupom](../../../backend/app/Services/ServicoBracketCupom.php)):
o sistema calcula quem o usuário "classificou", encaixa os melhores terceiros por
backtracking e propaga vencedores fase a fase. O bracket não é persistido — é
recalculado on-demand. Consequências:

1. **Erro ao mudar resultado de grupo** — a regeneração do bracket pode lançar
   `ValidationException` no backtracking dos terceiros; há lógica de
   classificação duplicada (backend + frontend) que pode divergir.
2. **Regra frágil** — se o usuário erra os grupos, o confronto que ele previu no
   mata-mata não existe na realidade; palpite e realidade ficam entrelaçados.

### Novo modelo (decidido)

- **Fonte de verdade = realidade.** Os confrontos do mata-mata são os **reais**.
  O usuário palpita o **placar dos jogos que realmente acontecem**.
- **Fase de grupos** pontua por conta própria (placar/classificado vs resultado
  real) — já é assim, permanece.
- **Confrontos definidos/confirmados pelo admin.** Ao lançar resultados, o admin
  define quem avançou e monta os confrontos da próxima fase (o sistema pode
  sugerir pelos resultados, mas o admin confirma). Confirmar = preencher os times
  reais (`jogos.selecao_mandante_id`/`selecao_visitante_id`) daquela fase.
- **Abertura por fase.** Os palpites de uma fase do mata-mata **só abrem quando
  seus confrontos reais são confirmados** — oitavas após os grupos, quartas após
  as oitavas, etc. Combina com o fechamento-por-dia já existente (1h antes do
  primeiro jogo).
- **Pontuação do mata-mata.** Compara o palpite (placar + quem avança no empate)
  com o **resultado real do jogo real**. Direto, igual aos grupos — sem bracket
  derivado.
- **Bônus campeão + vice + 3º.** Palpite único por cupom, cravado no início e
  **fechado junto com a fase de grupos**. Pontua contra o pódio real
  (`resultado_torneio`, que já existe). Reaproveita a estrutura
  `campeao/vice/terceiro` já presente ([ResumoBracketCupom](../../../backend/app/Services/ServicoBracketCupom.php)),
  agora como palpite explícito (não mais derivado do bracket).
- **Aba "Chaveamento"** vira a **visualização do bracket REAL**, preenchendo a
  cada fase. Some o bracket de fantasia.

### O que é aposentado

- Derivação de fantasia em `ServicoBracketCupom` (classificação a partir de
  palpites, backtracking de terceiros, propagação de vencedores-por-palpite) como
  **fonte da estrutura** do mata-mata.
- Lógica de classificação duplicada no frontend (`classificacaoGrupos`) para fins
  de chaveamento.
- `selecao_classificada_id` no palpite deixa de estruturar o bracket; continua só
  como "quem avança" do jogo real (para pontuar), referente aos times reais.

### Impacto no bug atual

Mudar um resultado de grupo passa a **apenas re-pontuar os grupos** (e, se for o
caso, influenciar quais classificados o admin confirma). Sem regeneração de
bracket por cupom → **sem a exception**. O entrelaçamento some — por isso não
investimos em consertar o modelo antigo à parte.

### Admin (ações novas)

- "Confirmar confrontos da fase X" — define os times reais dos jogos daquela fase
  (com sugestão pelos resultados), abrindo os palpites.

### Transição de dados

Palpites de eliminatória do modelo de fantasia (feitos contra times derivados)
perdem o sentido no novo modelo. Como os palpites de mata-mata passam a abrir só
quando a fase real é confirmada — e a fase de grupos ainda está em curso —, na
prática ainda não há palpites de eliminatória "válidos" a preservar. Dev:
`migrate:fresh`. Produção: descartar/ignorar palpites de eliminatória antigos no
mesmo plano de backfill da seção 8.

### Bolão só de mata-mata

Encaixa naturalmente: é um torneio cujas fases são só eliminatórias. O admin
confirma os confrontos de cada fase conforme a competição real avança; o usuário
palpita os placares. O bônus campeão/vice/3º é opcional por bolão.

---

## Decisões registradas

| Tema                       | Decisão                                                        |
|----------------------------|---------------------------------------------------------------|
| Modelo                     | Bolão = Torneio (sem entidade nova); `torneio` interno, "Bolão" na UI |
| Independência de jogos     | Cada bolão tem jogos/seleções/resultados próprios             |
| Cupom                      | Totalmente separado por bolão (`cupons.torneio_id`)           |
| Valor do cupom             | Por bolão (`valor_cupom` já existe)                            |
| Abertura/palpites          | Usuário compra e palpita assim que o bolão é criado/publicado |
| Criação                    | Seeder dedicado por caso, escrito quando o usuário passa jogos/horários |
| Pontuação                  | Template padrão copiado via helper na criação                 |
| Compra aberta/fechada      | Por bolão (`compras_abertas` por torneio)                     |
| Admin                      | Seletor de bolão no painel; fim do `latest('id')`             |
| Frontend                   | Lista de bolões ativos + aba "Encerrados"                     |
| Dados existentes           | Dev: migrate:fresh. Produção: backfill em 3 passos            |
| Mata-mata: fonte de verdade| Pela realidade (confrontos reais), não mais bracket de fantasia |
| Mata-mata: confrontos      | Admin define/confirma (sistema sugere pelos resultados)       |
| Mata-mata: abertura        | Por fase, quando os confrontos reais são confirmados          |
| Mata-mata: bônus           | Palpite de campeão+vice+3º, fecha junto com os grupos         |
| Mata-mata: chaveamento     | Aba vira bracket REAL preenchendo por fase; some o de fantasia |
| Bug do resultado de grupo  | Resolvido pela mudança de regra (sem regeneração de bracket)  |
| Mata-mata: motor real      | `ServicoResultadosTorneio::participantesDoJogo(Jogo)` já entrega os times reais; substitui a fantasia |
| Bônus: armazenamento       | Uma aposta `tipo='podio'` (conteudo: campeao/vice/terceiro selecao_id); fecha em `data_inicio - 1h` |
| Grupos × mata-mata         | Desacoplados: mata-mata abre pela realidade, sem exigir palpites de grupo do usuário |

---

## Sequenciamento

### Entrega 1 (este plano) — núcleo

Bolão = Torneio (seeder por caso), cupom separado por bolão, fim dos
`latest('id')`, lista de bolões + aba Encerrados, seletor de bolão no admin, e o
**mata-mata pela realidade** (admin confirma confrontos, abertura por fase, bônus
campeão/vice/3º, bracket real). Inclui a transição de dados (seção 8 + abaixo).

### Fase 2 (próxima, logo após a Entrega 1)

Comprometidos e priorizados, fora da Entrega 1 para não atrasar o núcleo. Dois
deles revisitam decisões da Entrega 1 (cupom separado, bolão independente):

- UI admin de criação/edição de bolão (hoje criação é via seeder).
- "Cupom da copa vale no mata-mata" / cupons compartilhados entre bolões.
- Reaproveitamento de jogos/resultados entre bolões (ex.: mata-mata reusar
  resultados reais da Copa).
- Propagação automática de classificados reais (incl. melhores terceiros, regras
  FIFA) — substitui a confirmação manual do admin.

### Fora de escopo (YAGNI)

- Palpite de artilheiro no novo bolão (escopo = placar dos jogos + bônus
  campeão/vice/3º).

## Transição de dados de palpites de eliminatória

Os palpites de mata-mata do modelo antigo (derivados do bracket de fantasia)
perdem o sentido. Estado atual do banco (dev): **0** apostas
`placar_jogo_eliminatoria`; todas as 171 apostas são `placar_jogo_grupos` (que
permanecem válidas). A migration de transição **remove** quaisquer apostas
`tipo='placar_jogo_eliminatoria'` e recalcula a pontuação. Sem risco de quebra: o
que lançava exception era a derivação do bracket, que é aposentada.

**Armazenamento do bônus campeão/vice/3º:** hoje campeão/vice/terceiro são
**derivados** (não salvos). No novo modelo viram palpite explícito — precisam de
armazenamento próprio (novo `tipo` de aposta, ex.: `podio`, ou colunas no cupom).
O enum já tem um `artilheiro` ocioso como referência de padrão.
