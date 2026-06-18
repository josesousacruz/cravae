<template>
  <div class="mx-auto" :class="tabAtiva === 'palpites' && subTabAtiva === 'chaveamento' ? 'max-w-none' : 'max-w-6xl'">
    <!-- Loading -->
    <div v-if="carregando" class="flex items-center justify-center py-20">
      <span class="text-text-muted">Carregando...</span>
    </div>

    <div v-else-if="cupom && torneio" class="space-y-4 pb-24 sm:pb-0">
      <!-- Breadcrumb -->
      <nav class="text-sm text-text-muted">
        <RouterLink to="/painel" class="hover:text-text-secondary transition">Meus Cupons</RouterLink>
        <span class="mx-2">&gt;</span>
        <span>Cupom {{ cupom.codigo }}</span>
      </nav>

      <!-- Header -->
      <div class="flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-xl font-bold">Cupom {{ cupom.codigo }}</h1>
        <span
          class="rounded-full px-3 py-1 text-xs font-semibold"
          :class="cupom.status === 'ativo' ? 'bg-primary text-bg' : 'bg-warning/20 text-warning'"
        >
          {{ cupom.status === 'ativo' ? 'Cupom ativo' : 'Aguardando pagamento' }}
        </span>
      </div>

      <!-- Banner de pendencia: pagamento em destaque -->
      <div
        v-if="cupom.status !== 'ativo'"
        class="flex flex-col gap-3 rounded-2xl border border-[#32BCAD]/30 bg-[#32BCAD]/10 px-4 py-3.5 sm:flex-row sm:items-center sm:justify-between"
      >
        <div class="flex items-center gap-2.5">
          <svg class="h-6 w-6 shrink-0 text-[#32BCAD]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" /></svg>
          <p class="text-sm text-text-secondary">
            Cupom <strong class="text-text">aguardando pagamento</strong>. Pague via Pix para confirmar sua participacao.
          </p>
        </div>
        <button
          type="button"
          class="flex shrink-0 items-center justify-center gap-2.5 rounded-xl bg-[#32BCAD] px-5 py-3 text-sm font-bold text-black shadow-lg shadow-[#32BCAD]/25 transition-all hover:bg-[#2aa99b] hover:shadow-[#32BCAD]/40"
          @click="modalPagamentoAberto = true"
        >
          <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h3c.621 0 1.125.504 1.125 1.125v3c0 .621-.504 1.125-1.125 1.125h-3A1.125 1.125 0 013.75 7.875v-3zM3.75 16.125c0-.621.504-1.125 1.125-1.125h3c.621 0 1.125.504 1.125 1.125v3c0 .621-.504 1.125-1.125 1.125h-3a1.125 1.125 0 01-1.125-1.125v-3zM15 4.875c0-.621.504-1.125 1.125-1.125h3c.621 0 1.125.504 1.125 1.125v3c0 .621-.504 1.125-1.125 1.125h-3A1.125 1.125 0 0115 7.875v-3zM13.5 13.5h3m-3 3h.008v.008H13.5V16.5zm3 3h.008v.008H16.5V19.5zm3-3h.008v.008H19.5V16.5zm0 3h.008v.008H19.5V19.5z" /></svg>
          <span>Pagar via Pix{{ valorCupomFormatado ? ` · ${valorCupomFormatado}` : '' }}</span>
        </button>
      </div>

      <ModalPixPagamento
        :aberto="modalPagamentoAberto"
        :cupom-codigo="cupom.codigo"
        :valor="cupom.pedido_checkout?.valor"
        @fechar="modalPagamentoAberto = false"
      />

      <!-- Tabs: Palpites / Ranking / Meus Resultados -->
      <div class="hidden overflow-x-auto border-b border-border sm:flex">
        <button
          v-for="tab in tabs"
          :key="tab.id"
          @click="tabAtiva = tab.id"
          class="flex items-center gap-2 whitespace-nowrap px-5 py-3 text-sm transition cursor-pointer"
          :class="tabAtiva === tab.id ? 'border-b-2 border-primary text-primary font-medium' : 'text-text-muted hover:text-text-secondary'"
        >
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" :d="tab.icone" />
          </svg>
          {{ tab.nome }}
        </button>
      </div>

      <!-- ═══════ Tab Palpites ═══════ -->
      <section v-if="tabAtiva === 'palpites'" class="min-w-0 overflow-x-hidden">
        <div class="min-w-0 gap-6" :class="subTabAtiva === 'chaveamento' ? '' : 'lg:grid lg:grid-cols-[minmax(0,1fr)_300px]'">
          <!-- Main column -->
          <div class="min-w-0 space-y-4">
            <!-- Fase navigator -->
            <div class="flex min-w-0 items-center justify-between gap-2 rounded-xl border border-border bg-bg-card px-3 py-3 sm:px-4">
              <button @click="faseAnterior" class="shrink-0 rounded-lg p-1.5 text-text-muted transition hover:bg-bg-input hover:text-text" :disabled="indiceFase <= 0">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" /></svg>
              </button>
              <h2 class="min-w-0 truncate text-center text-sm font-bold sm:text-base">{{ tituloFaseAtual }}</h2>
              <button @click="faseProxima" class="shrink-0 rounded-lg p-1.5 text-text-muted transition hover:bg-bg-input hover:text-text">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
              </button>
            </div>

            <!-- Day selector — horizontal scroll, compact -->
            <div v-if="diasComJogos.length" class="flex gap-1.5 overflow-x-auto pb-1 scrollbar-none">
              <button
                v-for="dia in diasComJogos"
                :key="dia.data"
                @click="diaSelecionado = dia.data"
                class="relative flex shrink-0 items-center gap-1 rounded-lg px-2.5 py-1.5 text-center transition cursor-pointer"
                :class="diaSelecionado === dia.data
                  ? 'bg-primary text-bg'
                  : dia.semPalpite
                    ? 'bg-bg-card border border-warning/50 text-text-muted'
                    : 'bg-bg-card border border-border text-text-muted hover:border-primary/40'"
              >
                <span v-if="dia.semPalpite && diaSelecionado !== dia.data" class="absolute -top-1 -right-1 h-2 w-2 rounded-full bg-warning" />
                <span class="text-[10px] uppercase font-medium">{{ dia.diaSemana }}</span>
                <span class="text-sm font-bold">{{ dia.diaNumero }}</span>
                <span class="text-[10px] opacity-70"><sup>({{ dia.totalJogos }})</sup></span>
              </button>
            </div>

            <!-- Sub-tabs -->
            <div class="flex gap-2 overflow-x-auto">
              <button
                v-for="sub in subTabs"
                :key="sub.id"
                @click="subTabAtiva = sub.id"
                class="px-3 py-1.5 rounded-lg text-xs font-medium whitespace-nowrap transition cursor-pointer"
                :class="subTabAtiva === sub.id ? 'bg-primary/20 text-primary' : 'bg-bg-input text-text-muted hover:text-text-secondary'"
              >
                {{ sub.nome }}
              </button>
            </div>

            <!-- Auto-save indicator -->
            <div class="flex flex-wrap items-center gap-3">
              <span class="inline-flex items-center gap-1.5 rounded-full border border-primary/30 bg-primary/10 px-3 py-1 text-xs text-primary">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                {{ textoFechamento }}
              </span>
              <span v-if="salvando" class="inline-flex items-center gap-1.5 text-xs text-primary animate-pulse">
                <svg class="h-3 w-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                Salvando...
              </span>
              <span v-else-if="ultimoSalvo" class="inline-flex items-center gap-1 text-xs text-text-muted">
                <svg class="h-3 w-3 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                Salvo
              </span>
            </div>

            <!-- ═══ Sub-tab: Jogos ═══ -->
            <template v-if="subTabAtiva === 'jogos'">
            <!-- ═══ Banner: Palpite de pódio (visível só enquanto o pódio estiver aberto) ═══ -->
            <div
              v-if="!podioFechado"
              class="mb-4 overflow-hidden rounded-2xl border border-gold/40 bg-[radial-gradient(circle_at_0%_0%,rgba(251,191,36,0.16),transparent_45%),linear-gradient(135deg,#1a1404,#141414_62%)] shadow-lg shadow-black/30"
            >
              <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gold/15 px-4 py-3 sm:px-5">
                <div class="flex items-center gap-2.5">
                  <svg class="h-6 w-6 shrink-0 text-gold" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9 0a3 3 0 013 3h-15a3 3 0 013-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 01-.982-3.172M9.497 14.25a7.454 7.454 0 00.981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 007.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M7.73 9.728a6.726 6.726 0 002.748 1.35m8.272-6.842V4.5c0 2.108-.966 3.99-2.48 5.228m2.48-5.492a46.32 46.32 0 012.916.52 6.003 6.003 0 01-5.395 4.972m0 0a6.726 6.726 0 01-2.749 1.35m0 0a6.772 6.772 0 01-3.044 0" /></svg>
                  <div class="min-w-0">
                    <h3 class="text-sm font-black text-text sm:text-base">Palpite de pódio</h3>
                    <p class="text-[11px] text-text-muted">Campeão, vice e 3º lugar — fecha 1h antes do mata-mata</p>
                  </div>
                </div>
                <div
                  v-if="contagemPodio"
                  class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-bold"
                  :class="contagemPodio.urgente ? 'animate-pulse border-warning/50 bg-warning/15 text-warning' : 'border-gold/40 bg-gold/10 text-gold'"
                >
                  <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                  <span>Fecha em {{ textoContagemPodio }}</span>
                </div>
              </div>
              <div class="grid gap-3 p-4 sm:grid-cols-3 sm:px-5">
                <label class="block">
                  <span class="mb-1 block text-[10px] uppercase tracking-wide text-text-muted">Campeão</span>
                  <select v-model.number="palpitePodio.campeao" :disabled="podioFechado" class="w-full rounded-lg border border-gold/25 bg-bg-input px-2 py-2 text-sm text-text focus:border-gold/60 focus:outline-none disabled:opacity-50" @change="aoMudarPodio">
                    <option :value="null">A definir</option>
                    <option v-for="s in todasSelecoesOrdenadas" :key="`bc-${s.id}`" :value="s.id">{{ s.nome }}</option>
                  </select>
                </label>
                <label class="block">
                  <span class="mb-1 block text-[10px] uppercase tracking-wide text-text-muted">Vice</span>
                  <select v-model.number="palpitePodio.vice" :disabled="podioFechado" class="w-full rounded-lg border border-gold/25 bg-bg-input px-2 py-2 text-sm text-text focus:border-gold/60 focus:outline-none disabled:opacity-50" @change="aoMudarPodio">
                    <option :value="null">A definir</option>
                    <option v-for="s in todasSelecoesOrdenadas" :key="`bv-${s.id}`" :value="s.id">{{ s.nome }}</option>
                  </select>
                </label>
                <label class="block">
                  <span class="mb-1 block text-[10px] uppercase tracking-wide text-text-muted">Terceiro</span>
                  <select v-model.number="palpitePodio.terceiro" :disabled="podioFechado" class="w-full rounded-lg border border-gold/25 bg-bg-input px-2 py-2 text-sm text-text focus:border-gold/60 focus:outline-none disabled:opacity-50" @change="aoMudarPodio">
                    <option :value="null">A definir</option>
                    <option v-for="s in todasSelecoesOrdenadas" :key="`bt-${s.id}`" :value="s.id">{{ s.nome }}</option>
                  </select>
                </label>
              </div>
            </div>
            <!-- Match cards -->
            <div class="min-w-0 space-y-4">
              <div
                v-for="jogo in jogosDoDia"
                :key="jogo.id"
                class="min-w-0 overflow-hidden rounded-2xl border bg-bg-card p-4 transition-colors sm:p-5"
                :class="jogoCompleto(jogo) ? 'border-primary/30' : 'border-border'"
              >
                <!-- Top row: palpite status + quem palpitou + group -->
                <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                  <div class="flex min-w-0 flex-wrap items-center gap-2">
                    <span v-if="jogoSemConfronto(jogo)" class="inline-flex items-center gap-1 rounded-full bg-text-muted/15 px-2 py-0.5 text-[10px] font-medium text-text-muted">
                      <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                      Aguardando confronto
                    </span>
                    <span v-else-if="jogoCompleto(jogo)" class="inline-flex items-center gap-1 rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-medium text-primary">
                      <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                      Com palpite
                    </span>
                    <span v-else-if="jogoRepalpiteNecessario(jogo)" class="inline-flex items-center gap-1 rounded-full bg-warning/10 px-2 py-0.5 text-[10px] font-medium text-warning">
                      <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" /></svg>
                      Repalpite necessário
                    </span>
                    <span v-else class="inline-flex items-center gap-1 rounded-full bg-warning/10 px-2 py-0.5 text-[10px] font-medium text-warning">
                      <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" /></svg>
                      Sem palpite
                    </span>
                    <span class="text-xs text-text-muted">{{ formatarHora(jogo.data_hora_inicio) }}</span>
                    <span v-if="jogoFechado(jogo)" class="inline-flex items-center gap-1 rounded-full bg-text-muted/15 px-2 py-0.5 text-[10px] font-medium text-text-muted">
                      <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" /></svg>
                      Fechado
                    </span>
                  </div>
                  <div class="flex min-w-0 flex-wrap items-center gap-2 sm:gap-3">
                    <span v-if="jogo.grupo" class="text-xs font-medium text-primary">{{ jogo.grupo.nome }}</span>
                    <span v-else class="text-xs font-medium text-primary">{{ jogo.fase.nome }}</span>
                    <!-- Quem palpitou -->
                    <div class="relative">
                      <button
                        type="button"
                        data-palpiteiros
                        class="inline-flex items-center gap-1 rounded-full border border-primary/30 bg-primary/5 px-2.5 py-1 text-[10px] font-medium text-primary transition hover:bg-primary/10"
                        @click="togglePalpiteiros(jogo.id, $event)"
                      >
                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" /></svg>
                        Quem palpitou
                      </button>
                      <!-- Popover (teleportado para o body para escapar do overflow-hidden do card) -->
                      <Teleport to="body">
                        <div
                          v-if="palpiteirosAberto === jogo.id"
                          data-palpiteiros
                          class="fixed z-50 w-56 max-w-[calc(100vw-1rem)] rounded-xl border border-border bg-bg-card p-3 shadow-xl"
                          :style="{ top: `${palpiteirosPos.top}px`, left: `${palpiteirosPos.left}px` }"
                        >
                          <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-bold text-text">Palpiteiros</span>
                            <span class="rounded-full bg-primary/20 px-2 py-0.5 text-[10px] font-bold text-primary">
                              {{ palpiteirosCache[jogo.id]?.total ?? '...' }}
                            </span>
                          </div>
                          <div v-if="!palpiteirosCache[jogo.id]" class="py-2 text-center">
                            <span class="text-xs text-text-muted animate-pulse">Carregando...</span>
                          </div>
                          <div v-else-if="palpiteirosCache[jogo.id].total === 0" class="py-2 text-center">
                            <span class="text-xs text-text-muted">Ninguem palpitou ainda</span>
                          </div>
                          <div v-else class="max-h-48 space-y-1 overflow-y-auto">
                            <div
                              v-for="(p, pi) in palpiteirosCache[jogo.id].palpiteiros"
                              :key="pi"
                              class="flex items-center gap-2 rounded-lg px-2 py-1 hover:bg-bg-input"
                            >
                              <div class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-primary/20 text-[9px] font-bold text-primary">
                                {{ p.nome.charAt(0).toUpperCase() }}
                              </div>
                              <span class="text-xs text-text-secondary truncate">{{ p.nome }}</span>
                            </div>
                          </div>
                        </div>
                      </Teleport>
                    </div>
                  </div>
                </div>

                <!-- Teams + score (grade de 2 linhas: bandeiras+placar / nomes+resultado) -->
                <div class="grid min-w-0 grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-x-3 gap-y-2 sm:gap-x-8">
                  <!-- Linha 1: bandeira mandante | placar | bandeira visitante -->
                  <img
                    :src="bandeira(jogo.selecao_mandante?.sigla ?? '')"
                    :alt="jogo.selecao_mandante?.nome ?? 'A definir'"
                    class="mx-auto h-10 w-14 rounded object-cover shadow"
                    @error="($event.target as HTMLImageElement).style.visibility='hidden'"
                  />

                  <div class="flex items-center justify-center gap-1 sm:gap-2">
                    <div class="flex items-center gap-0.5">
                      <button type="button" :disabled="jogoFechado(jogo) || jogoSemConfronto(jogo)" class="flex h-7 w-7 items-center justify-center rounded-lg bg-bg-input text-text-muted transition hover:bg-primary/20 hover:text-primary disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-bg-input disabled:hover:text-text-muted" @click="decrementarPlacar(jogo.id, 'mandante')">-</button>
                      <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-bg-input text-base font-bold" :class="placaresGrupos[jogo.id]?.placar_mandante !== '' ? 'text-text' : 'text-text-muted'">
                        {{ placaresGrupos[jogo.id]?.placar_mandante !== '' ? placaresGrupos[jogo.id]?.placar_mandante : '-' }}
                      </div>
                      <button type="button" :disabled="jogoFechado(jogo) || jogoSemConfronto(jogo)" class="flex h-7 w-7 items-center justify-center rounded-lg bg-bg-input text-text-muted transition hover:bg-primary/20 hover:text-primary disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-bg-input disabled:hover:text-text-muted" @click="incrementarPlacar(jogo.id, 'mandante')">+</button>
                    </div>

                    <span class="text-xs text-text-muted font-medium">x</span>

                    <div class="flex items-center gap-0.5">
                      <button type="button" :disabled="jogoFechado(jogo) || jogoSemConfronto(jogo)" class="flex h-7 w-7 items-center justify-center rounded-lg bg-bg-input text-text-muted transition hover:bg-primary/20 hover:text-primary disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-bg-input disabled:hover:text-text-muted" @click="decrementarPlacar(jogo.id, 'visitante')">-</button>
                      <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-bg-input text-base font-bold" :class="placaresGrupos[jogo.id]?.placar_visitante !== '' ? 'text-text' : 'text-text-muted'">
                        {{ placaresGrupos[jogo.id]?.placar_visitante !== '' ? placaresGrupos[jogo.id]?.placar_visitante : '-' }}
                      </div>
                      <button type="button" :disabled="jogoFechado(jogo) || jogoSemConfronto(jogo)" class="flex h-7 w-7 items-center justify-center rounded-lg bg-bg-input text-text-muted transition hover:bg-primary/20 hover:text-primary disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-bg-input disabled:hover:text-text-muted" @click="incrementarPlacar(jogo.id, 'visitante')">+</button>
                    </div>
                  </div>

                  <img
                    :src="bandeira(jogo.selecao_visitante?.sigla ?? '')"
                    :alt="jogo.selecao_visitante?.nome ?? 'A definir'"
                    class="mx-auto h-10 w-14 rounded object-cover shadow"
                    @error="($event.target as HTMLImageElement).style.visibility='hidden'"
                  />

                  <!-- Linha 2: nome mandante | resultado real | nome visitante -->
                  <p class="break-words text-center text-xs font-medium sm:text-sm">{{ jogo.selecao_mandante?.nome ?? 'A definir' }}</p>

                  <div class="flex items-center justify-center gap-1.5">
                    <template v-if="temResultadoReal(jogo)">
                      <span class="text-[9px] font-medium uppercase tracking-wide text-text-muted">Resultado</span>
                      <span class="inline-flex items-center gap-1 rounded-md bg-bg-input px-2 py-0.5 text-xs font-bold text-text">
                        {{ jogo.resultado?.placar_mandante }}
                        <span class="text-text-muted">x</span>
                        {{ jogo.resultado?.placar_visitante }}
                      </span>
                    </template>
                  </div>

                  <p class="break-words text-center text-xs font-medium sm:text-sm">{{ jogo.selecao_visitante?.nome ?? 'A definir' }}</p>
                </div>

                <!-- Knockout: penalties on draw -->
                <div v-if="jogo.fase.tipo !== 'grupos' && placaresEliminatorios[jogo.id]" class="mt-4">
                  <div v-if="precisaPenaltis(jogo.id)" class="rounded-xl border border-primary/20 bg-primary/5 p-3">
                    <span class="mb-2 block text-xs font-medium text-primary">Empate: informe os penaltis</span>
                    <div class="flex items-center justify-center gap-2">
                      <div class="flex items-center gap-0.5">
                        <button type="button" :disabled="jogoFechado(jogo) || jogoSemConfronto(jogo)" class="flex h-7 w-7 items-center justify-center rounded-lg bg-bg-input text-text-muted transition hover:bg-primary/20 hover:text-primary disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-bg-input disabled:hover:text-text-muted" @click="decrementarPenal(jogo.id, 'mandante')">-</button>
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-bg-input text-base font-bold" :class="placaresEliminatorios[jogo.id].penal_mandante !== '' ? 'text-text' : 'text-text-muted'">
                          {{ placaresEliminatorios[jogo.id].penal_mandante !== '' ? placaresEliminatorios[jogo.id].penal_mandante : '-' }}
                        </div>
                        <button type="button" :disabled="jogoFechado(jogo) || jogoSemConfronto(jogo)" class="flex h-7 w-7 items-center justify-center rounded-lg bg-bg-input text-text-muted transition hover:bg-primary/20 hover:text-primary disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-bg-input disabled:hover:text-text-muted" @click="incrementarPenal(jogo.id, 'mandante')">+</button>
                      </div>
                      <span class="text-xs text-text-muted font-medium">x</span>
                      <div class="flex items-center gap-0.5">
                        <button type="button" :disabled="jogoFechado(jogo) || jogoSemConfronto(jogo)" class="flex h-7 w-7 items-center justify-center rounded-lg bg-bg-input text-text-muted transition hover:bg-primary/20 hover:text-primary disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-bg-input disabled:hover:text-text-muted" @click="decrementarPenal(jogo.id, 'visitante')">-</button>
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-bg-input text-base font-bold" :class="placaresEliminatorios[jogo.id].penal_visitante !== '' ? 'text-text' : 'text-text-muted'">
                          {{ placaresEliminatorios[jogo.id].penal_visitante !== '' ? placaresEliminatorios[jogo.id].penal_visitante : '-' }}
                        </div>
                        <button type="button" :disabled="jogoFechado(jogo) || jogoSemConfronto(jogo)" class="flex h-7 w-7 items-center justify-center rounded-lg bg-bg-input text-text-muted transition hover:bg-primary/20 hover:text-primary disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-bg-input disabled:hover:text-text-muted" @click="incrementarPenal(jogo.id, 'visitante')">+</button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div v-if="!jogosDoDia.length" class="rounded-2xl border border-border bg-bg-card py-12 text-center">
                <p class="text-text-muted">Nenhum jogo neste dia.</p>
              </div>
            </div>

            </template>

            <div v-if="subTabAtiva === 'chaveamento'" class="space-y-4">
              <section class="overflow-hidden rounded-3xl border border-[#d4af37]/35 bg-[radial-gradient(circle_at_50%_45%,rgba(212,175,55,0.16),transparent_18%),linear-gradient(135deg,#050505_0%,#171104_46%,#000000_100%)] shadow-2xl shadow-black/40">
                <div class="border-b border-white/10 px-4 py-4 sm:px-5">
                  <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                      <span class="text-[10px] font-black uppercase tracking-[0.28em] text-[#d4af37]">Chaveamento</span>
                      <h2 class="mt-1 text-lg font-black text-white sm:text-2xl">Caminho ate a final</h2>
                    </div>
                    <span class="rounded-full border border-white/20 bg-white/10 px-3 py-1 text-xs font-semibold text-white/80">
                      {{ gruposPreenchidos }}/{{ totalJogosGrupos }} jogos de grupos palpitados
                    </span>
                  </div>
                </div>

                <div class="p-3 sm:p-4">
                  <div class="overflow-x-auto pb-2 scrollbar-none 2xl:overflow-visible">
                    <div class="grid min-w-[920px] grid-cols-[minmax(104px,.64fr)_minmax(104px,.64fr)_minmax(104px,.64fr)_minmax(104px,.64fr)_minmax(112px,.68fr)_minmax(104px,.64fr)_minmax(104px,.64fr)_minmax(104px,.64fr)_minmax(104px,.64fr)] items-center gap-2 2xl:min-w-0 2xl:w-full">
                      <section class="flex min-h-[920px] flex-col justify-around gap-2">
                        <h3 class="text-center text-[10px] font-black uppercase tracking-[0.22em] text-[#d4af37]">32 avos</h3>
                        <component :is="JogoChaveamento"
                          v-for="jogo in jogosPorSlugEOrdem('round_of_32', 1, 8)"
                          :key="jogo.id"
                          :jogo="jogo"
                          :lados="ladosJogoChaveamento(jogo)"
                          :invertido="false"
                        />
                      </section>

                      <section class="flex min-h-[920px] flex-col justify-around gap-2">
                        <h3 class="text-center text-[10px] font-black uppercase tracking-[0.22em] text-[#d4af37]">Oitavas</h3>
                        <component :is="JogoChaveamento"
                          v-for="jogo in jogosPorSlugEOrdem('oitavas_de_final', 1, 4)"
                          :key="jogo.id"
                          :jogo="jogo"
                          :lados="ladosJogoChaveamento(jogo)"
                          :invertido="false"
                        />
                      </section>

                      <section class="flex min-h-[920px] flex-col justify-around gap-2">
                        <h3 class="text-center text-[10px] font-black uppercase tracking-[0.22em] text-[#d4af37]">Quartas</h3>
                        <component :is="JogoChaveamento"
                          v-for="jogo in jogosPorSlugEOrdem('quartas_de_final', 1, 2)"
                          :key="jogo.id"
                          :jogo="jogo"
                          :lados="ladosJogoChaveamento(jogo)"
                          :invertido="false"
                        />
                      </section>

                      <section class="flex min-h-[920px] flex-col justify-around gap-2">
                        <h3 class="text-center text-[10px] font-black uppercase tracking-[0.22em] text-[#d4af37]">Semifinal</h3>
                        <component :is="JogoChaveamento"
                          v-for="jogo in jogosPorSlugEOrdem('semifinais', 1, 1)"
                          :key="jogo.id"
                          :jogo="jogo"
                          :lados="ladosJogoChaveamento(jogo)"
                          :invertido="false"
                        />
                      </section>

                      <section class="flex min-h-[920px] flex-col items-center justify-center gap-4 text-center">
                        <span class="text-[10px] font-black uppercase tracking-[0.28em] text-[#d4af37]">Final</span>
                        <div class="flex h-24 w-24 items-center justify-center rounded-full border border-[#d4af37]/50 bg-[#d4af37]/15 shadow-2xl shadow-[#d4af37]/20">
                          <img :src="tacaCopaAsset" alt="Taca da Copa do Mundo" class="h-32 w-auto object-contain drop-shadow-[0_0_18px_rgba(212,175,55,0.45)]" />
                        </div>
                        <component :is="JogoChaveamento"
                          v-for="jogo in jogosPorSlugEOrdem('final', 1, 1)"
                          :key="jogo.id"
                          :jogo="jogo"
                          :lados="ladosJogoChaveamento(jogo)"
                          :invertido="false"
                        />
                      </section>

                      <section class="flex min-h-[920px] flex-col justify-around gap-2">
                        <h3 class="text-center text-[10px] font-black uppercase tracking-[0.22em] text-[#d4af37]">Semifinal</h3>
                        <component :is="JogoChaveamento"
                          v-for="jogo in jogosPorSlugEOrdem('semifinais', 2, 2)"
                          :key="jogo.id"
                          :jogo="jogo"
                          :lados="ladosJogoChaveamento(jogo)"
                          :invertido="true"
                        />
                      </section>

                      <section class="flex min-h-[920px] flex-col justify-around gap-2">
                        <h3 class="text-center text-[10px] font-black uppercase tracking-[0.22em] text-[#d4af37]">Quartas</h3>
                        <component :is="JogoChaveamento"
                          v-for="jogo in jogosPorSlugEOrdem('quartas_de_final', 3, 4)"
                          :key="jogo.id"
                          :jogo="jogo"
                          :lados="ladosJogoChaveamento(jogo)"
                          :invertido="true"
                        />
                      </section>

                      <section class="flex min-h-[920px] flex-col justify-around gap-2">
                        <h3 class="text-center text-[10px] font-black uppercase tracking-[0.22em] text-[#d4af37]">Oitavas</h3>
                        <component :is="JogoChaveamento"
                          v-for="jogo in jogosPorSlugEOrdem('oitavas_de_final', 5, 8)"
                          :key="jogo.id"
                          :jogo="jogo"
                          :lados="ladosJogoChaveamento(jogo)"
                          :invertido="true"
                        />
                      </section>

                      <section class="flex min-h-[920px] flex-col justify-around gap-2">
                        <h3 class="text-center text-[10px] font-black uppercase tracking-[0.22em] text-[#d4af37]">32 avos</h3>
                        <component :is="JogoChaveamento"
                          v-for="jogo in jogosPorSlugEOrdem('round_of_32', 9, 16)"
                          :key="jogo.id"
                          :jogo="jogo"
                          :lados="ladosJogoChaveamento(jogo)"
                          :invertido="true"
                        />
                      </section>

                    </div>
                  </div>
                </div>
              </section>

              <!-- Palpite de pódio (campeão/vice/3º): resumo. A edição fica no banner da aba Jogos. -->
              <div class="rounded-2xl border border-border bg-bg-card p-4">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                  <h3 class="text-sm font-bold text-text">Palpite de pódio</h3>
                  <span v-if="podioFechado" class="rounded-full bg-text-muted/15 px-2 py-0.5 text-[10px] font-medium text-text-muted">Fechado</span>
                  <button
                    v-else
                    type="button"
                    class="inline-flex items-center gap-1 rounded-full border border-gold/40 bg-gold/10 px-2.5 py-0.5 text-[10px] font-medium text-gold transition hover:bg-gold/20"
                    @click="subTabAtiva = 'jogos'"
                  >
                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125" /></svg>
                    Editar na aba Jogos
                  </button>
                </div>
                <div class="grid gap-2 text-sm sm:grid-cols-3">
                  <div><span class="text-[10px] uppercase tracking-wide text-text-muted">Campeão</span><p class="font-medium text-text">{{ nomeSelecaoPorId(palpitePodio.campeao) }}</p></div>
                  <div><span class="text-[10px] uppercase tracking-wide text-text-muted">Vice</span><p class="font-medium text-text">{{ nomeSelecaoPorId(palpitePodio.vice) }}</p></div>
                  <div><span class="text-[10px] uppercase tracking-wide text-text-muted">Terceiro</span><p class="font-medium text-text">{{ nomeSelecaoPorId(palpitePodio.terceiro) }}</p></div>
                </div>
                <div v-if="resumoBracketIds.podio_real.campeao" class="mt-4 grid gap-2 border-t border-border pt-4 text-xs sm:grid-cols-3">
                  <div><span class="text-text-muted">Campeão real: </span><span class="font-medium text-text">{{ nomeSelecaoPorId(resumoBracketIds.podio_real.campeao) }}</span></div>
                  <div><span class="text-text-muted">Vice real: </span><span class="font-medium text-text">{{ nomeSelecaoPorId(resumoBracketIds.podio_real.vice) }}</span></div>
                  <div><span class="text-text-muted">3º real: </span><span class="font-medium text-text">{{ nomeSelecaoPorId(resumoBracketIds.podio_real.terceiro) }}</span></div>
                </div>
              </div>
            </div>
          </div>

          <!-- Sidebar: Ranking ao Vivo -->
          <div v-if="subTabAtiva !== 'chaveamento'" class="hidden lg:block">
            <div class="sticky top-20 overflow-hidden rounded-3xl border border-primary/20 bg-[radial-gradient(circle_at_top,#123225,transparent_40%),linear-gradient(180deg,#111513,#0b0d0c)] p-4">
              <div class="mb-4 flex items-center justify-between gap-2">
                <h3 class="text-sm font-bold">Ranking ao Vivo</h3>
                <span class="rounded-full border border-primary/30 bg-primary/10 px-2 py-0.5 text-[10px] text-primary">Top 3</span>
              </div>

              <div v-if="carregandoRanking" class="space-y-3">
                <div v-for="n in 3" :key="n" class="h-24 animate-pulse rounded-2xl bg-bg-card/50" />
              </div>

              <div v-else-if="!ranking.length" class="py-4 text-center text-xs text-text-muted">
                Nenhum palpite registrado
              </div>

              <div v-else class="space-y-3">
                <article
                  v-for="(item, i) in podioRanking"
                  :key="item.id"
                  class="rounded-2xl border px-3 py-3"
                  :class="classePosicaoRanking(i)"
                >
                  <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full border text-xs font-bold" :class="classePosicaoRanking(i)">
                      {{ i + 1 }}
                    </div>
                    <div class="flex h-12 w-12 items-center justify-center rounded-full border border-current/40 text-sm font-bold">
                      {{ iniciaisRanking(item.cupom.usuario.nome) }}
                    </div>
                    <div class="min-w-0 flex-1">
                      <p class="truncate text-sm font-medium">{{ item.cupom.usuario.nome }}</p>
                      <p class="text-[10px] uppercase tracking-[0.18em] text-text-muted">Cupom {{ item.cupom.codigo }}</p>
                    </div>
                    <div class="text-right">
                      <strong class="block text-lg font-black">{{ item.pontuacao_total }}</strong>
                      <span class="text-[10px] text-text-muted">pts</span>
                    </div>
                  </div>
                </article>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- ═══════ Tab Ranking ═══════ -->
      <section v-if="tabAtiva === 'ranking'">
        <RankingConteudo :cupom-destaque="cupom.id" />
      </section>

      <!-- ═══════ Tab Meus Resultados ═══════ -->
      <section v-if="tabAtiva === 'resultados'">
        <div v-if="cupom.eventos_pontuacao?.length" class="rounded-2xl border border-border bg-bg-card p-5">
          <h2 class="mb-4 text-lg font-bold">Eventos de Pontuacao</h2>
          <div class="space-y-2">
            <div v-for="evento in cupom.eventos_pontuacao" :key="evento.id" class="flex items-center justify-between gap-3 rounded-lg bg-bg-input px-3.5 py-2.5">
              <div class="min-w-0">
                <span class="block text-sm">{{ evento.descricao }}</span>
                <span v-if="descricaoJogo(evento)" class="mt-0.5 block truncate text-xs text-text-muted">{{ descricaoJogo(evento) }}</span>
              </div>
              <span class="shrink-0 text-sm font-bold text-primary">+{{ evento.pontos }} pts</span>
            </div>
          </div>
        </div>
        <div v-else class="rounded-2xl border border-border bg-bg-card py-12 text-center">
          <p class="text-text-muted">Nenhum evento de pontuacao registrado ainda.</p>
        </div>
      </section>

      <nav class="fixed inset-x-0 bottom-0 z-40 border-t border-border bg-bg-card/95 px-2 py-2 backdrop-blur sm:hidden">
        <div class="mx-auto grid max-w-md grid-cols-3 gap-1">
          <button
            v-for="tab in tabs"
            :key="`mobile-${tab.id}`"
            type="button"
            class="flex flex-col items-center justify-center gap-1 rounded-xl px-2 py-2 text-[11px] font-medium transition"
            :class="tabAtiva === tab.id ? 'text-primary' : 'text-text-muted'"
            @click="tabAtiva = tab.id"
          >
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
              <path stroke-linecap="round" stroke-linejoin="round" :d="tab.icone" />
            </svg>
            <span>{{ tab.nome === 'Meus Resultados' ? 'Resultados' : tab.nome }}</span>
          </button>
        </div>
      </nav>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, defineComponent, h, onBeforeUnmount, onMounted, ref, watch, type PropType } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { requisicaoApi } from '../services/api'
