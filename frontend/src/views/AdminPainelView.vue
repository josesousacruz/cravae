<template>
  <div v-if="!torneio" class="flex items-center justify-center py-20">
    <span class="text-text-muted">Carregando...</span>
  </div>

  <div v-else class="mx-auto max-w-6xl space-y-6">
    <section class="rounded-2xl border border-border bg-bg-card p-6 sm:p-8">
      <p class="mb-2 text-xs uppercase tracking-wider text-text-muted">Administracao</p>
      <h1 class="text-lg font-bold">{{ torneio.nome }} {{ torneio.edicao }}</h1>
      <p class="mt-1 text-sm text-text-secondary">Lance resultados por fase e acompanhe o reprocessamento em segundo plano.</p>

      <div v-if="boloes.length > 1" class="mt-3">
        <label class="mb-1 block text-xs font-medium text-text-muted">Bolão</label>
        <select
          class="rounded-lg border border-border bg-bg-input px-3 py-2 text-sm text-text"
          :value="torneioSelecionadoId ?? ''"
          @change="trocarBolao(Number(($event.target as HTMLSelectElement).value))"
        >
          <option v-for="b in boloes" :key="b.id" :value="b.id">
            {{ b.nome }} {{ b.edicao }}{{ b.status === 'encerrado' ? ' (encerrado)' : '' }}
          </option>
        </select>
      </div>
      <p v-if="mensagem" class="mt-3 rounded-lg bg-primary/10 px-3 py-2 text-sm text-primary">{{ mensagem }}</p>
      <p v-if="erro" class="mt-3 rounded-lg bg-danger/10 px-3 py-2 text-sm text-danger">{{ erro }}</p>

      <div class="mt-4 flex items-center justify-between gap-4 rounded-xl border border-border bg-bg-input px-4 py-3">
        <div>
          <p class="text-sm font-semibold">Compra de cupons</p>
          <p class="text-xs text-text-muted">
            {{ torneio.compras_abertas ? 'Aberta — participantes podem comprar cupons.' : 'Fechada — a compra esta bloqueada.' }}
          </p>
        </div>
        <button
          type="button"
          role="switch"
          :aria-checked="torneio.compras_abertas"
          :disabled="salvandoCompras"
          class="relative inline-flex h-7 w-12 shrink-0 cursor-pointer items-center rounded-full transition disabled:opacity-50"
          :class="torneio.compras_abertas ? 'bg-primary' : 'bg-border'"
          @click="alternarCompras"
        >
          <span
            class="inline-block h-5 w-5 transform rounded-full bg-white transition"
            :class="torneio.compras_abertas ? 'translate-x-6' : 'translate-x-1'"
          />
        </button>
      </div>

      <div class="mt-4 rounded-xl border border-border bg-bg-input px-4 py-3">
        <p class="text-sm font-semibold">Fechamento do pódio</p>
        <p class="text-xs text-text-muted">
          Quando o palpite de campeão, vice e 3º fecha. Deixe vazio para usar o automático
          (1h antes do 1º jogo do mata-mata).
        </p>
        <div class="mt-3 flex flex-wrap items-center gap-2">
          <input
            v-model="fechamentoPodioInput"
            type="datetime-local"
            class="rounded-lg border border-border bg-bg-card px-3 py-2 text-sm text-text [color-scheme:dark]"
          />
          <button
            type="button"
            :disabled="salvandoFechamentoPodio"
            class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white transition hover:bg-primary-hover disabled:opacity-50"
            @click="salvarFechamentoPodio"
          >
            Salvar
          </button>
          <button
            type="button"
            :disabled="salvandoFechamentoPodio || !torneio.data_fechamento_podio"
            class="rounded-lg border border-border px-4 py-2 text-sm text-text-secondary transition hover:text-text disabled:opacity-50"
            @click="limparFechamentoPodio"
          >
            Limpar
          </button>
        </div>
      </div>
    </section>

    <div class="flex overflow-x-auto border-b border-border">
      <button
        v-for="tab in tabs"
        :key="tab.id"
        class="whitespace-nowrap px-4 py-3 text-sm transition cursor-pointer"
        :class="tabAtiva === tab.id ? 'border-b-2 border-primary text-primary font-medium' : 'text-text-muted hover:text-text-secondary'"
        @click="tabAtiva = tab.id"
      >
        {{ tab.nome }}
      </button>
    </div>

    <section v-if="tabAtiva === 'jogos'" class="space-y-4">
      <div class="rounded-2xl border border-border bg-bg-card p-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h2 class="text-lg font-bold">Resultados dos Jogos</h2>
            <p class="mt-1 text-sm text-text-secondary">Selecione a fase, informe o placar e salve. Em empate no mata-mata, escolha o classificado.</p>
          </div>
          <span class="rounded-full border border-primary/30 bg-primary/10 px-3 py-1 text-xs text-primary">
            {{ jogosFiltrados.length }} jogo{{ jogosFiltrados.length === 1 ? '' : 's' }}
          </span>
        </div>

        <div class="mt-4 flex gap-2 overflow-x-auto pb-1">
          <button
            v-for="fase in fasesComJogos"
            :key="fase.id"
            type="button"
            class="whitespace-nowrap rounded-full px-3 py-1.5 text-xs font-medium transition"
            :class="faseSelecionadaId === fase.id ? 'bg-primary text-bg' : 'bg-bg-input text-text-muted hover:text-text'"
            @click="faseSelecionadaId = fase.id"
          >
            {{ fase.nome }}
          </button>
        </div>

        <!-- Seletor de dia dentro da fase (espelha os palpites do cupom) -->
        <div v-if="diasComJogosAdmin.length" class="mt-4 flex gap-1.5 overflow-x-auto pb-1 scrollbar-none">
          <button
            v-for="dia in diasComJogosAdmin"
            :key="dia.data"
            type="button"
            class="relative flex shrink-0 items-center gap-1 rounded-lg px-2.5 py-1.5 text-center transition"
            :class="diaSelecionadoAdmin === dia.data
              ? 'bg-primary text-bg'
              : dia.pendentes > 0
                ? 'bg-bg-input border border-warning/50 text-text-muted'
                : 'bg-bg-input border border-border text-text-muted hover:border-primary/40'"
            @click="diaSelecionadoAdmin = dia.data"
          >
            <span v-if="dia.pendentes > 0 && diaSelecionadoAdmin !== dia.data" class="absolute -top-1 -right-1 h-2 w-2 rounded-full bg-warning" />
            <span class="text-[10px] font-medium uppercase">{{ dia.diaSemana }}</span>
            <span class="text-sm font-bold">{{ dia.diaNumero }}</span>
            <span class="text-[10px] opacity-70"><sup>({{ dia.totalJogos }})</sup></span>
          </button>
        </div>
      </div>

      <div class="grid gap-4">
        <div v-for="jogo in jogosDoDiaAdmin" :key="jogo.id" class="rounded-2xl border border-border bg-bg-card p-5">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
              <p class="text-xs font-semibold uppercase tracking-wider text-primary">
                {{ jogo.fase.nome }} <span class="text-text-muted">#{{ jogo.ordem_na_fase }}</span>
              </p>
              <p class="mt-1 text-xs text-text-muted">
                {{ jogo.grupo?.nome ?? 'Mata-mata' }}
              </p>
            </div>
            <div class="flex items-center gap-2">
              <button
                v-if="jogo.resultado"
                type="button"
                class="rounded-lg border border-border px-4 py-2 text-sm font-medium text-text-secondary transition hover:text-text disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="salvandoJogoId === jogo.id"
                @click="limparResultadoJogo(jogo.id)"
              >
                {{ salvandoJogoId === jogo.id ? 'Limpando...' : 'Limpar' }}
              </button>
              <button
                type="button"
                class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-bg transition hover:bg-primary-hover disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="salvandoJogoId === jogo.id"
                @click="salvarResultadoJogo(jogo.id)"
              >
                {{ salvandoJogoId === jogo.id ? 'Salvando...' : 'Salvar resultado' }}
              </button>
            </div>
          </div>

          <div class="mt-4 grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] sm:items-center">
            <div class="rounded-xl bg-bg-input p-3 text-center sm:text-right">
              <p class="text-xs text-text-muted">Mandante</p>
              <strong class="text-sm">{{ nomeMandanteAdmin(jogo) }}</strong>
            </div>

            <div class="flex items-center justify-center gap-2">
              <input v-model="resultadosJogos[jogo.id].placar_mandante" type="number" min="0" class="!w-16 text-center" placeholder="0" />
              <span class="text-xs font-medium text-text-muted">x</span>
              <input v-model="resultadosJogos[jogo.id].placar_visitante" type="number" min="0" class="!w-16 text-center" placeholder="0" />
            </div>

            <div class="rounded-xl bg-bg-input p-3 text-center sm:text-left">
              <p class="text-xs text-text-muted">Visitante</p>
              <strong class="text-sm">{{ nomeVisitanteAdmin(jogo) }}</strong>
            </div>
          </div>

          <div v-if="jogoExigeClassificado(jogo)" class="mt-4 rounded-xl border border-primary/20 bg-primary/5 p-4">
            <label class="block">
              <span class="mb-1.5 block text-xs text-text-muted">Classificado nos penaltis</span>
              <select v-model="resultadosJogos[jogo.id].selecao_classificada_id">
                <option value="">Selecione o classificado</option>
                <option
                  v-for="selecao in opcoesClassificado(jogo)"
                  :key="selecao.id"
                  :value="String(selecao.id)"
                >
                  {{ selecao.nome }}
                </option>
              </select>
            </label>
          </div>

          <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-border pt-3">
            <span class="text-xs text-text-muted">Evento TheSportsDB</span>
            <span
              v-if="jogo.id_evento_externo"
              class="rounded-md bg-success/10 px-2 py-0.5 text-[11px] font-medium text-success"
            >
              vinculado #{{ jogo.id_evento_externo }}
            </span>
            <span v-else class="rounded-md bg-bg-input px-2 py-0.5 text-[11px] text-text-muted">
              sem vinculo
            </span>
            <input
              v-model="vinculosEvento[jogo.id]"
              type="number"
              min="0"
              placeholder="idEvent"
              class="!w-28 text-center"
            />
            <button
              type="button"
              class="rounded-lg border border-border px-3 py-1.5 text-xs font-medium text-text-secondary transition hover:text-text disabled:cursor-not-allowed disabled:opacity-60"
              :disabled="vinculandoJogoId === jogo.id"
              @click="vincularEvento(jogo.id)"
            >
              {{ vinculandoJogoId === jogo.id ? 'Salvando...' : 'Vincular' }}
            </button>
            <button
              v-if="jogo.id_evento_externo || jogo.selecao_mandante || jogo.selecao_visitante"
              type="button"
              class="rounded-lg border border-danger/40 px-3 py-1.5 text-xs font-medium text-danger transition hover:bg-danger/10 disabled:cursor-not-allowed disabled:opacity-60"
              :disabled="vinculandoJogoId === jogo.id"
              @click="limparSlot(jogo.id)"
            >
              Desvincular e limpar
            </button>
          </div>
        </div>
      </div>
    </section>

    <section v-if="tabAtiva === 'regras'" class="space-y-4">
      <div class="rounded-2xl border border-border bg-bg-card p-6">
        <h2 class="mb-1 text-lg font-bold">Adicionar regra</h2>
        <p class="mb-4 text-sm text-text-secondary">Escolha uma categoria de pontuacao reconhecida e a fase (ou global). So pontua quem usa uma categoria conhecida.</p>
        <div class="grid gap-3 sm:grid-cols-2">
          <label class="block">
            <span class="mb-1.5 block text-xs text-text-muted">Categoria</span>
            <select v-model="novaRegra.chave">
              <option value="">Selecione...</option>
              <option v-for="c in chavesDisponiveis" :key="c.chave" :value="c.chave">{{ c.label }}</option>
            </select>
          </label>
          <label class="block">
            <span class="mb-1.5 block text-xs text-text-muted">Fase</span>
            <select v-model="novaRegra.fase_id">
              <option value="">Global (todas as fases)</option>
              <option v-for="fase in torneio.fases" :key="fase.id" :value="String(fase.id)">{{ fase.nome }}</option>
            </select>
          </label>
          <label class="block">
            <span class="mb-1.5 block text-xs text-text-muted">Nome</span>
            <input v-model="novaRegra.nome" type="text" placeholder="Ex: Placar exato" />
          </label>
          <label class="block">
            <span class="mb-1.5 block text-xs text-text-muted">Pontos</span>
            <input v-model="novaRegra.pontos" type="number" min="0" />
          </label>
          <label class="block sm:col-span-2">
            <span class="mb-1.5 block text-xs text-text-muted">Descricao (opcional)</span>
            <input v-model="novaRegra.descricao" type="text" placeholder="Breve descricao" />
          </label>
        </div>
        <button
          type="button"
          class="mt-4 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-bg transition hover:bg-primary-hover disabled:cursor-not-allowed disabled:opacity-60"
          :disabled="adicionandoRegra || !novaRegra.chave || !novaRegra.nome"
          @click="adicionarRegra"
        >
          {{ adicionandoRegra ? 'Adicionando...' : 'Adicionar regra' }}
        </button>
      </div>

      <div class="rounded-2xl border border-border bg-bg-card p-6">
        <h2 class="mb-4 text-lg font-bold">Regras de Pontuacao</h2>
        <div class="space-y-3">
          <div
            v-for="regra in regrasOrdenadas"
            :key="regra.id"
            class="flex flex-col gap-3 rounded-xl bg-bg-input p-4 sm:flex-row sm:items-center"
          >
            <div class="min-w-0 flex-1">
              <div class="flex flex-wrap items-center gap-2">
                <p class="text-sm font-medium">{{ regra.nome }}</p>
                <span class="rounded-full bg-bg-card px-2 py-0.5 text-[10px] text-text-muted">{{ nomeFase(regra.fase_id) }}</span>
                <span v-if="(regra.eventos_pontuacao_count ?? 0) > 0" class="rounded-full bg-primary/15 px-2 py-0.5 text-[10px] font-medium text-primary">aplicada</span>
              </div>
              <p v-if="regra.descricao" class="mt-0.5 text-xs text-text-muted">{{ regra.descricao }}</p>
            </div>
            <input v-model="pontosRegras[regra.id]" type="number" min="0" class="!w-24" />
            <button
              type="button"
              class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-bg transition hover:bg-primary-hover disabled:cursor-not-allowed disabled:opacity-60"
              :disabled="salvandoRegraId === regra.id"
              @click="salvarRegra(regra.id)"
            >
              {{ salvandoRegraId === regra.id ? 'Salvando...' : 'Salvar' }}
            </button>
            <button
              v-if="(regra.eventos_pontuacao_count ?? 0) === 0"
              type="button"
              class="rounded-lg border border-danger/40 px-3 py-2 text-sm font-medium text-danger transition hover:bg-danger/10 disabled:cursor-not-allowed disabled:opacity-60"
              :disabled="excluindoRegraId === regra.id"
              @click="excluirRegra(regra.id)"
            >
              {{ excluindoRegraId === regra.id ? 'Excluindo...' : 'Excluir' }}
            </button>
          </div>
        </div>
      </div>
    </section>

    <section v-if="tabAtiva === 'torneio'" class="rounded-2xl border border-border bg-bg-card p-6">
      <h2 class="mb-4 text-lg font-bold">Resultado Final do Torneio</h2>
      <div class="grid gap-3 sm:grid-cols-3">
        <div class="rounded-xl bg-bg-input p-4">
          <span class="mb-1.5 block text-xs text-text-muted">Campeao</span>
          <strong class="text-sm">{{ podioDerivado.campeao ?? 'A definir' }}</strong>
        </div>
        <div class="rounded-xl bg-bg-input p-4">
          <span class="mb-1.5 block text-xs text-text-muted">Vice-campeao</span>
          <strong class="text-sm">{{ podioDerivado.vice ?? 'A definir' }}</strong>
        </div>
        <div class="rounded-xl bg-bg-input p-4">
          <span class="mb-1.5 block text-xs text-text-muted">Terceiro colocado</span>
          <strong class="text-sm">{{ podioDerivado.terceiro ?? 'A definir' }}</strong>
        </div>
      </div>

      <button
        type="button"
        class="mt-5 w-full rounded-lg bg-primary py-2.5 text-sm font-medium text-bg transition hover:bg-primary-hover disabled:cursor-not-allowed disabled:opacity-60"
        :disabled="salvandoResultadoTorneio"
        @click="salvarResultadoTorneioFn"
      >
        {{ salvandoResultadoTorneio ? 'Salvando...' : 'Sincronizar resultado final' }}
      </button>
    </section>

    <section v-if="tabAtiva === 'pagamentos'" class="space-y-4">
      <div class="rounded-2xl border border-border bg-bg-card p-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h2 class="text-lg font-bold">Pagamentos</h2>
            <p class="mt-1 text-sm text-text-secondary">Todos os cupons. Marque como pago ou reverta para aguardando.</p>
          </div>
          <span class="rounded-full border border-primary/30 bg-primary/10 px-3 py-1 text-xs text-primary">
            {{ cuponsPagamento.length }} cupom{{ cuponsPagamento.length === 1 ? '' : 's' }}
          </span>
        </div>
        <input
          v-model="buscaPagamento"
          type="text"
          placeholder="Buscar por nome, email, telefone ou codigo do cupom"
          class="mt-4 w-full"
        />
      </div>

      <div v-if="carregandoPendentes" class="rounded-2xl border border-border bg-bg-card py-10 text-center text-sm text-text-muted">
        Carregando...
      </div>
      <div v-else-if="!cuponsPagamentoFiltrados.length" class="rounded-2xl border border-dashed border-border bg-bg-card py-10 text-center text-sm text-text-muted">
        Nenhum cupom encontrado.
      </div>
      <div v-else class="grid gap-3">
        <div
          v-for="cupom in cuponsPagamentoFiltrados"
          :key="cupom.id"
          class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-border bg-bg-card p-4"
        >
          <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
              <p class="font-bold text-text">{{ cupom.usuario?.nome ?? 'Usuario' }}</p>
              <span class="rounded-full px-2 py-0.5 text-[10px] font-medium" :class="classeStatus(cupom.status)">{{ rotuloStatus(cupom.status) }}</span>
            </div>
            <p class="text-xs text-text-muted">{{ cupom.usuario?.email }} · {{ cupom.usuario?.telefone ?? 'sem telefone' }}</p>
            <p class="mt-0.5 text-xs text-text-muted">
              Cupom <span class="font-mono text-text-secondary">{{ cupom.codigo }}</span> · {{ formatarValorCupom(cupom.pedido_checkout?.valor) }}
            </p>
          </div>
          <button
            type="button"
            class="shrink-0 rounded-lg px-4 py-2 text-sm font-semibold transition disabled:cursor-not-allowed disabled:opacity-60"
            :class="cupom.status === 'ativo' ? 'border border-danger/40 text-danger hover:bg-danger/10' : 'bg-primary text-bg hover:bg-primary-hover'"
            :disabled="marcandoId === cupom.id"
            @click="alternarPagamento(cupom)"
          >
            {{ marcandoId === cupom.id ? 'Salvando...' : (cupom.status === 'ativo' ? 'Marcar como nao pago' : 'Marcar como pago') }}
          </button>
        </div>
      </div>
    </section>

    <section v-if="tabAtiva === 'logs'" class="space-y-4">
      <div class="rounded-2xl border border-border bg-bg-card p-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h2 class="text-lg font-bold">Logs de Apostas</h2>
            <p class="mt-1 text-sm text-text-secondary">
              Auditoria de palpites: quem criou, editou ou removeu, com o conteúdo antes e depois.
              Órfãos são o histórico de palpites apagados (sem vínculo com o jogo).
            </p>
          </div>
          <span class="rounded-full border border-primary/30 bg-primary/10 px-3 py-1 text-xs text-primary">
            {{ totalLogs }} registro{{ totalLogs === 1 ? '' : 's' }}
          </span>
        </div>

        <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <label class="block">
            <span class="mb-1 block text-xs text-text-muted">Jogador</span>
            <select v-model="filtrosLogs.usuario_id" class="w-full">
              <option value="">Todos</option>
              <option v-for="usuario in usuariosFiltroLogs" :key="usuario.id" :value="String(usuario.id)">
                {{ usuario.nome }} ({{ usuario.email }})
              </option>
            </select>
          </label>
          <label class="block">
            <span class="mb-1 block text-xs text-text-muted">Bolão</span>
            <select v-model="filtrosLogs.torneio_id" class="w-full">
              <option value="">Todos</option>
              <option v-for="bolao in boloes" :key="bolao.id" :value="String(bolao.id)">{{ bolao.nome }} {{ bolao.edicao }}</option>
            </select>
          </label>
          <label class="block lg:col-span-2">
            <span class="mb-1 block text-xs text-text-muted">Jogo (do bolão selecionado no topo)</span>
            <select v-model="filtrosLogs.jogo_id" class="w-full">
              <option value="">Todos</option>
              <option v-for="jogo in jogosParaFiltroLogs" :key="jogo.id" :value="String(jogo.id)">{{ rotuloJogoLogs(jogo) }}</option>
            </select>
          </label>
          <label class="block">
            <span class="mb-1 block text-xs text-text-muted">De (dia, Brasília)</span>
            <input v-model="filtrosLogs.de" type="date" class="w-full [color-scheme:dark]" />
          </label>
          <label class="block">
            <span class="mb-1 block text-xs text-text-muted">Até (dia, Brasília)</span>
            <input v-model="filtrosLogs.ate" type="date" class="w-full [color-scheme:dark]" />
          </label>
          <label class="block">
            <span class="mb-1 block text-xs text-text-muted">Ação</span>
            <select v-model="filtrosLogs.acao" class="w-full">
              <option value="">Todas</option>
              <option value="criada">Criada</option>
              <option value="editada">Editada</option>
              <option value="removida">Removida</option>
            </select>
          </label>
          <label class="block">
            <span class="mb-1 block text-xs text-text-muted">Escopo</span>
            <select v-model="filtrosLogs.escopo" class="w-full">
              <option value="todos">Todos</option>
              <option value="orfaos">Só órfãos (palpites apagados)</option>
              <option value="vinculados">Só vinculados a jogo</option>
            </select>
          </label>
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-3">
          <label class="flex cursor-pointer items-center gap-2 text-sm text-text-secondary">
            <input v-model="filtrosLogs.apenas_mudancas" type="checkbox" class="!w-auto" />
            Somente mudanças reais (esconde re-saves do auto-save)
          </label>
          <div class="ml-auto flex gap-2">
            <button
              type="button"
              class="rounded-lg border border-border px-4 py-2 text-sm text-text-secondary transition hover:text-text"
              @click="limparFiltrosLogs"
            >
              Limpar filtros
            </button>
            <button
              type="button"
              class="rounded-lg border border-primary/40 px-4 py-2 text-sm font-medium text-primary transition hover:bg-primary/10 disabled:opacity-50"
              :disabled="exportandoLogs || carregandoLogs"
              @click="exportarCsvLogs"
            >
              {{ exportandoLogs ? 'Exportando...' : 'Exportar CSV' }}
            </button>
            <button
              type="button"
              class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-bg transition hover:bg-primary-hover disabled:opacity-50"
              :disabled="carregandoLogs"
              @click="buscarLogs()"
            >
              {{ carregandoLogs ? 'Buscando...' : 'Buscar' }}
            </button>
          </div>
        </div>
      </div>

      <div v-if="carregandoLogs" class="rounded-2xl border border-border bg-bg-card py-10 text-center text-sm text-text-muted">
        Carregando...
      </div>
      <div v-else-if="!logsApostas.length" class="rounded-2xl border border-dashed border-border bg-bg-card py-10 text-center text-sm text-text-muted">
        Nenhum log encontrado para os filtros.
      </div>
      <div v-else class="overflow-x-auto rounded-2xl border border-border bg-bg-card">
        <table class="w-full min-w-[900px] text-left text-sm">
          <thead>
            <tr class="border-b border-border text-xs uppercase tracking-wider text-text-muted">
              <th class="px-4 py-3">Quando (BRT)</th>
              <th class="px-4 py-3">Jogador</th>
              <th class="px-4 py-3">Bolão / Cupom</th>
              <th class="px-4 py-3">Jogo</th>
              <th class="px-4 py-3">Ação</th>
              <th class="px-4 py-3">Antes → Depois</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="log in logsApostas" :key="log.id" class="border-b border-border/50 align-top" :class="log.mudou ? '' : 'opacity-50'">
              <td class="whitespace-nowrap px-4 py-3 font-mono text-xs">{{ log.quando_brt }}</td>
              <td class="px-4 py-3">
                <p class="font-medium">{{ log.usuario?.nome ?? '—' }}</p>
                <p class="text-xs text-text-muted">{{ log.usuario?.email }}</p>
              </td>
              <td class="px-4 py-3">
                <p class="text-xs">{{ log.bolao ?? '—' }}</p>
                <p class="font-mono text-xs text-text-muted">{{ log.cupom }}</p>
              </td>
              <td class="px-4 py-3">
                <template v-if="log.jogo">
                  <p>{{ log.jogo.nome }}</p>
                  <p class="text-xs text-text-muted">{{ log.jogo.data_hora_inicio }}</p>
                </template>
                <span v-else-if="log.orfao" class="rounded-full bg-warning/15 px-2 py-0.5 text-[10px] font-medium text-warning">
                  palpite apagado
                </span>
                <span v-else class="text-xs text-text-muted">{{ log.tipo ?? '—' }}</span>
              </td>
              <td class="px-4 py-3">
                <span
                  class="rounded-full px-2 py-0.5 text-[10px] font-medium"
                  :class="log.acao === 'removida' ? 'bg-danger/15 text-danger' : log.acao === 'criada' ? 'bg-primary/15 text-primary' : 'bg-bg-input text-text-secondary'"
                >
                  {{ log.acao }}
                </span>
                <p v-if="!log.mudou" class="mt-1 text-[10px] text-text-muted">sem mudança</p>
              </td>
              <td class="px-4 py-3 text-xs">
                <p class="text-text-muted">{{ log.resumo_antes ?? 'sem palpite' }}</p>
                <p class="mt-0.5 font-medium text-text">→ {{ log.resumo_depois ?? 'sem palpite' }}</p>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="totalPaginasLogs > 1" class="flex items-center justify-center gap-3">
        <button
          type="button"
          class="rounded-lg border border-border px-4 py-2 text-sm text-text-secondary transition hover:text-text disabled:opacity-40"
          :disabled="paginaLogs <= 1 || carregandoLogs"
          @click="buscarLogs(paginaLogs - 1)"
        >
          Anterior
        </button>
        <span class="text-sm text-text-muted">Página {{ paginaLogs }} de {{ totalPaginasLogs }}</span>
        <button
          type="button"
          class="rounded-lg border border-border px-4 py-2 text-sm text-text-secondary transition hover:text-text disabled:opacity-40"
          :disabled="paginaLogs >= totalPaginasLogs || carregandoLogs"
          @click="buscarLogs(paginaLogs + 1)"
        >
          Próxima
        </button>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { requisicaoApi } from '../services/api'
