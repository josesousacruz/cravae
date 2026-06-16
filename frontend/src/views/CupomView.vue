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
                    <span v-if="jogoCompleto(jogo)" class="inline-flex items-center gap-1 rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-medium text-primary">
                      <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                      Com palpite
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
                        class="inline-flex items-center gap-1 rounded-full border border-primary/30 bg-primary/5 px-2.5 py-1 text-[10px] font-medium text-primary transition hover:bg-primary/10"
                        @click="togglePalpiteiros(jogo.id)"
                      >
                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" /></svg>
                        Quem palpitou
                      </button>
                      <!-- Popover -->
                      <div
                        v-if="palpiteirosAberto === jogo.id"
                        class="absolute right-0 top-full z-20 mt-1 w-56 max-w-[calc(100vw-2rem)] rounded-xl border border-border bg-bg-card p-3 shadow-xl"
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
                        <div v-else class="max-h-32 space-y-1 overflow-y-auto">
                          <div
                            v-for="(p, pi) in palpiteirosCache[jogo.id].palpiteiros"
                            :key="pi"
                            class="flex items-center gap-2 rounded-lg px-2 py-1 hover:bg-bg-input"
                          >
                            <div class="flex h-5 w-5 items-center justify-center rounded-full bg-primary/20 text-[9px] font-bold text-primary">
                              {{ p.nome.charAt(0).toUpperCase() }}
                            </div>
                            <span class="text-xs text-text-secondary truncate">{{ p.nome }}</span>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Teams + score -->
                <div class="grid min-w-0 grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-x-2 gap-y-4 sm:flex sm:items-center sm:gap-6">
                  <!-- Home team -->
                  <div class="min-w-0 text-center">
                    <img
                      :src="bandeira(jogo.selecao_mandante?.sigla ?? '')"
                      :alt="jogo.selecao_mandante?.nome ?? 'A definir'"
                      class="mx-auto h-10 w-14 rounded object-cover shadow"
                      @error="($event.target as HTMLImageElement).style.display='none'"
                    />
                    <p class="mt-2 break-words text-xs font-medium sm:text-sm">{{ jogo.selecao_mandante?.nome ?? 'A definir' }}</p>
                  </div>

                  <!-- Score inputs with +/- buttons -->
                  <div class="flex items-center justify-center gap-1 sm:gap-2">
                    <div class="flex items-center gap-0.5">
                      <button type="button" :disabled="jogoFechado(jogo)" class="flex h-7 w-7 items-center justify-center rounded-lg bg-bg-input text-text-muted transition hover:bg-primary/20 hover:text-primary disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-bg-input disabled:hover:text-text-muted" @click="decrementarPlacar(jogo.id, 'mandante')">-</button>
                      <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-bg-input text-base font-bold" :class="placaresGrupos[jogo.id]?.placar_mandante !== '' ? 'text-text' : 'text-text-muted'">
                        {{ placaresGrupos[jogo.id]?.placar_mandante !== '' ? placaresGrupos[jogo.id]?.placar_mandante : '-' }}
                      </div>
                      <button type="button" :disabled="jogoFechado(jogo)" class="flex h-7 w-7 items-center justify-center rounded-lg bg-bg-input text-text-muted transition hover:bg-primary/20 hover:text-primary disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-bg-input disabled:hover:text-text-muted" @click="incrementarPlacar(jogo.id, 'mandante')">+</button>
                    </div>

                    <span class="text-xs text-text-muted font-medium">x</span>

                    <div class="flex items-center gap-0.5">
                      <button type="button" :disabled="jogoFechado(jogo)" class="flex h-7 w-7 items-center justify-center rounded-lg bg-bg-input text-text-muted transition hover:bg-primary/20 hover:text-primary disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-bg-input disabled:hover:text-text-muted" @click="decrementarPlacar(jogo.id, 'visitante')">-</button>
                      <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-bg-input text-base font-bold" :class="placaresGrupos[jogo.id]?.placar_visitante !== '' ? 'text-text' : 'text-text-muted'">
                        {{ placaresGrupos[jogo.id]?.placar_visitante !== '' ? placaresGrupos[jogo.id]?.placar_visitante : '-' }}
                      </div>
                      <button type="button" :disabled="jogoFechado(jogo)" class="flex h-7 w-7 items-center justify-center rounded-lg bg-bg-input text-text-muted transition hover:bg-primary/20 hover:text-primary disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-bg-input disabled:hover:text-text-muted" @click="incrementarPlacar(jogo.id, 'visitante')">+</button>
                    </div>
                  </div>

                  <!-- Away team -->
                  <div class="min-w-0 text-center">
                    <img
                      :src="bandeira(jogo.selecao_visitante?.sigla ?? '')"
                      :alt="jogo.selecao_visitante?.nome ?? 'A definir'"
                      class="mx-auto h-10 w-14 rounded object-cover shadow"
                      @error="($event.target as HTMLImageElement).style.display='none'"
                    />
                    <p class="mt-2 break-words text-xs font-medium sm:text-sm">{{ jogo.selecao_visitante?.nome ?? 'A definir' }}</p>
                  </div>
                </div>

                <!-- Knockout: penalties on draw -->
                <div v-if="jogo.fase.tipo !== 'grupos' && placaresEliminatorios[jogo.id]" class="mt-4">
                  <div v-if="precisaPenaltis(jogo.id)" class="rounded-xl border border-primary/20 bg-primary/5 p-3">
                    <span class="mb-2 block text-xs font-medium text-primary">Empate: informe os penaltis</span>
                    <div class="flex items-center justify-center gap-2">
                      <div class="flex items-center gap-0.5">
                        <button type="button" :disabled="jogoFechado(jogo)" class="flex h-7 w-7 items-center justify-center rounded-lg bg-bg-input text-text-muted transition hover:bg-primary/20 hover:text-primary disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-bg-input disabled:hover:text-text-muted" @click="decrementarPenal(jogo.id, 'mandante')">-</button>
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-bg-input text-base font-bold" :class="placaresEliminatorios[jogo.id].penal_mandante !== '' ? 'text-text' : 'text-text-muted'">
                          {{ placaresEliminatorios[jogo.id].penal_mandante !== '' ? placaresEliminatorios[jogo.id].penal_mandante : '-' }}
                        </div>
                        <button type="button" :disabled="jogoFechado(jogo)" class="flex h-7 w-7 items-center justify-center rounded-lg bg-bg-input text-text-muted transition hover:bg-primary/20 hover:text-primary disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-bg-input disabled:hover:text-text-muted" @click="incrementarPenal(jogo.id, 'mandante')">+</button>
                      </div>
                      <span class="text-xs text-text-muted font-medium">x</span>
                      <div class="flex items-center gap-0.5">
                        <button type="button" :disabled="jogoFechado(jogo)" class="flex h-7 w-7 items-center justify-center rounded-lg bg-bg-input text-text-muted transition hover:bg-primary/20 hover:text-primary disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-bg-input disabled:hover:text-text-muted" @click="decrementarPenal(jogo.id, 'visitante')">-</button>
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-bg-input text-base font-bold" :class="placaresEliminatorios[jogo.id].penal_visitante !== '' ? 'text-text' : 'text-text-muted'">
                          {{ placaresEliminatorios[jogo.id].penal_visitante !== '' ? placaresEliminatorios[jogo.id].penal_visitante : '-' }}
                        </div>
                        <button type="button" :disabled="jogoFechado(jogo)" class="flex h-7 w-7 items-center justify-center rounded-lg bg-bg-input text-text-muted transition hover:bg-primary/20 hover:text-primary disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-bg-input disabled:hover:text-text-muted" @click="incrementarPenal(jogo.id, 'visitante')">+</button>
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
                    <div class="grid min-w-[1280px] grid-cols-[minmax(172px,1.05fr)_minmax(104px,.64fr)_minmax(104px,.64fr)_minmax(104px,.64fr)_minmax(104px,.64fr)_minmax(112px,.68fr)_minmax(104px,.64fr)_minmax(104px,.64fr)_minmax(104px,.64fr)_minmax(104px,.64fr)_minmax(172px,1.05fr)] items-center gap-2 2xl:min-w-0 2xl:w-full">
                      <aside class="flex min-h-[920px] flex-col justify-around gap-3">
                        <article
                          v-for="grupo in gruposChaveamentoEsquerda"
                          :key="grupo.letra"
                          class="rounded-2xl border border-white/15 bg-white/10 p-3 text-white shadow-inner shadow-white/5 backdrop-blur"
                        >
                          <div class="mb-2 flex items-center justify-between">
                            <strong class="flex h-7 w-7 items-center justify-center rounded-lg border border-[#d4af37]/60 bg-[#d4af37]/20 text-xs text-[#f8e7a1]">
                              {{ grupo.letra }}
                            </strong>
                            <span class="text-[9px] font-bold uppercase tracking-[0.18em] text-white/55">Grupo {{ grupo.letra }}</span>
                          </div>
                          <div class="space-y-1.5">
                            <div
                              v-for="linha in grupo.tabela"
                              :key="linha.selecao.id"
                              class="grid grid-cols-[24px_1fr_30px] items-center gap-1.5 rounded-lg border px-2 py-1.5"
                              :class="linha.posicao <= 2 ? 'border-[#d4af37]/35 bg-[#d4af37]/15' : linha.qualificadoTerceiro ? 'border-emerald-300/30 bg-emerald-300/10' : 'border-white/10 bg-black/10'"
                            >
                              <span class="text-[10px] font-black text-[#f8e7a1]">{{ linha.posicao }}o</span>
                              <span class="min-w-0 truncate text-[11px] font-semibold">{{ linha.selecao.nome }}</span>
                              <span class="rounded-md bg-black/20 px-1 py-0.5 text-center text-[9px] font-bold text-white/80">{{ linha.pontos }}</span>
                            </div>
                          </div>
                        </article>
                      </aside>

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

                      <aside class="flex min-h-[920px] flex-col justify-around gap-3">
                        <article
                          v-for="grupo in gruposChaveamentoDireita"
                          :key="grupo.letra"
                          class="rounded-2xl border border-white/15 bg-white/10 p-3 text-white shadow-inner shadow-white/5 backdrop-blur"
                        >
                          <div class="mb-2 flex items-center justify-between">
                            <span class="text-[9px] font-bold uppercase tracking-[0.18em] text-white/55">Grupo {{ grupo.letra }}</span>
                            <strong class="flex h-7 w-7 items-center justify-center rounded-lg border border-[#d4af37]/60 bg-[#d4af37]/20 text-xs text-[#f8e7a1]">
                              {{ grupo.letra }}
                            </strong>
                          </div>
                          <div class="space-y-1.5">
                            <div
                              v-for="linha in grupo.tabela"
                              :key="linha.selecao.id"
                              class="grid grid-cols-[30px_1fr_24px] items-center gap-1.5 rounded-lg border px-2 py-1.5"
                              :class="linha.posicao <= 2 ? 'border-[#d4af37]/35 bg-[#d4af37]/15' : linha.qualificadoTerceiro ? 'border-emerald-300/30 bg-emerald-300/10' : 'border-white/10 bg-black/10'"
                            >
                              <span class="rounded-md bg-black/20 px-1 py-0.5 text-center text-[9px] font-bold text-white/80">{{ linha.pontos }}</span>
                              <span class="min-w-0 truncate text-right text-[11px] font-semibold">{{ linha.selecao.nome }}</span>
                              <span class="text-right text-[10px] font-black text-[#f8e7a1]">{{ linha.posicao }}o</span>
                            </div>
                          </div>
                        </article>
                      </aside>
                    </div>
                  </div>
                </div>
              </section>

              <div class="grid gap-3 sm:grid-cols-3">
                <div class="rounded-2xl border border-border bg-bg-card p-4">
                  <span class="block text-[10px] uppercase text-text-muted">Campeao</span>
                  <span class="mt-1 block text-sm font-medium">{{ resumoBracket.campeao ?? 'A definir' }}</span>
                </div>
                <div class="rounded-2xl border border-border bg-bg-card p-4">
                  <span class="block text-[10px] uppercase text-text-muted">Vice</span>
                  <span class="mt-1 block text-sm font-medium">{{ resumoBracket.vice ?? 'A definir' }}</span>
                </div>
                <div class="rounded-2xl border border-border bg-bg-card p-4">
                  <span class="block text-[10px] uppercase text-text-muted">Terceiro</span>
                  <span class="mt-1 block text-sm font-medium">{{ resumoBracket.terceiro ?? 'A definir' }}</span>
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
import { computed, defineComponent, h, onMounted, ref, watch, type PropType } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { requisicaoApi } from '../services/api'
import { usarTorneioStore } from '../stores/torneio'
import { useToast } from '../composables/useToast'
import type { Aposta, BracketJogoCupom, Cupom, RankingItem, ResumoBracketCupom, Selecao, Torneio } from '../tipos'
import tacaCopaAsset from '../assets/taca-copa-transparente.png'
import ModalPixPagamento from '../components/ModalPixPagamento.vue'
import RankingConteudo from '../components/RankingConteudo.vue'
import { useEventosCupom } from '../composables/useEventosCupom'