import { useToast } from '../composables/useToast'
import type { Aposta, BracketJogoCupom, Cupom, RankingItem, ResumoBracketReal, Selecao, Torneio } from '../tipos'
import tacaCopaAsset from '../assets/taca-copa-transparente.png'
import ModalPixPagamento from '../components/ModalPixPagamento.vue'
import RankingConteudo from '../components/RankingConteudo.vue'
import { useEventosCupom } from '../composables/useEventosCupom'

const rota = useRoute()
const { mostrar } = useToast()
const { descricaoJogo } = useEventosCupom()

const torneio = ref<Torneio | null>(null)
const cupom = ref<Cupom | null>(null)
const apostas = ref<Aposta[]>([])
const bracketCupom = ref<BracketJogoCupom[]>([])
const resumoBracketIds = ref<ResumoBracketReal>({
  podio_palpite: { campeao: null, vice: null, terceiro: null },
  podio_real: { campeao: null, vice: null, terceiro: null },
})
// Palpite de campeao/vice/3o (aposta 'podio'), escolhido pelo usuario.
const palpitePodio = ref<{ campeao: number | null; vice: number | null; terceiro: number | null }>({
  campeao: null,
  vice: null,
  terceiro: null,
})
const ranking = ref<RankingItem[]>([])
const carregando = ref(true)
const carregandoRanking = ref(false)
const modalPagamentoAberto = ref(false)
const valorCupomFormatado = computed(() => {
  const numero = Number(cupom.value?.pedido_checkout?.valor)
  return Number.isFinite(numero) && numero > 0
    ? new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(numero)
    : ''
})