import { usarBolaoAtivoStore } from '../stores/bolaoAtivo'
import type { Torneio, Bolao } from '../tipos'

// Mesmo store do seletor de bolão da navegação (AppHeader/SeletorBolao). Sem isso, o
// admin abria sempre o último torneio (latest id = Mata-Mata) e ignorava o seletor.
const bolaoAtivo = usarBolaoAtivoStore()

const torneio = ref<Torneio | null>(null)
const mensagem = ref('')
const erro = ref('')
const tabAtiva = ref<'jogos' | 'regras' | 'torneio' | 'pagamentos' | 'logs'>('jogos')
const faseSelecionadaId = ref<number | null>(null)
const pontosRegras = ref<Record<number, string>>({})
const resultadosJogos = ref<Record<number, { placar_mandante: string; placar_visitante: string; selecao_classificada_id: string }>>({})
const salvandoJogoId = ref<number | null>(null)
const vinculosEvento = ref<Record<number, string>>({})
const vinculandoJogoId = ref<number | null>(null)
const salvandoRegraId = ref<number | null>(null)
const excluindoRegraId = ref<number | null>(null)
const adicionandoRegra = ref(false)
const salvandoResultadoTorneio = ref(false)
const salvandoCompras = ref(false)
const salvandoFechamentoPodio = ref(false)
const fechamentoPodioInput = ref('')
const chavesDisponiveis = ref<{ chave: string; label: string }[]>([])
const boloes = ref<Bolao[]>([])
const torneioSelecionadoId = ref<number | null>(null)
const novaRegra = ref({ chave: '', fase_id: '', nome: '', descricao: '', pontos: '0' })

