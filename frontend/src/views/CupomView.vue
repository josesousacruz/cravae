<template>
  <div class="mx-auto max-w-6xl">
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
          {{ cupom.status }}
        </span>
      </div>

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
        <div class="min-w-0 gap-6 lg:grid lg:grid-cols-[minmax(0,1fr)_300px]">
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
                      <button type="button" class="flex h-7 w-7 items-center justify-center rounded-lg bg-bg-input text-text-muted transition hover:bg-primary/20 hover:text-primary" @click="decrementarPlacar(jogo.id, 'mandante')">-</button>
                      <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-bg-input text-base font-bold" :class="placaresGrupos[jogo.id]?.placar_mandante !== '' ? 'text-text' : 'text-text-muted'">
                        {{ placaresGrupos[jogo.id]?.placar_mandante !== '' ? placaresGrupos[jogo.id]?.placar_mandante : '-' }}
                      </div>
                      <button type="button" class="flex h-7 w-7 items-center justify-center rounded-lg bg-bg-input text-text-muted transition hover:bg-primary/20 hover:text-primary" @click="incrementarPlacar(jogo.id, 'mandante')">+</button>
                    </div>

                    <span class="text-xs text-text-muted font-medium">x</span>

                    <div class="flex items-center gap-0.5">
                      <button type="button" class="flex h-7 w-7 items-center justify-center rounded-lg bg-bg-input text-text-muted transition hover:bg-primary/20 hover:text-primary" @click="decrementarPlacar(jogo.id, 'visitante')">-</button>
                      <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-bg-input text-base font-bold" :class="placaresGrupos[jogo.id]?.placar_visitante !== '' ? 'text-text' : 'text-text-muted'">
                        {{ placaresGrupos[jogo.id]?.placar_visitante !== '' ? placaresGrupos[jogo.id]?.placar_visitante : '-' }}
                      </div>
                      <button type="button" class="flex h-7 w-7 items-center justify-center rounded-lg bg-bg-input text-text-muted transition hover:bg-primary/20 hover:text-primary" @click="incrementarPlacar(jogo.id, 'visitante')">+</button>
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
                        <button type="button" class="flex h-7 w-7 items-center justify-center rounded-lg bg-bg-input text-text-muted transition hover:bg-primary/20 hover:text-primary" @click="decrementarPenal(jogo.id, 'mandante')">-</button>
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-bg-input text-base font-bold" :class="placaresEliminatorios[jogo.id].penal_mandante !== '' ? 'text-text' : 'text-text-muted'">
                          {{ placaresEliminatorios[jogo.id].penal_mandante !== '' ? placaresEliminatorios[jogo.id].penal_mandante : '-' }}
                        </div>
                        <button type="button" class="flex h-7 w-7 items-center justify-center rounded-lg bg-bg-input text-text-muted transition hover:bg-primary/20 hover:text-primary" @click="incrementarPenal(jogo.id, 'mandante')">+</button>
                      </div>
                      <span class="text-xs text-text-muted font-medium">x</span>
                      <div class="flex items-center gap-0.5">
                        <button type="button" class="flex h-7 w-7 items-center justify-center rounded-lg bg-bg-input text-text-muted transition hover:bg-primary/20 hover:text-primary" @click="decrementarPenal(jogo.id, 'visitante')">-</button>
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-bg-input text-base font-bold" :class="placaresEliminatorios[jogo.id].penal_visitante !== '' ? 'text-text' : 'text-text-muted'">
                          {{ placaresEliminatorios[jogo.id].penal_visitante !== '' ? placaresEliminatorios[jogo.id].penal_visitante : '-' }}
                        </div>
                        <button type="button" class="flex h-7 w-7 items-center justify-center rounded-lg bg-bg-input text-text-muted transition hover:bg-primary/20 hover:text-primary" @click="incrementarPenal(jogo.id, 'visitante')">+</button>
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

            <div v-if="subTabAtiva === 'artilheiro'" class="space-y-4">
              <div class="rounded-2xl border border-border bg-bg-card p-5">
                <h2 class="mb-2 text-base font-bold">Artilheiro da Copa</h2>
                <p class="mb-4 text-xs text-text-muted">Quem sera o artilheiro da Copa 2026?</p>
                <select v-model="artilheiroId" @change="agendarAutoSave()">
                  <option value="">Selecione o artilheiro</option>
                  <option v-for="j in jogadores" :key="j.id" :value="String(j.id)">{{ j.nome }} ({{ j.selecao_sigla }})</option>
                </select>
                <div v-if="artilheiroId" class="mt-3 flex items-center gap-2">
                  <svg class="h-4 w-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                  <span class="text-xs text-primary">Selecionado</span>
                </div>
              </div>

              <div class="rounded-2xl border border-border bg-bg-card p-5">
                <h2 class="mb-2 text-base font-bold">Resumo do Mata-Mata</h2>
                <p class="mb-4 text-xs text-text-muted">Campeao, vice e terceiro sao definidos pelos seus palpites no bracket.</p>
                <div class="grid gap-3 sm:grid-cols-3">
                  <div class="rounded-xl bg-bg-input p-3">
                    <span class="block text-[10px] uppercase text-text-muted">Campeao</span>
                    <span class="mt-1 block text-sm font-medium">{{ resumoBracket.campeao ?? 'A definir' }}</span>
                  </div>
                  <div class="rounded-xl bg-bg-input p-3">
                    <span class="block text-[10px] uppercase text-text-muted">Vice</span>
                    <span class="mt-1 block text-sm font-medium">{{ resumoBracket.vice ?? 'A definir' }}</span>
                  </div>
                  <div class="rounded-xl bg-bg-input p-3">
                    <span class="block text-[10px] uppercase text-text-muted">Terceiro</span>
                    <span class="mt-1 block text-sm font-medium">{{ resumoBracket.terceiro ?? 'A definir' }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Sidebar: Ranking ao Vivo -->
          <div class="hidden lg:block">
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
        <div v-if="carregandoRanking" class="space-y-4">
          <div class="h-48 animate-pulse rounded-3xl border border-border bg-bg-card" />
          <div class="rounded-3xl border border-border bg-bg-card p-4">
            <div v-for="n in 4" :key="n" class="mb-3 h-16 animate-pulse rounded-2xl bg-bg-input last:mb-0" />
          </div>
        </div>
        <div v-else-if="!ranking.length" class="rounded-2xl border border-border bg-bg-card py-8 text-center">
          <p class="text-text-muted">Nenhum resultado disponivel ainda.</p>
        </div>
        <div v-else class="space-y-4">
          <section class="overflow-hidden rounded-[28px] border border-primary/20 bg-[radial-gradient(circle_at_top,#123225,transparent_45%),linear-gradient(180deg,#111513,#0a0c0b)] p-5 sm:p-6">
            <div class="flex flex-wrap items-end justify-between gap-4">
              <div>
                <span class="inline-flex rounded-full border border-primary/30 bg-primary/10 px-3 py-1 text-xs font-semibold text-primary">Podio</span>
                <h2 class="mt-3 text-xl font-bold">Top 3 do bolao</h2>
                <p class="mt-1 text-sm text-text-secondary">Seu cupom continua destacado na classificacao geral logo abaixo.</p>
              </div>
              <span class="rounded-full border border-border bg-bg-card/60 px-3 py-1 text-xs text-text-muted">
                {{ ranking.length }} participante{{ ranking.length === 1 ? '' : 's' }}
              </span>
            </div>

            <div class="mt-6 grid gap-4 lg:grid-cols-[1fr_1.15fr_1fr] lg:items-end">
              <article
                v-for="item in podioRankingExibicao"
                :key="item.ranking.id"
                class="rounded-3xl border p-5 text-center"
                :class="item.posicao === 1 ? 'border-primary/40 bg-primary/10 lg:-translate-y-3' : item.posicao === 2 ? 'border-silver/40 bg-silver/10' : 'border-bronze/40 bg-bronze/10'"
              >
                <span class="inline-flex h-10 w-10 items-center justify-center rounded-full border text-sm font-bold" :class="item.posicao === 1 ? 'border-primary/40 text-primary' : item.posicao === 2 ? 'border-silver/40 text-silver' : 'border-bronze/40 text-bronze'">
                  {{ item.posicao }}
                </span>
                <div class="mx-auto mt-4 flex h-20 w-20 items-center justify-center rounded-full border border-current/40 text-2xl font-bold" :class="item.posicao === 1 ? 'text-primary' : item.posicao === 2 ? 'text-silver' : 'text-bronze'">
                  {{ iniciaisRanking(item.ranking.cupom.usuario.nome) }}
                </div>
                <p class="mt-4 text-lg font-bold">{{ item.ranking.cupom.usuario.nome }}</p>
                <p class="mt-1 text-xs uppercase tracking-[0.18em] text-text-muted">{{ item.rotulo }}</p>
                <p class="mt-4 text-4xl font-black text-primary">{{ item.ranking.pontuacao_total }}</p>
                <p class="text-xs text-text-muted">pontos</p>
              </article>
            </div>
          </section>

          <section class="rounded-3xl border border-border bg-bg-card p-4 sm:p-5">
            <div class="mb-4">
              <h3 class="text-lg font-bold">Classificacao geral</h3>
              <p class="text-sm text-text-secondary">Todos os cupons em ordem de pontuacao.</p>
            </div>
            <div class="space-y-3">
              <article
                v-for="(item, i) in ranking"
                :key="item.id"
                class="flex items-center gap-3 rounded-2xl border px-4 py-3"
                :class="item.cupom.id === cupom.id ? 'border-primary/40 bg-primary/10' : 'border-border bg-bg-input/60'"
              >
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border text-sm font-bold" :class="classePosicaoRanking(i)">
                  {{ i + 1 }}
                </div>
                <div class="min-w-0 flex-1">
                  <div class="flex flex-wrap items-center gap-2">
                    <strong class="truncate text-sm">{{ item.cupom.usuario.nome }}</strong>
                    <span v-if="item.cupom.id === cupom.id" class="rounded-full bg-primary px-2 py-0.5 text-[10px] font-bold text-bg">Voce</span>
                  </div>
                  <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-text-muted">
                    <span>Cupom {{ item.cupom.codigo }}</span>
                    <span>{{ item.quantidade_placares_exatos }} exatos</span>
                    <span>{{ item.quantidade_classificados_corretos }} classificados</span>
                  </div>
                </div>
                <div class="text-right">
                  <strong class="block text-2xl font-black text-primary">{{ item.pontuacao_total }}</strong>
                  <span class="text-xs text-text-muted">pts</span>
                </div>
              </article>
            </div>
          </section>
        </div>
      </section>

      <!-- ═══════ Tab Meus Resultados ═══════ -->
      <section v-if="tabAtiva === 'resultados'">
        <div v-if="cupom.eventos_pontuacao?.length" class="rounded-2xl border border-border bg-bg-card p-5">
          <h2 class="mb-4 text-lg font-bold">Eventos de Pontuacao</h2>
          <div class="space-y-2">
            <div v-for="evento in cupom.eventos_pontuacao" :key="evento.id" class="flex items-center justify-between rounded-lg bg-bg-input px-3.5 py-2.5">
              <span class="text-sm">{{ evento.descricao }}</span>
              <span class="text-sm font-bold text-primary">+{{ evento.pontos }} pts</span>
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
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { requisicaoApi } from '../services/api'
import { usarTorneioStore } from '../stores/torneio'
import { useToast } from '../composables/useToast'
import type { Aposta, BracketJogoCupom, Cupom, RankingItem, ResumoBracketCupom, Selecao, Torneio } from '../tipos'

const rota = useRoute()
const torneioStore = usarTorneioStore()
const { mostrar } = useToast()

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

const tabAtiva = ref<'palpites' | 'ranking' | 'resultados'>('palpites')
const subTabAtiva = ref<'jogos' | 'artilheiro'>('jogos')
const indiceFase = ref(0)
const diaSelecionado = ref('')

const tabs = [
  { id: 'palpites' as const, nome: 'Palpites', icone: 'M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125' },
  { id: 'ranking' as const, nome: 'Ranking', icone: 'M16.5 18.75h-9m9 0a3 3 0 013 3h-15a3 3 0 013-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872' },
  { id: 'resultados' as const, nome: 'Meus Resultados', icone: 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z' },
]

const subTabs = [
  { id: 'jogos' as const, nome: 'Jogos' },
  { id: 'artilheiro' as const, nome: 'Artilheiro' },
]

const placaresGrupos = ref<Record<number, { placar_mandante: string; placar_visitante: string }>>({})
const placaresEliminatorios = ref<Record<number, { placar_mandante: string; placar_visitante: string; penal_mandante: string; penal_visitante: string }>>({})
const artilheiroId = ref('')

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

function bandeira(sigla: string): string {
  const iso = fifaParaIso[sigla]
  if (!iso) return ''
  return `https://flagcdn.com/w80/${iso}.png`
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

const jogosGruposDoTorneio = computed(() => torneio.value?.jogos.filter((jogo) => jogo.fase.tipo === 'grupos') ?? [])
const jogosEliminatoriosDoCupom = computed(() => bracketCupom.value.filter((jogo) => jogo.fase.tipo !== 'grupos'))

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
const jogadores = computed(() => todasSelecoes.value.flatMap(s => (s.jogadores ?? []).map(j => ({ ...j, selecao_sigla: s.sigla }))))
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
const podioRankingExibicao = computed(() => [
  podioRanking.value[1] ? { ranking: podioRanking.value[1], posicao: 2, rotulo: 'Segundo lugar' } : null,
  podioRanking.value[0] ? { ranking: podioRanking.value[0], posicao: 1, rotulo: 'Primeiro lugar' } : null,
  podioRanking.value[2] ? { ranking: podioRanking.value[2], posicao: 3, rotulo: 'Terceiro lugar' } : null,
].filter((item): item is { ranking: RankingItem; posicao: number; rotulo: string } => Boolean(item)))

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
  const campo = lado === 'mandante' ? 'penal_mandante' : 'penal_visitante'
  const current = placaresEliminatorios.value[jogoId][campo]
  placaresEliminatorios.value[jogoId][campo] = current === '' ? '0' : String(Number(current) + 1)
  agendarAutoSave()
}

function decrementarPenal(jogoId: number, lado: 'mandante' | 'visitante') {
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

  // Artilheiro
  if (artilheiroId.value) {
    apostasArr.push({ tipo: 'artilheiro', torneio_id: torneio.value.id, jogador_id: Number(artilheiroId.value) })
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

function encontrarAposta(tipo: string, referenciaId?: number) {
  return apostas.value.find(a => {
    if (a.tipo !== tipo) return false
    if (tipo === 'artilheiro') return true
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
  if (sobrescrever || !artilheiroId.value) {
    artilheiroId.value = String(encontrarAposta('artilheiro')?.conteudo.jogador_id ?? '')
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

// Auto-select first day when fase changes
watch([indiceFase, jogosFaseAtual], () => {
  if (diasComJogos.value.length) diaSelecionado.value = diasComJogos.value[0].data
  else diaSelecionado.value = ''
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