const tabAtiva = ref<'palpites' | 'ranking' | 'resultados'>('palpites')
const subTabAtiva = ref<'jogos' | 'chaveamento'>('jogos')
const indiceFase = ref(0)
// Relogio reativo (atualizado a cada segundo) para o fechamento do podio e a contagem regressiva.
const agoraMs = ref(Date.now())
let relogioTimer: ReturnType<typeof setInterval> | null = null
const diaSelecionado = ref('')

const tabs = [
  { id: 'palpites' as const, nome: 'Palpites', icone: 'M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125' },
  { id: 'ranking' as const, nome: 'Ranking', icone: 'M16.5 18.75h-9m9 0a3 3 0 013 3h-15a3 3 0 013-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872' },
  { id: 'resultados' as const, nome: 'Meus Resultados', icone: 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z' },
]

const subTabs = [
  { id: 'jogos' as const, nome: 'Jogos' },
  { id: 'chaveamento' as const, nome: 'Chaveamento' },
]

const placaresGrupos = ref<Record<number, { placar_mandante: string; placar_visitante: string }>>({})
const placaresEliminatorios = ref<Record<number, { placar_mandante: string; placar_visitante: string; penal_mandante: string; penal_visitante: string }>>({})

// Auto-save
const salvando = ref(false)
const ultimoSalvo = ref(false)
let autoSaveTimer: ReturnType<typeof setTimeout> | null = null
let salvarNovamente = false

// Quem palpitou
const palpiteirosAberto = ref<number | null>(null)
const palpiteirosPos = ref<{ top: number; left: number }>({ top: 0, left: 0 })
const palpiteirosCache = ref<Record<number, { total: number; palpiteiros: { nome: string; cupom_codigo: string }[] }>>({})

const LARGURA_POPOVER = 224 // w-56

async function togglePalpiteiros(jogoId: number, evento: MouseEvent) {
  if (palpiteirosAberto.value === jogoId) {
    palpiteirosAberto.value = null
    return
  }

  const rect = (evento.currentTarget as HTMLElement).getBoundingClientRect()
  const left = Math.max(8, rect.right - LARGURA_POPOVER)
  palpiteirosPos.value = { top: rect.bottom + 4, left }
  palpiteirosAberto.value = jogoId

  if (!palpiteirosCache.value[jogoId]) {
    try {
      const r = await requisicaoApi<{ total: number; palpiteiros: { nome: string; cupom_codigo: string }[] }>(`/jogos/${jogoId}/palpiteiros`)
      palpiteirosCache.value[jogoId] = r
    } catch {
      palpiteirosCache.value[jogoId] = { total: 0, palpiteiros: [] }
    }
  }
}

// Fecha o popover ao clicar fora dele ou ao rolar a página (posicao fixa fica descolada do botao).
function fecharPalpiteiros(evento?: Event) {
  if (palpiteirosAberto.value === null) return
  if (evento?.type === 'mousedown') {
    const alvo = evento.target as HTMLElement | null
    if (alvo?.closest('[data-palpiteiros]')) return
  }
  palpiteirosAberto.value = null
}

onMounted(() => {
  document.addEventListener('mousedown', fecharPalpiteiros)
  window.addEventListener('scroll', fecharPalpiteiros, true)
  relogioTimer = setInterval(() => { agoraMs.value = Date.now() }, 1000)
})

onBeforeUnmount(() => {
  document.removeEventListener('mousedown', fecharPalpiteiros)
  window.removeEventListener('scroll', fecharPalpiteiros, true)
  if (relogioTimer) clearInterval(relogioTimer)
})

function temResultadoReal(jogo: JogoCupom): boolean {
  const r = jogo.resultado
  return !!r && r.placar_mandante !== null && r.placar_visitante !== null
}

// Mata-mata cujo confronto real ainda nao foi definido (algum lado "A definir").
// Palpitar nesse caso e ignorado pelo backend, entao a UI desabilita e nao envia.
function jogoSemConfronto(jogo: JogoCupom): boolean {
  return jogo.fase.tipo !== 'grupos' && (!jogo.selecao_mandante || !jogo.selecao_visitante)
}

function jogoIndisponivelPorId(jogoId: number): boolean {
  if (jogoFechadoPorId(jogoId)) return true
  const jogo = jogosPorId.value.get(jogoId)
  return jogo ? jogoSemConfronto(jogo) : false
}

// Palpite de eliminatoria "obsoleto": feito sobre um confronto antigo (ex.: modelo de
// fantasia) cujo classificado salvo NAO esta entre os times reais deste jogo. Tratamos
// como vazio para o usuario repalpitar o confronto real (evita pre-preencher placar errado).
function apostaEliminatoriaObsoleta(jogo: JogoCupom, aposta?: Aposta): boolean {
  if (jogo.fase.tipo === 'grupos' || !aposta) return false
  const m = jogo.selecao_mandante?.id
  const v = jogo.selecao_visitante?.id
  if (!m || !v) return false // sem confronto real ainda: jogoSemConfronto cuida disso
  const classificado = (aposta.conteudo as Record<string, number | null>)?.selecao_classificada_id
  if (!classificado) return false
  return classificado !== m && classificado !== v
}

function jogoRepalpiteNecessario(jogo: JogoCupom): boolean {
  return apostaEliminatoriaObsoleta(jogo, encontrarAposta('placar_jogo_eliminatoria', jogo.id))
}

// FIFA code → ISO 2-letter for flags
const fifaParaIso: Record<string, string> = {
  MEX: 'mx', RSA: 'za', KOR: 'kr', CAN: 'ca', QAT: 'qa', SUI: 'ch',
  BRA: 'br', MAR: 'ma', HAI: 'ht', SCO: 'gb-sct', USA: 'us', PAR: 'py',
  AUS: 'au', GER: 'de', CUW: 'cw', CIV: 'ci', ECU: 'ec', NED: 'nl',
  JPN: 'jp', TUN: 'tn', BEL: 'be', EGY: 'eg', IRN: 'ir', NZL: 'nz',
  ESP: 'es', CPV: 'cv', KSA: 'sa', URU: 'uy', FRA: 'fr', SEN: 'sn',
  NOR: 'no', ARG: 'ar', ALG: 'dz', AUT: 'at', JOR: 'jo', POR: 'pt',
  UZB: 'uz', COL: 'co', ENG: 'gb-eng', CRO: 'hr', GHA: 'gh', PAN: 'pa',
  UD4: '', UA1: '', UC3: '', UB2: '', IC1: '', IC2: '',
}

type JogoCupom = Torneio['jogos'][number] | BracketJogoCupom
type LadoChaveamento = {
  chave: string
  selecao: Selecao | null
  bandeiraUrl: string
  placar: string
  vencedor: boolean
}

const JogoChaveamento = defineComponent({
  props: {
    jogo: { type: Object as PropType<BracketJogoCupom>, required: true },
    lados: { type: Array as PropType<LadoChaveamento[]>, required: true },
    invertido: { type: Boolean, required: true },
  },
  setup(props) {
    return () => h('article', {
      class: [
        'relative rounded-xl border border-[#d4af37]/45 bg-[#171104]/70 p-2 text-white shadow-lg shadow-black/20',
        props.invertido
          ? 'before:absolute before:right-full before:top-1/2 before:hidden before:h-px before:w-3 before:bg-[#d4af37]/45 xl:before:block'
          : 'before:absolute before:left-full before:top-1/2 before:hidden before:h-px before:w-3 before:bg-[#d4af37]/45 xl:before:block',
        props.jogo.bloqueado ? 'opacity-55' : '',
      ],
    }, [
      h('span', { class: ['mb-1 block text-[9px] font-bold uppercase tracking-[0.18em] text-white/45', props.invertido ? 'text-right' : ''] }, `Jogo ${props.jogo.ordem_na_fase}`),
      h('div', { class: 'space-y-1' }, props.lados.map((lado) => h('div', {
        key: lado.chave,
        class: [
          props.invertido ? 'grid-cols-[auto_1fr_22px]' : 'grid-cols-[22px_1fr_auto]',
          'grid items-center gap-1.5 rounded-lg border px-1.5 py-1',
          lado.vencedor ? 'border-[#d4af37]/60 bg-[#d4af37]/20' : 'border-white/10 bg-black/15',
        ],
      }, props.invertido ? [
        h('span', { class: 'text-[10px] font-black text-white/70' }, lado.placar),
        h('span', { class: 'min-w-0 truncate text-right text-[11px] font-semibold' }, lado.selecao?.nome ?? 'A definir'),
        lado.bandeiraUrl
          ? h('img', { src: lado.bandeiraUrl, alt: lado.selecao?.nome ?? '', class: 'h-4 w-5 rounded object-cover' })
          : h('span', { class: 'h-4 w-5 rounded border border-dashed border-white/25' }),
      ] : [
        lado.bandeiraUrl
          ? h('img', { src: lado.bandeiraUrl, alt: lado.selecao?.nome ?? '', class: 'h-4 w-5 rounded object-cover' })
          : h('span', { class: 'h-4 w-5 rounded border border-dashed border-white/25' }),
        h('span', { class: 'min-w-0 truncate text-[11px] font-semibold' }, lado.selecao?.nome ?? 'A definir'),
        h('span', { class: 'text-[10px] font-black text-white/70' }, lado.placar),
      ]))),
    ])
  },
})

function bandeira(sigla: string): string {
  const iso = fifaParaIso[sigla]
  if (!iso) return ''
  return `https://flagcdn.com/w80/${iso}.png`
}

// Chaveamento mostra o resultado REAL (read-only): placar e vencedor reais do jogo.
function placarChaveamento(jogo: BracketJogoCupom, lado: 'mandante' | 'visitante'): string {
  const r = jogo.resultado
  if (!r || r.placar_mandante === null || r.placar_visitante === null) return '-'
  return String(lado === 'mandante' ? r.placar_mandante : r.placar_visitante)
}

function vencedorChaveamentoId(jogo: BracketJogoCupom): number | null {
  return jogo.resultado?.selecao_classificada_id ?? null
}

function ladosJogoChaveamento(jogo: BracketJogoCupom) {
  const vencedorId = vencedorChaveamentoId(jogo)
  return [
    {
      chave: 'mandante',
      selecao: jogo.selecao_mandante,
      bandeiraUrl: bandeira(jogo.selecao_mandante?.sigla ?? ''),
      placar: placarChaveamento(jogo, 'mandante'),
      vencedor: Boolean(vencedorId && jogo.selecao_mandante?.id === vencedorId),
    },
    {
      chave: 'visitante',
      selecao: jogo.selecao_visitante,
      bandeiraUrl: bandeira(jogo.selecao_visitante?.sigla ?? ''),
      placar: placarChaveamento(jogo, 'visitante'),
      vencedor: Boolean(vencedorId && jogo.selecao_visitante?.id === vencedorId),
    },
  ]
}

function jogosPorSlugEOrdem(slug: string, inicio: number, fim: number): BracketJogoCupom[] {
  return jogosEliminatoriosDoCupom.value
    .filter((jogo) => jogo.fase.slug === slug && jogo.ordem_na_fase >= inicio && jogo.ordem_na_fase <= fim)
    .sort((a, b) => a.ordem_na_fase - b.ordem_na_fase)
}

function jogoPossuiPlacares(jogoId: number): boolean {
  return placaresGrupos.value[jogoId]?.placar_mandante !== '' && placaresGrupos.value[jogoId]?.placar_visitante !== ''
}

function precisaPenaltis(jogoId: number): boolean {
  const placar = placaresGrupos.value[jogoId]
  return Boolean(placar && placar.placar_mandante !== '' && placar.placar_visitante !== '' && placar.placar_mandante === placar.placar_visitante)
}

function jogoCompleto(jogo: JogoCupom): boolean {
  if (!jogoPossuiPlacares(jogo.id)) return false
  if (jogo.fase.tipo === 'grupos') return true
  if (!precisaPenaltis(jogo.id)) return true
  const penal = placaresEliminatorios.value[jogo.id]
  return Boolean(
    penal
    && penal.penal_mandante !== ''
    && penal.penal_visitante !== ''
    && penal.penal_mandante !== penal.penal_visitante,
  )
}

// Primeiro jogo de cada (rodada, dia) em ms. Os palpites de grupos fecham por dia,
// 1h antes do primeiro jogo daquela rodada no dia (cada rodada e independente).
const primeiroJogoRodadaDiaMs = computed(() => {
  const mapa = new Map<string, number>()
  for (const jogo of torneio.value?.jogos ?? []) {
    if (!jogo.data_hora_inicio) continue
    const chave = `${jogo.rodada?.id ?? 'sem-rodada'}|${jogo.data_hora_inicio.substring(0, 10)}`
    const inicio = new Date(jogo.data_hora_inicio).getTime()
    if (!mapa.has(chave) || inicio < (mapa.get(chave) as number)) mapa.set(chave, inicio)
  }
  return mapa
})

// Espelha a regra de fechamento do backend (ServicoFechamentoApostas):
// grupos fecham POR DIA, 1h antes do primeiro jogo da rodada naquele dia
// (data_fechamento da rodada e um override opcional); mata-mata fecha no inicio do jogo.
function jogoFechado(jogo: JogoCupom): boolean {
  if (!jogo.data_hora_inicio) return false

  let referenciaMs: number
  if (jogo.fase.tipo === 'grupos') {
    if (jogo.rodada?.data_fechamento) {
      referenciaMs = new Date(jogo.rodada.data_fechamento).getTime()
    } else {
      const chave = `${jogo.rodada?.id ?? 'sem-rodada'}|${jogo.data_hora_inicio.substring(0, 10)}`
      const primeiro = primeiroJogoRodadaDiaMs.value.get(chave) ?? new Date(jogo.data_hora_inicio).getTime()
      referenciaMs = primeiro - 3600000
    }
  } else {
    referenciaMs = new Date(jogo.data_hora_inicio).getTime()
  }

  return Date.now() >= referenciaMs
}

const jogosGruposDoTorneio = computed(() => torneio.value?.jogos.filter((jogo) => jogo.fase.tipo === 'grupos') ?? [])
const jogosEliminatoriosDoCupom = computed(() => bracketCupom.value.filter((jogo) => jogo.fase.tipo !== 'grupos'))

const jogosPorId = computed(() => {
  const mapa = new Map<number, JogoCupom>()
  for (const jogo of jogosGruposDoTorneio.value) mapa.set(jogo.id, jogo)
  for (const jogo of jogosEliminatoriosDoCupom.value) mapa.set(jogo.id, jogo)
  return mapa
})

function jogoFechadoPorId(jogoId: number): boolean {
  const jogo = jogosPorId.value.get(jogoId)
  return jogo ? jogoFechado(jogo) : false
}

const totalJogosGrupos = computed(() => jogosGruposDoTorneio.value.length)
const gruposPreenchidos = computed(() => jogosGruposDoTorneio.value.filter((jogo) => jogoCompleto(jogo)).length)

// Pódio (campeão/vice/3º): palpite do usuário (seletor) e resultado real, para a aba Chaveamento.
const todasSelecoesOrdenadas = computed<Selecao[]>(() =>
  [...todasSelecoes.value].sort((a, b) => a.nome.localeCompare(b.nome)),
)

function nomeSelecaoPorId(id: number | null): string {
  if (!id) return 'A definir'
  return todasSelecoes.value.find((s) => s.id === id)?.nome ?? 'A definir'
}

// O pódio (aposta 'podio') fecha no FIM da fase de grupos: 1h antes do primeiro jogo
// do mata-mata. Espelha ServicoFechamentoApostas. Fallback: 1h antes do início do torneio
// (ex.: bolão só de mata-mata, ou quando o mata-mata ainda não tem data definida).
const prazoPodioMs = computed<number | null>(() => {
  const inicios = (torneio.value?.jogos ?? [])
    .filter((jogo) => jogo.fase.tipo !== 'grupos' && jogo.data_hora_inicio)
    .map((jogo) => new Date(jogo.data_hora_inicio).getTime())
  if (inicios.length) return Math.min(...inicios) - 3600000
  const inicio = torneio.value?.data_inicio
  return inicio ? new Date(inicio).getTime() - 3600000 : null
})

const podioFechado = computed(() => {
  const prazo = prazoPodioMs.value
  if (prazo === null) return false
  return agoraMs.value >= prazo
})

// Contagem regressiva até o fechamento do pódio (null quando não há prazo ou já fechou).
const contagemPodio = computed(() => {
  const prazo = prazoPodioMs.value
  if (prazo === null) return null
  const restanteMs = prazo - agoraMs.value
  if (restanteMs <= 0) return null
  const totalSeg = Math.floor(restanteMs / 1000)
  return {
    dias: Math.floor(totalSeg / 86400),
    horas: Math.floor((totalSeg % 86400) / 3600),
    min: Math.floor((totalSeg % 3600) / 60),
    seg: totalSeg % 60,
    urgente: restanteMs <= 86_400_000, // últimas 24h
  }
})

const textoContagemPodio = computed(() => {
  const c = contagemPodio.value
  if (!c) return ''
  const pad = (n: number) => String(n).padStart(2, '0')
  return c.dias > 0
    ? `${c.dias}d ${pad(c.horas)}h ${pad(c.min)}m`
    : `${pad(c.horas)}h ${pad(c.min)}m ${pad(c.seg)}s`
})

function aoMudarPodio() {
  if (podioFechado.value) return
  agendarAutoSave()
}

// Fases e rodadas disponíveis
const fasesRodadas = computed(() => {
  if (!torneio.value) return []
  const items: { fase: typeof torneio.value.fases[0]; rodada?: typeof torneio.value.jogos[0]['rodada'] }[] = []
  const faseGrupos = torneio.value.fases.find(f => f.tipo === 'grupos')
  if (faseGrupos) {
    const rodadaObjs = jogosGruposDoTorneio.value.filter(j => j.rodada).map(j => j.rodada!).filter((r, i, a) => a.findIndex(x => x.id === r.id) === i).sort((a, b) => a.ordem - b.ordem)
    for (const r of rodadaObjs) {
      items.push({ fase: faseGrupos, rodada: r })
    }
  }
  const fasesEliminatoriasDisponiveis = jogosEliminatoriosDoCupom.value
    .filter((jogo) => !jogo.bloqueado)
    .map((jogo) => jogo.fase)
    .filter((fase, indice, fases) => fases.findIndex((item) => item.id === fase.id) === indice)
    .sort((a, b) => a.ordem - b.ordem)

  for (const fase of fasesEliminatoriasDisponiveis) {
    items.push({ fase })
  }
  return items
})

const faseAtual = computed(() => fasesRodadas.value[indiceFase.value]?.fase)
const rodadaAtual = computed(() => fasesRodadas.value[indiceFase.value]?.rodada)
const tituloFaseAtual = computed(() => rodadaAtual.value ? `${faseAtual.value?.nome ?? 'Fase de Grupos'} - ${rodadaAtual.value.ordem}` : (faseAtual.value?.nome ?? 'Fase de Grupos'))

// Jogos da fase/rodada atual
const jogosFaseAtual = computed(() => {
  if (!torneio.value || !faseAtual.value) return []
  let jogos: JogoCupom[] = faseAtual.value.tipo === 'grupos'
    ? jogosGruposDoTorneio.value.filter(j => j.fase_id === faseAtual.value!.id)
    : jogosEliminatoriosDoCupom.value.filter(j => j.fase_id === faseAtual.value!.id && !j.bloqueado)
  if (rodadaAtual.value && faseAtual.value.tipo === 'grupos') {
    jogos = jogos.filter(j => j.rodada_id === rodadaAtual.value!.id)
  }
  return jogos.sort((a, b) => new Date(a.data_hora_inicio).getTime() - new Date(b.data_hora_inicio).getTime())
})

// Dias com jogos + indicador sem palpite
const diasSemana = ['DOM', 'SEG', 'TER', 'QUA', 'QUI', 'SEX', 'SAB']
const diasComJogos = computed(() => {
  const map = new Map<string, { data: string; diaSemana: string; diaNumero: number; totalJogos: number; semPalpite: boolean }>()
  for (const jogo of jogosFaseAtual.value) {
    const d = new Date(jogo.data_hora_inicio)
    const key = jogo.data_hora_inicio.substring(0, 10)
    if (!map.has(key)) {
      map.set(key, { data: key, diaSemana: diasSemana[d.getDay()], diaNumero: d.getDate(), totalJogos: 0, semPalpite: false })
    }
    const entry = map.get(key)!
    entry.totalJogos++
    if (!jogoCompleto(jogo)) entry.semPalpite = true
  }
  return [...map.values()].sort((a, b) => a.data.localeCompare(b.data))
})

// Jogos do dia selecionado
const jogosDoDia = computed(() => {
  if (!diaSelecionado.value) return jogosFaseAtual.value
  return jogosFaseAtual.value.filter(j => j.data_hora_inicio.startsWith(diaSelecionado.value))
})

// Texto de fechamento do DIA selecionado (grupos: 1h antes do primeiro jogo do dia).
const textoFechamento = computed(() => {
  const doDia = jogosDoDia.value.filter((jogo) => jogo.data_hora_inicio)
  if (!doDia.length) return 'Sem prazo definido'

  const primeiroMs = Math.min(...doDia.map((jogo) => new Date(jogo.data_hora_inicio).getTime()))
  const override = rodadaAtual.value?.data_fechamento

  let referenciaMs: number
  if (override) {
    referenciaMs = new Date(override).getTime()
  } else if (faseAtual.value?.tipo === 'grupos') {
    referenciaMs = primeiroMs - 3600000
  } else {
    referenciaMs = primeiroMs
  }

  const diff = referenciaMs - Date.now()
  if (diff <= 0) return 'Fechado'
  const dias = Math.floor(diff / 86400000)
  if (dias > 30) return `Fecha em ${Math.floor(dias / 30)} meses`
  if (dias > 0) return `Fecha em ${dias} dia${dias > 1 ? 's' : ''}`
  const horas = Math.floor(diff / 3600000)
  if (horas > 0) return `Fecha em ${horas}h`
  const minutos = Math.max(1, Math.floor(diff / 60000))
  return `Fecha em ${minutos}min`
})

const todasSelecoes = computed<Selecao[]>(() => torneio.value?.grupos.flatMap(g => g.selecoes) ?? [])
const podioRanking = computed(() => ranking.value.slice(0, 3))

function classePosicaoRanking(indice: number) {
  if (indice === 0) return 'border-primary/40 bg-primary/10 text-primary'
  if (indice === 1) return 'border-silver/40 bg-silver/10 text-silver'
  if (indice === 2) return 'border-bronze/40 bg-bronze/10 text-bronze'
  return 'border-border bg-bg-card text-text-muted'
}

function iniciaisRanking(nome: string) {
  return nome
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((parte) => parte[0]?.toUpperCase() ?? '')
    .join('')
}

function faseAnterior() { if (indiceFase.value > 0) indiceFase.value-- }
function faseProxima() { if (indiceFase.value < fasesRodadas.value.length - 1) indiceFase.value++ }

// Score starts at '-' (empty). First click on + sets to 0, then 1, 2...
function incrementarPlacar(jogoId: number, lado: 'mandante' | 'visitante') {
  if (jogoIndisponivelPorId(jogoId)) return
  const campo = lado === 'mandante' ? 'placar_mandante' : 'placar_visitante'
  const current = placaresGrupos.value[jogoId][campo]
  if (current === '') {
    placaresGrupos.value[jogoId][campo] = '0'
  } else {
    placaresGrupos.value[jogoId][campo] = String(Number(current) + 1)
  }
  agendarAutoSave()
}

function decrementarPlacar(jogoId: number, lado: 'mandante' | 'visitante') {
  if (jogoIndisponivelPorId(jogoId)) return
  const campo = lado === 'mandante' ? 'placar_mandante' : 'placar_visitante'
  const current = placaresGrupos.value[jogoId][campo]
  if (current === '' || current === '0') {
    placaresGrupos.value[jogoId][campo] = ''
  } else {
    placaresGrupos.value[jogoId][campo] = String(Number(current) - 1)
  }
  agendarAutoSave()
}

function incrementarPenal(jogoId: number, lado: 'mandante' | 'visitante') {
  if (jogoIndisponivelPorId(jogoId)) return
  const campo = lado === 'mandante' ? 'penal_mandante' : 'penal_visitante'
  const current = placaresEliminatorios.value[jogoId][campo]
  placaresEliminatorios.value[jogoId][campo] = current === '' ? '0' : String(Number(current) + 1)
  agendarAutoSave()
}

function decrementarPenal(jogoId: number, lado: 'mandante' | 'visitante') {
  if (jogoIndisponivelPorId(jogoId)) return
  const campo = lado === 'mandante' ? 'penal_mandante' : 'penal_visitante'
  const current = placaresEliminatorios.value[jogoId][campo]
  if (current === '' || current === '0') {
    placaresEliminatorios.value[jogoId][campo] = ''
  } else {
    placaresEliminatorios.value[jogoId][campo] = String(Number(current) - 1)
  }
  agendarAutoSave()
}

// Auto-save debounce 2s
function agendarAutoSave() {
  if (autoSaveTimer) clearTimeout(autoSaveTimer)
  if (salvando.value) {
    salvarNovamente = true
  }
  autoSaveTimer = setTimeout(() => {
    void autoSalvar()
  }, 1200)
}

function montarApostasParaEnvio() {
  if (!torneio.value) return
  const apostasArr: Record<string, unknown>[] = []
  const jogosDoCupom: JogoCupom[] = [...jogosGruposDoTorneio.value, ...jogosEliminatoriosDoCupom.value]

  // All games with both scores filled
  for (const jogo of jogosDoCupom) {
    // Jogos ja fechados nao entram no lote: o palpite deles e imutavel e reenvia-los
    // faria o backend recusar o lote inteiro, derrubando os jogos ainda abertos.
    if (jogoFechado(jogo)) continue
    // Mata-mata sem confronto definido: o backend ignoraria; nao enviar para nao confundir.
    if (jogoSemConfronto(jogo)) continue
    const p = placaresGrupos.value[jogo.id]
    if (!p || p.placar_mandante === '' || p.placar_visitante === '') continue
    if (jogo.fase.tipo === 'grupos') {
      apostasArr.push({ tipo: 'placar_jogo_grupos', jogo_id: jogo.id, placar_mandante: Number(p.placar_mandante), placar_visitante: Number(p.placar_visitante) })
    } else {
      const e = placaresEliminatorios.value[jogo.id]
      if (!e) continue
      const payload: Record<string, unknown> = {
        tipo: 'placar_jogo_eliminatoria',
        jogo_id: jogo.id,
        placar_mandante: Number(p.placar_mandante),
        placar_visitante: Number(p.placar_visitante),
      }
      if (precisaPenaltis(jogo.id)) {
        if (e.penal_mandante === '' || e.penal_visitante === '' || e.penal_mandante === e.penal_visitante) continue
        payload.penal_mandante = Number(e.penal_mandante)
        payload.penal_visitante = Number(e.penal_visitante)
      }
      apostasArr.push(payload)
    }
  }

  // Palpite de pódio (campeão/vice/3º): só envia se completo, distinto e ainda aberto.
  const pod = palpitePodio.value
  if (!podioFechado.value && pod.campeao && pod.vice && pod.terceiro
    && pod.campeao !== pod.vice && pod.campeao !== pod.terceiro && pod.vice !== pod.terceiro) {
    apostasArr.push({
      tipo: 'podio',
      torneio_id: torneio.value.id,
      campeao_selecao_id: pod.campeao,
      vice_selecao_id: pod.vice,
      terceiro_selecao_id: pod.terceiro,
    })
  }

  return apostasArr
}

// Jogos que tinham palpite salvo e foram esvaziados ("sem palpite"): precisam ser
// removidos no backend, senao o palpite antigo continua gravado.
function montarRemocoes(): number[] {
  if (!torneio.value) return []
  const remover: number[] = []
  const jogosDoCupom: JogoCupom[] = [...jogosGruposDoTorneio.value, ...jogosEliminatoriosDoCupom.value]

  for (const jogo of jogosDoCupom) {
    if (jogoFechado(jogo)) continue
    const tipo = jogo.fase.tipo === 'grupos' ? 'placar_jogo_grupos' : 'placar_jogo_eliminatoria'
    if (!encontrarAposta(tipo, jogo.id)) continue
    const p = placaresGrupos.value[jogo.id]
    const vazio = !p || (p.placar_mandante === '' && p.placar_visitante === '')
    if (vazio) remover.push(jogo.id)
  }

  return remover
}

async function recarregarEstadoDerivado() {
  const [rC, rA, rB] = await Promise.all([
    requisicaoApi<{ cupom: Cupom }>(`/cupons/${rota.params.id}`),
    requisicaoApi<{ apostas: Aposta[] }>(`/cupons/${rota.params.id}/apostas`),
    requisicaoApi<{ bracket: BracketJogoCupom[]; resumo: ResumoBracketReal }>(`/cupons/${rota.params.id}/bracket`),
  ])

  cupom.value = rC.cupom
  apostas.value = rA.apostas
  bracketCupom.value = rB.bracket
  resumoBracketIds.value = rB.resumo
  preencherFormulario('mesclar')
}

async function autoSalvar() {
  if (!torneio.value) return
  if (salvando.value) {
    salvarNovamente = true
    return
  }

  const apostasArr = montarApostasParaEnvio()
  const remocoes = montarRemocoes()
  if (!apostasArr?.length && !remocoes.length) return

  salvando.value = true
  ultimoSalvo.value = false
  salvarNovamente = false
  try {
    if (apostasArr?.length) {
      await requisicaoApi(`/cupons/${rota.params.id}/apostas/lote`, { metodo: 'POST', corpo: { apostas: apostasArr } })
    }
    if (remocoes.length) {
      await requisicaoApi(`/cupons/${rota.params.id}/apostas/remover`, { metodo: 'POST', corpo: { jogos: remocoes } })
    }
    await recarregarEstadoDerivado()
    ultimoSalvo.value = true
    setTimeout(() => { ultimoSalvo.value = false }, 3000)
  } catch (error) {
    mostrar('erro', error instanceof Error ? error.message : 'Falha ao salvar automaticamente.')
  } finally {
    salvando.value = false
    if (salvarNovamente) {
      salvarNovamente = false
      if (autoSaveTimer) clearTimeout(autoSaveTimer)
      autoSaveTimer = setTimeout(() => {
        void autoSalvar()
      }, 250)
    }
  }
}

function formatarHora(dataHora: string) {
  return new Date(dataHora).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })
}