const tabs = [
  { id: 'jogos' as const, nome: 'Resultados dos Jogos' },
  { id: 'regras' as const, nome: 'Regras' },
  { id: 'torneio' as const, nome: 'Resultado Final' },
  { id: 'pagamentos' as const, nome: 'Pagamentos' },
  { id: 'logs' as const, nome: 'Logs de Apostas' },
]

type CupomPagamento = {
  id: number
  codigo: string
  status: string
  usuario: { id: number; nome: string; email: string; telefone: string | null } | null
  pedido_checkout: { id: number; valor: string; status: string } | null
}

const cuponsPagamento = ref<CupomPagamento[]>([])
const carregandoPendentes = ref(false)
const buscaPagamento = ref('')
const marcandoId = ref<number | null>(null)

const cuponsPagamentoFiltrados = computed(() => {
  const termo = buscaPagamento.value.trim().toLowerCase()
  if (!termo) return cuponsPagamento.value
  return cuponsPagamento.value.filter((cupom) =>
    [cupom.codigo, cupom.usuario?.nome, cupom.usuario?.email, cupom.usuario?.telefone]
      .some((valor) => (valor ?? '').toLowerCase().includes(termo)),
  )
})

function rotuloStatus(status: string) {
  if (status === 'ativo') return 'Pago'
  if (status === 'aguardando_pagamento') return 'Aguardando'
  return status
}

