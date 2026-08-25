# Cravaê — Roadmap da transformação (bolão Copa 2026 → fantasy do Brasileirão)

Decisões fundadoras: ver `CLAUDE.md` (raiz). Este roadmap cobre só o delta em relação ao código herdado.

## Fase A — Fundação Brasileirão (dados)

- [ ] Config TheSportsDB do Brasileirão: liga `4351`, temporada corrente, 38 rodadas (`eventsround` r=1..38); manter a config da Copa como referência histórica
- [ ] Migration: tabela `clubes` (ou generalizar `selecoes` → participantes) com `id_externo`, escudo (`strBadge`)
- [ ] Migration: tabela `jogadores` (`id_externo`, `clube_id`, nome, foto cutout, posição, ativo)
- [ ] Command de importação: 20 clubes + elencos (`lookup_all_players`, throttle ~10 req/min) + calendário das 38 rodadas
- [ ] Torneio "Brasileirão Série A" com fases = rodadas (1..38) e regras de pontuação por fase
- [ ] Seeder novo do Cravaê (admin `admin@cravae.com.br`)

## Fase B — Palpite com goleadores

- [ ] Migration: `palpite_goleadores` (aposta_id, jogo_id, lado mandante/visitante, ordinal do gol, jogador_id) — N linhas por palpite conforme o placar
- [ ] Backend: request/validação — quantidade de autores deve bater com o placar palpitado; jogador deve pertencer ao clube do lado; pode repetir jogador
- [ ] Fechamento **por jogo** no horário de início (adaptar `ServicoFechamentoApostas`, que hoje fecha por dia)
- [ ] Frontend `CupomView`: ao definir placar, abre seletor de autor por gol (elenco com foto); editar placar reconcilia os autores já escolhidos
- [ ] Decisões pendentes (grill antes de implementar): gol contra, palpite 0x0, jogador transferido no meio da temporada

## Fase C — Pontuação e resultados

- [ ] `ServicoPontuacao`: níveis exclusivos (resultado < placar exato) + bônus por goleador acertado + bônus cheio (placar exato + todos os autores); valores em banco
- [ ] Definir valores default com o Almir (pendência do grilling)
- [ ] Sync de resultados: `eventsround` (placar) + `lookuptimeline` (gols com autor), throttle ~5 req/min, matching por `idPlayer` ↔ `jogadores.id_externo`
- [ ] Admin: lançamento manual de placar + goleadores (fallback quando a API atrasar), reaproveitando o painel atual
- [ ] Ranking por cupom com desempate (critério a decidir)

## Fase D — Produto e lançamento

- [ ] Rebrand completo: landing do Cravaê (InicioView), ícones PWA regenerados a partir do novo `favicon.svg` (192/512/maskable ainda são os do bolão), e-mails/textos
- [ ] Limpeza da herança da Copa: mata-mata, seleções, `linkedin/`, docs antigos (decidir o que remover vs manter)
- [ ] Repo GitHub novo + deploy (Coolify) + domínio `cravae.com.br` (registro é ação do Almir)

## Estado

- 2026-07-21: fork criado a partir do bolão (código + banco `cravafc`), rebrand inicial aplicado (tema lima, nome, manifest, favicon, CLAUDE.md).
- 2026-07-21 (noite): **Fases A e B essencialmente concluídas + parte da C.**
  - Feito: migration (escudos/fotos/goleadores), `cravae:importar-brasileirao` (torneio id 3, 20 clubes com escudo, 350 jogos, 183 resultados, regras 10/25/+5/+15), `cravae:sincronizar-rodada` (placares + timeline com gol contra), palpite com goleadores no backend (validação por clube, multiset na pontuação, 0x0 = bônus cheio, fechamento POR JOGO em fase `pontos_corridos`), rota `/jogos/{jogo}/elencos`, UI completa no CupomView (rodadas, escudos, seletor de goleador com foto/busca/gol contra, autosave). 104 testes passando (9 novos em `CravaeGoleadoresTest`).
  - **Bloqueio conhecido**: chave FREE da TheSportsDB corta elencos em 10 jogadores e timeline em 5 eventos → goleadores reais só entram com chave premium (~US$9/mês, Patreon). API-Football free NÃO cobre a temporada 2026 (testado com chave real). Ao assinar: trocar `THESPORTSDB_KEY` no .env e rodar `cravae:importar-brasileirao --so-elencos` + `cravae:sincronizar-rodada N --forcar`.
  - Pendente (Fase C/D): admin lançar goleadores manualmente na UI, desativação automática de transferidos (só com elenco premium ≥15), desempate do ranking, seeder/admin próprio, agendamento do sync no scheduler, landing/design completo (o Almir fará o design), PWA icons, repo GitHub + deploy.