// Jogo que originou um evento de pontuacao (vazio para artilheiro/podio).
function encontrarAposta(tipo: string, referenciaId?: number) {
  return apostas.value.find(a => {
    if (a.tipo !== tipo) return false
    return a.jogo_id === referenciaId
  })
}

function preencherFormulario(modo: 'substituir' | 'mesclar' = 'substituir') {
  if (!torneio.value) return
  const sobrescrever = modo === 'substituir'
  const jogosDoCupom: JogoCupom[] = [...jogosGruposDoTorneio.value, ...jogosEliminatoriosDoCupom.value]
  for (const jogo of jogosDoCupom) {
    const tipo = jogo.fase.tipo === 'grupos' ? 'placar_jogo_grupos' : 'placar_jogo_eliminatoria'
    const aposta = encontrarAposta(tipo, jogo.id)
    // Palpite obsoleto (confronto antigo) nao pre-preenche: o usuario palpita o confronto real.
    const fonte = apostaEliminatoriaObsoleta(jogo, aposta) ? undefined : aposta
    if (sobrescrever || !placaresGrupos.value[jogo.id]) {
      placaresGrupos.value[jogo.id] = {
        placar_mandante: String(fonte?.conteudo.placar_mandante ?? ''),
        placar_visitante: String(fonte?.conteudo.placar_visitante ?? ''),
      }
    }
    if (jogo.fase.tipo !== 'grupos' && (sobrescrever || !placaresEliminatorios.value[jogo.id])) {
      placaresEliminatorios.value[jogo.id] = {
        placar_mandante: String(fonte?.conteudo.placar_mandante ?? ''),
        placar_visitante: String(fonte?.conteudo.placar_visitante ?? ''),
        penal_mandante: String(fonte?.conteudo.penal_mandante ?? ''),
        penal_visitante: String(fonte?.conteudo.penal_visitante ?? ''),
      }
    }
  }

  const apostaPodio = apostas.value.find((a) => a.tipo === 'podio')
  if (sobrescrever || (palpitePodio.value.campeao === null && palpitePodio.value.vice === null && palpitePodio.value.terceiro === null)) {
    const c = (apostaPodio?.conteudo ?? {}) as Record<string, number | null>
    palpitePodio.value = {
      campeao: c.campeao_selecao_id ?? null,
      vice: c.vice_selecao_id ?? null,
      terceiro: c.terceiro_selecao_id ?? null,
    }
  }
}