function classeStatus(status: string) {
  if (status === 'ativo') return 'bg-primary/15 text-primary'
  if (status === 'aguardando_pagamento') return 'bg-warning/15 text-warning'
  return 'bg-bg-input text-text-muted'
}

function formatarValorCupom(valor?: string | null) {
  const numero = Number(valor)
  return Number.isFinite(numero) && numero > 0
    ? new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(numero)
    : 'R$ --'
}

async function carregarPagamentos() {
  carregandoPendentes.value = true
  try {
    const resposta = await requisicaoApi<{ cupons: CupomPagamento[] }>('/admin/pagamentos')
    cuponsPagamento.value = resposta.cupons
  } catch (error) {
    erro.value = error instanceof Error ? error.message : 'Falha ao carregar pagamentos.'
  } finally {
    carregandoPendentes.value = false
  }
}

async function alternarPagamento(cupom: CupomPagamento) {
  marcandoId.value = cupom.id
  mensagem.value = ''
  erro.value = ''
  const marcarPago = cupom.status !== 'ativo'
  const rota = marcarPago ? 'marcar-pago' : 'marcar-nao-pago'
  try {
    const resposta = await requisicaoApi<{ cupom: CupomPagamento }>(`/admin/cupons/${cupom.id}/${rota}`, { metodo: 'POST', corpo: {} })
    const indice = cuponsPagamento.value.findIndex((item) => item.id === cupom.id)
    if (indice !== -1) cuponsPagamento.value[indice] = resposta.cupom
    mensagem.value = marcarPago ? 'Cupom marcado como pago.' : 'Cupom revertido para aguardando.'
  } catch (error) {
    erro.value = error instanceof Error ? error.message : 'Falha ao atualizar pagamento.'
  } finally {
    marcandoId.value = null
  }
}