const rota = useRoute()
const torneioStore = usarTorneioStore()
const { mostrar } = useToast()
const { descricaoJogo } = useEventosCupom()

const torneio = ref<Torneio | null>(null)
const cupom = ref<Cupom | null>(null)
const apostas = ref<Aposta[]>([])
const bracketCupom = ref<BracketJogoCupom[]>([])
const resumoBracketIds = ref<ResumoBracketCupom>({
  campeao_selecao_id: null,
  vice_campeao_selecao_id: null,
  terceiro_colocado_selecao_id: null,
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
const palpiteirosCache = ref<Record<number, { total: number; palpiteiros: { nome: string; cupom_codigo: string }[] }>>({})

async function togglePalpiteiros(jogoId: number) {
  if (palpiteirosAberto.value === jogoId) {
    palpiteirosAberto.value = null
    return
  }
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
type LinhaClassificacaoGrupo = {
  grupo: string
  posicao: number
  selecao: Selecao
  pontos: number
  saldo: number
  golsPro: number
  vitorias: number
  qualificadoTerceiro: boolean
}
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

function letraGrupo(nome: string): string {
  return nome.replace(/^Grupo\s+/i, '')
}

function ordenarClassificacao(a: LinhaClassificacaoGrupo, b: LinhaClassificacaoGrupo): number {
  return b.pontos - a.pontos
    || b.saldo - a.saldo
    || b.golsPro - a.golsPro
    || b.vitorias - a.vitorias
    || a.selecao.nome.localeCompare(b.selecao.nome)
}

function placarChaveamento(jogo: BracketJogoCupom, lado: 'mandante' | 'visitante'): string {
  const placar = placaresGrupos.value[jogo.id]
  const campo = lado === 'mandante' ? 'placar_mandante' : 'placar_visitante'
  return placar?.[campo] !== '' && placar?.[campo] !== undefined ? placar[campo] : '-'
}

function vencedorChaveamentoId(jogo: BracketJogoCupom): number | null {
  const placar = placaresGrupos.value[jogo.id]
  if (!placar || placar.placar_mandante === '' || placar.placar_visitante === '') return null

  const mandante = jogo.selecao_mandante?.id ?? null
  const visitante = jogo.selecao_visitante?.id ?? null
  const golsMandante = Number(placar.placar_mandante)
  const golsVisitante = Number(placar.placar_visitante)

  if (golsMandante > golsVisitante) return mandante
  if (golsVisitante > golsMandante) return visitante

  const penaltis = placaresEliminatorios.value[jogo.id]
  if (!penaltis || penaltis.penal_mandante === '' || penaltis.penal_visitante === '') return null

  const penalMandante = Number(penaltis.penal_mandante)
  const penalVisitante = Number(penaltis.penal_visitante)
  if (penalMandante > penalVisitante) return mandante
  if (penalVisitante > penalMandante) return visitante
  return null
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

// Espelha a regra de fechamento do backend (ServicoFechamentoApostas):
// grupos fecham na data_fechamento da rodada (ou 1h antes do jogo); mata-mata
// fecha no inicio do proprio jogo. Jogo fechado nao pode ter o palpite alterado.
function jogoFechado(jogo: JogoCupom): boolean {
  const referencia = jogo.fase.tipo === 'grupos'
    ? (jogo.rodada?.data_fechamento ?? new Date(new Date(jogo.data_hora_inicio).getTime() - 3600000).toISOString())
    : jogo.data_hora_inicio
  if (!referencia) return false
  return Date.now() >= new Date(referencia).getTime()
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

const classificacaoGrupos = computed(() => {
  if (!torneio.value) return []

  const grupos = torneio.value.grupos.map((grupo) => {
    const letra = letraGrupo(grupo.nome)
    const tabela = new Map<number, LinhaClassificacaoGrupo>()

    for (const selecao of grupo.selecoes) {
      tabela.set(selecao.id, {
        grupo: letra,
        posicao: 0,
        selecao,
        pontos: 0,
        saldo: 0,
        golsPro: 0,
        vitorias: 0,
        qualificadoTerceiro: false,
      })
    }

    for (const jogo of jogosGruposDoTorneio.value.filter((item) => item.grupo_id === grupo.id)) {
      const palpite = placaresGrupos.value[jogo.id]
      if (!palpite || palpite.placar_mandante === '' || palpite.placar_visitante === '' || !jogo.selecao_mandante || !jogo.selecao_visitante) continue

      const mandante = tabela.get(jogo.selecao_mandante.id)
      const visitante = tabela.get(jogo.selecao_visitante.id)
      if (!mandante || !visitante) continue

      const golsMandante = Number(palpite.placar_mandante)
      const golsVisitante = Number(palpite.placar_visitante)
      mandante.golsPro += golsMandante
      visitante.golsPro += golsVisitante
      mandante.saldo += golsMandante - golsVisitante
      visitante.saldo += golsVisitante - golsMandante

      if (golsMandante > golsVisitante) {
        mandante.pontos += 3
        mandante.vitorias += 1
      } else if (golsVisitante > golsMandante) {
        visitante.pontos += 3
        visitante.vitorias += 1
      } else {
        mandante.pontos += 1
        visitante.pontos += 1
      }
    }

    const linhas = [...tabela.values()]
      .sort(ordenarClassificacao)
      .map((linha, indice) => ({ ...linha, posicao: indice + 1 }))

    return { letra, tabela: linhas }
  })

  const terceiros = grupos
    .map((grupo) => grupo.tabela[2])
    .filter((linha): linha is LinhaClassificacaoGrupo => Boolean(linha))
    .sort(ordenarClassificacao)
    .slice(0, 8)
    .map((linha) => `${linha.grupo}-${linha.selecao.id}`)

  return grupos.map((grupo) => ({
    ...grupo,
    tabela: grupo.tabela.map((linha) => ({
      ...linha,
      qualificadoTerceiro: terceiros.includes(`${linha.grupo}-${linha.selecao.id}`),
    })),
  }))
})
const gruposChaveamentoEsquerda = computed(() => classificacaoGrupos.value.slice(0, Math.ceil(classificacaoGrupos.value.length / 2)))
const gruposChaveamentoDireita = computed(() => classificacaoGrupos.value.slice(Math.ceil(classificacaoGrupos.value.length / 2)))

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

// Texto de fechamento
const textoFechamento = computed(() => {
  const referencia = rodadaAtual.value?.data_fechamento ?? jogosFaseAtual.value[0]?.data_hora_inicio ?? faseAtual.value?.data_fechamento
  if (!referencia) return 'Sem prazo definido'
  const diff = new Date(referencia).getTime() - Date.now()
  if (diff <= 0) return 'Fechado'
  const dias = Math.floor(diff / 86400000)
  if (dias > 30) return `Fecha em ${Math.floor(dias / 30)} meses`
  if (dias > 0) return `Fecha em ${dias} dia${dias > 1 ? 's' : ''}`
  const horas = Math.floor(diff / 3600000)
  return `Fecha em ${horas}h`
})

const todasSelecoes = computed<Selecao[]>(() => torneio.value?.grupos.flatMap(g => g.selecoes) ?? [])
const resumoBracket = computed(() => {
  if (!torneio.value) return { campeao: null, vice: null, terceiro: null }

  const nomeSelecao = (id: number | null | undefined) => todasSelecoes.value.find((selecao) => selecao.id === id)?.nome ?? null

  return {
    campeao: nomeSelecao(resumoBracketIds.value.campeao_selecao_id),
    vice: nomeSelecao(resumoBracketIds.value.vice_campeao_selecao_id),
    terceiro: nomeSelecao(resumoBracketIds.value.terceiro_colocado_selecao_id),
  }
})
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
  if (jogoFechadoPorId(jogoId)) return
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
  if (jogoFechadoPorId(jogoId)) return
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
  if (jogoFechadoPorId(jogoId)) return
  const campo = lado === 'mandante' ? 'penal_mandante' : 'penal_visitante'
  const current = placaresEliminatorios.value[jogoId][campo]
  placaresEliminatorios.value[jogoId][campo] = current === '' ? '0' : String(Number(current) + 1)
  agendarAutoSave()
}

function decrementarPenal(jogoId: number, lado: 'mandante' | 'visitante') {
  if (jogoFechadoPorId(jogoId)) return
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

  return apostasArr
}

async function recarregarEstadoDerivado() {
  const [rC, rA, rB] = await Promise.all([
    requisicaoApi<{ cupom: Cupom }>(`/cupons/${rota.params.id}`),
    requisicaoApi<{ apostas: Aposta[] }>(`/cupons/${rota.params.id}/apostas`),
    requisicaoApi<{ bracket: BracketJogoCupom[]; resumo: ResumoBracketCupom }>(`/cupons/${rota.params.id}/bracket`),
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
  if (!apostasArr?.length) return

  salvando.value = true
  ultimoSalvo.value = false
  salvarNovamente = false
  try {
    await requisicaoApi(`/cupons/${rota.params.id}/apostas/lote`, { metodo: 'POST', corpo: { apostas: apostasArr } })
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
    if (sobrescrever || !placaresGrupos.value[jogo.id]) {
      placaresGrupos.value[jogo.id] = {
        placar_mandante: String(aposta?.conteudo.placar_mandante ?? ''),
        placar_visitante: String(aposta?.conteudo.placar_visitante ?? ''),
      }
    }
    if (jogo.fase.tipo !== 'grupos' && (sobrescrever || !placaresEliminatorios.value[jogo.id])) {
      placaresEliminatorios.value[jogo.id] = {
        placar_mandante: String(aposta?.conteudo.placar_mandante ?? ''),
        placar_visitante: String(aposta?.conteudo.placar_visitante ?? ''),
        penal_mandante: String(aposta?.conteudo.penal_mandante ?? ''),
        penal_visitante: String(aposta?.conteudo.penal_visitante ?? ''),
      }
    }
  }
}

async function carregarDados() {
  carregando.value = true
  try {
    const [rT, rC, rA, rB] = await Promise.all([
      requisicaoApi<{ torneio: Torneio }>('/torneio'),
      requisicaoApi<{ cupom: Cupom }>(`/cupons/${rota.params.id}`),
      requisicaoApi<{ apostas: Aposta[] }>(`/cupons/${rota.params.id}/apostas`),
      requisicaoApi<{ bracket: BracketJogoCupom[]; resumo: ResumoBracketCupom }>(`/cupons/${rota.params.id}/bracket`),
    ])
    torneio.value = rT.torneio
    cupom.value = rC.cupom
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
  if (!torneioStore.torneio) return
  carregandoRanking.value = true
  try {
    const r = await requisicaoApi<{ ranking: RankingItem[] }>(`/torneios/${torneioStore.torneio.id}/ranking`)
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

onMounted(async () => {
  await Promise.all([carregarDados(), torneioStore.carregar()])
  carregarRanking()
})
</script>