async function carregarDados() {
  carregando.value = true
  try {
    const rC = await requisicaoApi<{ cupom: Cupom }>(`/cupons/${rota.params.id}`)
    cupom.value = rC.cupom

    const caminhoTorneio = rC.cupom.torneio_id ? `/torneios/${rC.cupom.torneio_id}` : '/torneio'
    const [rT, rA, rB] = await Promise.all([
      requisicaoApi<{ torneio: Torneio }>(caminhoTorneio),
      requisicaoApi<{ apostas: Aposta[] }>(`/cupons/${rota.params.id}/apostas`),
      requisicaoApi<{ bracket: BracketJogoCupom[]; resumo: ResumoBracketReal }>(`/cupons/${rota.params.id}/bracket`),
    ])
    torneio.value = rT.torneio
    apostas.value = rA.apostas
    bracketCupom.value = rB.bracket
    resumoBracketIds.value = rB.resumo
    preencherFormulario('substituir')
  } catch {
    mostrar('erro', 'Falha ao carregar dados do cupom.')
  } finally {
    carregando.value = false
  }
}


async function carregarRanking() {
  if (!torneio.value) return
  carregandoRanking.value = true
  try {
    const r = await requisicaoApi<{ ranking: RankingItem[] }>(`/torneios/${torneio.value.id}/ranking`)
    ranking.value = r.ranking
  } catch {} finally { carregandoRanking.value = false }
}