watch(tabAtiva, (tab) => {
  if (tab === 'pagamentos') void carregarPagamentos()
  if (tab === 'logs') {
    void carregarUsuariosFiltroLogs()
    if (!buscouLogs.value) void buscarLogs()
  }
})

// ---- Logs de Apostas (auditoria de palpites) ----

type UsuarioFiltroLogs = { id: number; nome: string; email: string }
type LogApostaAdmin = {
  id: number
  quando_brt: string
  acao: string
  orfao: boolean
  mudou: boolean
  usuario: UsuarioFiltroLogs | null
  cupom: string | null
  bolao: string | null
  tipo: string | null
  jogo: { id: number; nome: string; data_hora_inicio: string | null } | null
  resumo_antes: string | null
  resumo_depois: string | null
}
type RespostaLogs = { logs: LogApostaAdmin[]; pagina: number; total_paginas: number; total: number }

const usuariosFiltroLogs = ref<UsuarioFiltroLogs[]>([])
const logsApostas = ref<LogApostaAdmin[]>([])
const carregandoLogs = ref(false)
const exportandoLogs = ref(false)
const buscouLogs = ref(false)
const paginaLogs = ref(1)
const totalPaginasLogs = ref(1)
const totalLogs = ref(0)
const filtrosLogs = ref({
  usuario_id: '',
  torneio_id: '',
  jogo_id: '',
  acao: '',
  escopo: 'todos',
  apenas_mudancas: true,
  de: '',
  ate: '',
})

// O seletor de jogo usa os jogos do bolão carregado no painel (troque o bolão no topo
// para listar os jogos do outro). O filtro de bolão do relatório é independente disso.
const jogosParaFiltroLogs = computed(() =>
  [...(torneio.value?.jogos ?? [])]
    .filter((jogo) => jogo.data_hora_inicio)
    .sort((a, b) => a.data_hora_inicio.localeCompare(b.data_hora_inicio)),
)

