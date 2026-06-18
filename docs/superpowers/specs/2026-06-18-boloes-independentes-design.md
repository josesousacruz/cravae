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

---

## Fora de escopo (YAGNI por enquanto)

- Command/UI de criação de bolão pelo admin (criação fica via seeder).
- "Cupom da copa vale no mata-mata" / cupons compartilhados.
- Reaproveitamento de jogos/resultados entre bolões.
- Palpites de campeão/artilheiro no bolão de mata-mata (escopo = placar dos
  jogos eliminatórios).