watch([indiceFase, diasComJogos], () => {
  if (!diasComJogos.value.length) {
    diaSelecionado.value = ''
    return
  }

  const diaAindaExiste = diasComJogos.value.some((dia) => dia.data === diaSelecionado.value)
  if (!diaAindaExiste) {
    diaSelecionado.value = diasComJogos.value[0].data
  }
}, { immediate: true })

watch(fasesRodadas, (items) => {
  if (!items.length) {
    indiceFase.value = 0
    return
  }
  if (indiceFase.value > items.length - 1) {
    indiceFase.value = items.length - 1
  }
})

// Abre na fase/dia do dia atual (ou o proximo dia com jogos; senao o ultimo).
function definirDiaInicial(): void {
  if (!fasesRodadas.value.length) return
  const jogos = [...jogosGruposDoTorneio.value, ...jogosEliminatoriosDoCupom.value]
    .filter((jogo) => jogo.data_hora_inicio)
    .sort((a, b) => a.data_hora_inicio.localeCompare(b.data_hora_inicio))
  if (!jogos.length) return

  const hoje = new Date().toISOString().substring(0, 10)
  const alvo = jogos.find((jogo) => jogo.data_hora_inicio.substring(0, 10) === hoje)
    ?? jogos.find((jogo) => jogo.data_hora_inicio.substring(0, 10) >= hoje)
    ?? jogos[jogos.length - 1]

  const idx = fasesRodadas.value.findIndex((item) =>
    item.fase.tipo === 'grupos'
      ? (item.rodada?.id ?? null) === alvo.rodada_id
      : item.fase.id === alvo.fase_id,
  )
  if (idx >= 0) indiceFase.value = idx
  diaSelecionado.value = alvo.data_hora_inicio.substring(0, 10)
}

onMounted(async () => {
  await carregarDados()
  definirDiaInicial()
  carregarRanking()
})
</script>