function rotuloJogoLogs(jogo: Torneio['jogos'][number]) {
  const inicio = jogo.data_hora_inicio ?? ''
  const dia = inicio ? `${inicio.substring(8, 10)}/${inicio.substring(5, 7)} ${inicio.substring(11, 16)}` : 'sem data'
  return `${dia} — ${nomeMandanteAdmin(jogo)} x ${nomeVisitanteAdmin(jogo)}`
}

function montarQueryLogs(pagina: number) {
  const params = new URLSearchParams()
  const filtros = filtrosLogs.value
  if (filtros.usuario_id) params.set('usuario_id', filtros.usuario_id)
  if (filtros.torneio_id) params.set('torneio_id', filtros.torneio_id)
  if (filtros.jogo_id) params.set('jogo_id', filtros.jogo_id)
  if (filtros.acao) params.set('acao', filtros.acao)
  if (filtros.escopo !== 'todos') params.set('escopo', filtros.escopo)
  if (filtros.apenas_mudancas) params.set('apenas_mudancas', '1')
  if (filtros.de) params.set('de', filtros.de)
  if (filtros.ate) params.set('ate', filtros.ate)
  params.set('page', String(pagina))
  return params
}

async function carregarUsuariosFiltroLogs() {
  if (usuariosFiltroLogs.value.length) return
  try {
    const resposta = await requisicaoApi<{ usuarios: UsuarioFiltroLogs[] }>('/admin/usuarios')
    usuariosFiltroLogs.value = resposta.usuarios
  } catch {
    usuariosFiltroLogs.value = []
  }
}

async function buscarLogs(pagina = 1) {
  carregandoLogs.value = true
  erro.value = ''
  try {
    const resposta = await requisicaoApi<RespostaLogs>(`/admin/logs-apostas?${montarQueryLogs(pagina)}`)
    logsApostas.value = resposta.logs
    paginaLogs.value = resposta.pagina
    totalPaginasLogs.value = resposta.total_paginas
    totalLogs.value = resposta.total
    buscouLogs.value = true
  } catch (error) {
    erro.value = error instanceof Error ? error.message : 'Falha ao carregar logs de apostas.'
  } finally {
    carregandoLogs.value = false
  }
}

function limparFiltrosLogs() {
  filtrosLogs.value = { usuario_id: '', torneio_id: '', jogo_id: '', acao: '', escopo: 'todos', apenas_mudancas: true, de: '', ate: '' }
  void buscarLogs()
}

// Exporta TODAS as páginas do filtro atual (trava de segurança em 40 páginas = 2000 linhas).
async function exportarCsvLogs() {
  exportandoLogs.value = true
  erro.value = ''
  try {
    const linhas: LogApostaAdmin[] = []
    let pagina = 1
    let totalPaginas = 1
    do {
      const resposta = await requisicaoApi<RespostaLogs>(`/admin/logs-apostas?${montarQueryLogs(pagina)}`)
      linhas.push(...resposta.logs)
      totalPaginas = resposta.total_paginas
      pagina++
    } while (pagina <= totalPaginas && pagina <= 40)

    const campo = (valor: unknown) => `"${String(valor ?? '').replaceAll('"', '""')}"`
    const csv = [
      ['quando_brt', 'usuario', 'email', 'bolao', 'cupom', 'jogo', 'acao', 'orfao', 'mudanca_real', 'antes', 'depois'].join(','),
      ...linhas.map((log) => [
        campo(log.quando_brt),
        campo(log.usuario?.nome),
        campo(log.usuario?.email),
        campo(log.bolao),
        campo(log.cupom),
        campo(log.jogo?.nome ?? (log.orfao ? 'palpite apagado' : '')),
        campo(log.acao),
        campo(log.orfao ? 'sim' : 'nao'),
        campo(log.mudou ? 'sim' : 'nao'),
        campo(log.resumo_antes),
        campo(log.resumo_depois),
      ].join(',')),
    ].join('\n')

    const blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8' })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `logs-apostas-${new Date().toISOString().substring(0, 10)}.csv`
    link.click()
    URL.revokeObjectURL(url)
  } catch (error) {
    erro.value = error instanceof Error ? error.message : 'Falha ao exportar CSV.'
  } finally {
    exportandoLogs.value = false
  }
}

const selecoes = computed(() => torneio.value?.grupos.flatMap((g) => g.selecoes) ?? [])
const regrasOrdenadas = computed(() =>
  [...(torneio.value?.regras_pontuacao ?? [])].sort((a, b) =>
    a.chave === b.chave ? (a.fase_id ?? 0) - (b.fase_id ?? 0) : a.chave.localeCompare(b.chave),
  ),
)

function nomeFase(faseId: number | null) {
  if (faseId === null) return 'Global'
  return torneio.value?.fases.find((f) => f.id === faseId)?.nome ?? 'Fase'
}
const fasesComJogos = computed(() => {
  if (!torneio.value) return []
  return torneio.value.fases
    .filter((fase) => torneio.value?.jogos.some((jogo) => jogo.fase_id === fase.id))
    .sort((a, b) => a.ordem - b.ordem)
})
const jogosFiltrados = computed(() => {
  if (!torneio.value || !faseSelecionadaId.value) return []
  return torneio.value.jogos
    .filter((jogo) => jogo.fase_id === faseSelecionadaId.value)
    .sort((a, b) => a.ordem_na_fase - b.ordem_na_fase)
})

// Agrupamento por dia dentro da fase selecionada (espelha a tela de palpites do cupom).
const diasSemanaAdmin = ['DOM', 'SEG', 'TER', 'QUA', 'QUI', 'SEX', 'SAB']
const diaSelecionadoAdmin = ref('')

const diasComJogosAdmin = computed(() => {
  const map = new Map<string, { data: string; diaSemana: string; diaNumero: number; totalJogos: number; pendentes: number }>()
  for (const jogo of jogosFiltrados.value) {
    if (!jogo.data_hora_inicio) continue
    const d = new Date(jogo.data_hora_inicio)
    const key = jogo.data_hora_inicio.substring(0, 10)
    if (!map.has(key)) {
      // getUTC* para casar com o prefixo YYYY-MM-DD (dia civil em Brasília, valor naive).
      map.set(key, { data: key, diaSemana: diasSemanaAdmin[d.getUTCDay()], diaNumero: d.getUTCDate(), totalJogos: 0, pendentes: 0 })
    }
    const entry = map.get(key)!
    entry.totalJogos++
    if (!jogo.resultado) entry.pendentes++
  }
  return [...map.values()].sort((a, b) => a.data.localeCompare(b.data))
})

const jogosDoDiaAdmin = computed(() => {
  if (!diaSelecionadoAdmin.value) return jogosFiltrados.value
  return jogosFiltrados.value.filter((jogo) => (jogo.data_hora_inicio ?? '').startsWith(diaSelecionadoAdmin.value))
})

watch([faseSelecionadaId, diasComJogosAdmin], () => {
  if (!diasComJogosAdmin.value.length) {
    diaSelecionadoAdmin.value = ''
    return
  }
  if (!diasComJogosAdmin.value.some((dia) => dia.data === diaSelecionadoAdmin.value)) {
    diaSelecionadoAdmin.value = diasComJogosAdmin.value[0].data
  }
}, { immediate: true })
const podioDerivado = computed(() => {
  if (!torneio.value) {
    return { campeao: null, vice: null, terceiro: null }
  }

  const nomeSelecao = (id: number | null | undefined) => selecoes.value.find((selecao) => selecao.id === id)?.nome ?? null

  return {
    campeao: nomeSelecao(torneio.value.resultado_torneio?.campeao_selecao_id),
    vice: nomeSelecao(torneio.value.resultado_torneio?.vice_campeao_selecao_id),
    terceiro: nomeSelecao(torneio.value.resultado_torneio?.terceiro_colocado_selecao_id),
  }
})

function opcoesClassificado(jogo: Torneio['jogos'][number]) {
  if (jogo.fase.tipo === 'grupos') {
    return [jogo.selecao_mandante, jogo.selecao_visitante].filter((selecao): selecao is NonNullable<typeof selecao> => Boolean(selecao))
  }

  return jogo.participantes_admin ?? []
}

function nomeMandanteAdmin(jogo: Torneio['jogos'][number]) {
  if (jogo.fase.tipo === 'grupos') {
    return jogo.selecao_mandante?.nome ?? 'A definir'
  }

  return jogo.participantes_admin?.[0]?.nome ?? 'A definir'
}

function nomeVisitanteAdmin(jogo: Torneio['jogos'][number]) {
  if (jogo.fase.tipo === 'grupos') {
    return jogo.selecao_visitante?.nome ?? 'A definir'
  }

  return jogo.participantes_admin?.[1]?.nome ?? 'A definir'
}

function jogoExigeClassificado(jogo: Torneio['jogos'][number]) {
  if (jogo.fase.tipo === 'grupos') {
    return false
  }

  const resultado = resultadosJogos.value[jogo.id]
  return resultado
    && resultado.placar_mandante !== ''
    && resultado.placar_visitante !== ''
    && resultado.placar_mandante === resultado.placar_visitante
}

function preencherFormulario() {
  if (!torneio.value) return

  fechamentoPodioInput.value = isoParaInputLocal(torneio.value?.data_fechamento_podio ?? null)

  for (const regra of torneio.value.regras_pontuacao) {
    pontosRegras.value[regra.id] = String(regra.pontos)
  }

  for (const jogo of torneio.value.jogos) {
    resultadosJogos.value[jogo.id] = {
      placar_mandante: String(jogo.resultado?.placar_mandante ?? ''),
      placar_visitante: String(jogo.resultado?.placar_visitante ?? ''),
      selecao_classificada_id: String(jogo.resultado?.selecao_classificada_id ?? ''),
    }
    vinculosEvento.value[jogo.id] = jogo.id_evento_externo != null ? String(jogo.id_evento_externo) : ''
  }

  if (!faseSelecionadaId.value || !fasesComJogos.value.some((fase) => fase.id === faseSelecionadaId.value)) {
    definirDiaInicialAdmin()
  }
}

// Abre na fase/dia do dia atual (ou o proximo dia com jogos; senao o ultimo).
function definirDiaInicialAdmin(): void {
  const jogos = (torneio.value?.jogos ?? [])
    .filter((jogo) => jogo.data_hora_inicio)
    .sort((a, b) => a.data_hora_inicio.localeCompare(b.data_hora_inicio))

  if (!jogos.length) {
    faseSelecionadaId.value = fasesComJogos.value[0]?.id ?? null
    return
  }

  const hoje = new Date().toISOString().substring(0, 10)
  const alvo = jogos.find((jogo) => jogo.data_hora_inicio.substring(0, 10) === hoje)
    ?? jogos.find((jogo) => jogo.data_hora_inicio.substring(0, 10) >= hoje)
    ?? jogos[jogos.length - 1]

  faseSelecionadaId.value = alvo.fase_id
  diaSelecionadoAdmin.value = alvo.data_hora_inicio.substring(0, 10)
}

async function carregarDados(torneioId?: number) {
  const caminho = torneioId ? `/admin/dados?torneio_id=${torneioId}` : '/admin/dados'
  const resposta = await requisicaoApi<{ torneio: Torneio; chaves_disponiveis: { chave: string; label: string }[] }>(caminho)
  torneio.value = resposta.torneio
  torneioSelecionadoId.value = resposta.torneio.id
  chavesDisponiveis.value = resposta.chaves_disponiveis ?? []
  preencherFormulario()
}

async function carregarBoloes() {
  try {
    const r = await requisicaoApi<{ ativos: Bolao[]; encerrados: Bolao[] }>('/boloes')
    boloes.value = [...r.ativos, ...r.encerrados]
  } catch {
    boloes.value = []
  }
}

async function trocarBolao(torneioId: number) {
  // Mantém o seletor do admin e o seletor global (topo) em sincronia.
  bolaoAtivo.definirAtivo(torneioId)
  await carregarDados(torneioId)
}

// Convenção do app: datas são wall-clock de Brasília armazenadas naive (serializadas
// com Z). O input datetime-local deve mostrar/gravar esses dígitos VERBATIM, então
// operamos no frame UTC dos dois lados (sem aplicar o fuso do navegador).
function isoParaInputLocal(iso: string | null): string {
  if (!iso) return ''
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return ''
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${d.getUTCFullYear()}-${pad(d.getUTCMonth() + 1)}-${pad(d.getUTCDate())}T${pad(d.getUTCHours())}:${pad(d.getUTCMinutes())}`
}

// Converte o valor do input (interpretado como wall-clock de Brasília) para o ISO
// naive-com-Z esperado pelo backend, preservando os dígitos.
function inputLocalParaIso(local: string): string | null {
  if (!local) return null
  const d = new Date(`${local}:00.000Z`) // força leitura no frame UTC = dígitos do input
  return Number.isNaN(d.getTime()) ? null : d.toISOString()
}

async function alternarCompras() {
  if (!torneio.value || salvandoCompras.value) return
  salvandoCompras.value = true
  mensagem.value = ''
  erro.value = ''
  try {
    const novoEstado = !torneio.value.compras_abertas
    const resposta = await requisicaoApi<{ torneio: Torneio }>(`/admin/torneios/${torneio.value.id}/compras`, {
      metodo: 'PUT',
      corpo: { compras_abertas: novoEstado },
    })
    torneio.value.compras_abertas = resposta.torneio.compras_abertas
    mensagem.value = torneio.value.compras_abertas
      ? 'Compra de cupons aberta para os participantes.'
      : 'Compra de cupons encerrada.'
  } catch (e) {
    erro.value = e instanceof Error ? e.message : 'Falha ao atualizar a compra de cupons.'
  } finally {
    salvandoCompras.value = false
  }
}

async function salvarFechamentoPodio() {
  if (!torneio.value || salvandoFechamentoPodio.value) return
  salvandoFechamentoPodio.value = true
  mensagem.value = ''
  erro.value = ''
  try {
    const resposta = await requisicaoApi<{ torneio: Torneio }>(
      `/admin/torneios/${torneio.value.id}/fechamento-podio`,
      { metodo: 'PUT', corpo: { data_fechamento_podio: inputLocalParaIso(fechamentoPodioInput.value) } },
    )
    torneio.value.data_fechamento_podio = resposta.torneio.data_fechamento_podio
    fechamentoPodioInput.value = isoParaInputLocal(torneio.value.data_fechamento_podio)
    mensagem.value = torneio.value.data_fechamento_podio
      ? 'Prazo de fechamento do pódio atualizado.'
      : 'Fechamento do pódio voltou ao automático.'
  } catch (e) {
    erro.value = e instanceof Error ? e.message : 'Falha ao atualizar o fechamento do pódio.'
  } finally {
    salvandoFechamentoPodio.value = false
  }
}

function limparFechamentoPodio() {
  fechamentoPodioInput.value = ''
  void salvarFechamentoPodio()
}

async function executarAcao(acao: () => Promise<void>, mensagemSucesso = 'Salvo com sucesso. Recalculo enviado para processamento.') {
  mensagem.value = ''
  erro.value = ''

  try {
    await acao()
    mensagem.value = mensagemSucesso
    await carregarDados()
  } catch (error) {
    erro.value = error instanceof Error ? error.message : 'Falha ao salvar.'
  }
}

async function salvarRegra(regraId: number) {
  salvandoRegraId.value = regraId
  await executarAcao(async () => {
    await requisicaoApi(`/admin/regras-pontuacao/${regraId}`, {
      metodo: 'PUT',
      corpo: { pontos: Number(pontosRegras.value[regraId] || 0) },
    })
  }, 'Regra salva. Recalculo enviado para processamento.')
  salvandoRegraId.value = null
}

async function adicionarRegra() {
  adicionandoRegra.value = true
  await executarAcao(async () => {
    await requisicaoApi('/admin/regras-pontuacao', {
      metodo: 'POST',
      corpo: {
        // Sem o torneio_id, o backend cai em latest('id') (o ultimo bolao) e cria/valida
        // a regra no bolao errado. Amarra ao bolao selecionado no painel.
        torneio_id: torneioSelecionadoId.value,
        chave: novaRegra.value.chave,
        fase_id: novaRegra.value.fase_id ? Number(novaRegra.value.fase_id) : null,
        nome: novaRegra.value.nome,
        descricao: novaRegra.value.descricao || null,
        pontos: Number(novaRegra.value.pontos || 0),
      },
    })
    novaRegra.value = { chave: '', fase_id: '', nome: '', descricao: '', pontos: '0' }
  }, 'Regra adicionada. Recalculo enviado para processamento.')
  adicionandoRegra.value = false
}

async function excluirRegra(regraId: number) {
  excluindoRegraId.value = regraId
  await executarAcao(async () => {
    await requisicaoApi(`/admin/regras-pontuacao/${regraId}`, { metodo: 'DELETE' })
  }, 'Regra excluida. Recalculo enviado para processamento.')
  excluindoRegraId.value = null
}

async function salvarResultadoJogo(jogoId: number) {
  salvandoJogoId.value = jogoId
  await executarAcao(async () => {
    await requisicaoApi(`/admin/jogos/${jogoId}/resultado`, {
      metodo: 'PUT',
      corpo: {
        placar_mandante: Number(resultadosJogos.value[jogoId].placar_mandante || 0),
        placar_visitante: Number(resultadosJogos.value[jogoId].placar_visitante || 0),
        selecao_classificada_id: resultadosJogos.value[jogoId].selecao_classificada_id
          ? Number(resultadosJogos.value[jogoId].selecao_classificada_id)
          : null,
      },
    })
  }, 'Resultado salvo. Recalculo enviado para processamento.')
  salvandoJogoId.value = null
}

async function limparResultadoJogo(jogoId: number) {
  salvandoJogoId.value = jogoId
  await executarAcao(async () => {
    await requisicaoApi(`/admin/jogos/${jogoId}/resultado`, { metodo: 'DELETE' })
  }, 'Resultado removido. Recalculo enviado para processamento.')
  salvandoJogoId.value = null
}

async function vincularEvento(jogoId: number) {
  vinculandoJogoId.value = jogoId
  // input type=number pode chegar como número; coage pra string antes de .trim()
  const valor = String(vinculosEvento.value[jogoId] ?? '').trim()
  await executarAcao(async () => {
    await requisicaoApi(`/admin/jogos/${jogoId}/evento-externo`, {
      metodo: 'PUT',
      corpo: { id_evento_externo: valor ? Number(valor) : null },
    })
  }, valor ? 'Evento vinculado (times e data trazidos do evento).' : 'Slot limpo.')
  vinculandoJogoId.value = null
}

// Desvincular e limpar: zera vínculo + times + resultado (volta a "A definir").
async function limparSlot(jogoId: number) {
  vinculandoJogoId.value = jogoId
  vinculosEvento.value[jogoId] = ''
  await executarAcao(async () => {
    await requisicaoApi(`/admin/jogos/${jogoId}/evento-externo`, {
      metodo: 'PUT',
      corpo: { id_evento_externo: null },
    })
  }, 'Slot desvinculado e limpo.')
  vinculandoJogoId.value = null
}

async function salvarResultadoTorneioFn() {
  if (!torneio.value) return
  const torneioId = torneio.value.id

  salvandoResultadoTorneio.value = true
  await executarAcao(async () => {
    await requisicaoApi(`/admin/torneios/${torneioId}/resultado`, {
      metodo: 'PUT',
      corpo: {},
    })
  }, 'Resultado final salvo. Recalculo enviado para processamento.')
  salvandoResultadoTorneio.value = false
}

// Segue o seletor global do topo: ao trocar de bolão lá, o admin recarrega.
watch(() => bolaoAtivo.ativoId, (novoId) => {
  if (novoId && novoId !== torneioSelecionadoId.value) {
    carregarDados(novoId)
  }
})

onMounted(async () => {
  if (bolaoAtivo.lista.length === 0) {
    await bolaoAtivo.carregar()
  }
  await Promise.all([carregarDados(bolaoAtivo.ativoId ?? undefined), carregarBoloes()])
})
</script>
