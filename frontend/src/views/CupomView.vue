<template>
  <div class="mx-auto max-w-6xl">
    <!-- Loading -->
    <div v-if="carregando" class="flex items-center justify-center py-20">
      <span class="text-text-muted">Carregando...</span>
    </div>

    <div v-else-if="cupom && torneio" class="space-y-4">
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
      <div class="flex overflow-x-auto border-b border-border">
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
      <section v-if="tabAtiva === 'palpites'">
        <div class="grid gap-6 lg:grid-cols-[1fr_300px]">
          <!-- Main column -->
          <div class="space-y-4">
            <!-- Fase navigator -->
            <div class="flex items-center justify-between rounded-xl bg-bg-card border border-border px-4 py-3">
              <button @click="faseAnterior" class="rounded-lg p-1.5 text-text-muted transition hover:bg-bg-input hover:text-text" :disabled="indiceFase <= 0">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" /></svg>
              </button>
              <h2 class="text-base font-bold">{{ faseAtual?.nome ?? 'Fase de Grupos' }} - {{ rodadaAtual?.ordem ?? 1 }}</h2>
              <button @click="faseProxima" class="rounded-lg p-1.5 text-text-muted transition hover:bg-bg-input hover:text-text">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
              </button>
            </div>

            <!-- Day selector — horizontal scroll, compact -->
            <div class="flex gap-1.5 overflow-x-auto pb-1 scrollbar-none">
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
                <!-- Dot: sem palpite -->
                <span v-if="dia.semPalpite && diaSelecionado !== dia.data" class="absolute -top-1 -right-1 h-2 w-2 rounded-full bg-warning" />
                <span class="text-[10px] uppercase font-medium">{{ dia.diaSemana }}</span>
                <span class="text-sm font-bold">{{ dia.diaNumero }}</span>
                <span class="text-[10px] opacity-70"><sup>({{ dia.totalJogos }})</sup></span>
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

            <!-- Match cards -->
            <div class="space-y-4">
              <div
                v-for="jogo in jogosDoDia"
                :key="jogo.id"
                class="rounded-2xl border bg-bg-card p-5 transition-colors"
                :class="temPalpite(jogo.id) ? 'border-primary/30' : 'border-border'"
              >
                <!-- Top row: palpite status + group -->
                <div class="mb-4 flex items-center justify-between">
                  <div class="flex items-center gap-2">
                    <span v-if="temPalpite(jogo.id)" class="inline-flex items-center gap-1 rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-medium text-primary">
                      <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                      Com palpite
                    </span>
                    <span v-else class="inline-flex items-center gap-1 rounded-full bg-warning/10 px-2 py-0.5 text-[10px] font-medium text-warning">
                      <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" /></svg>
                      Sem palpite
                    </span>
                    <span class="text-xs text-text-muted">{{ formatarHora(jogo.data_hora_inicio) }}</span>
                  </div>
                  <span v-if="jogo.grupo" class="text-xs font-medium text-primary">{{ jogo.grupo.nome }}</span>
                  <span v-else class="text-xs font-medium text-primary">{{ jogo.fase.nome }}</span>
                </div>

                <!-- Teams + score -->
                <div class="flex items-center gap-3 sm:gap-6">
                  <!-- Home team -->
                  <div class="flex-1 text-center">
                    <img
                      :src="bandeira(jogo.selecao_mandante.sigla)"
                      :alt="jogo.selecao_mandante.nome"
                      class="mx-auto h-10 w-14 rounded object-cover shadow"
                      @error="($event.target as HTMLImageElement).style.display='none'"
                    />
                    <p class="mt-2 text-xs font-medium sm:text-sm">{{ jogo.selecao_mandante.nome }}</p>
                  </div>

                  <!-- Score inputs with +/- buttons -->
                  <div class="flex items-center gap-1.5 sm:gap-2">
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
                  <div class="flex-1 text-center">
                    <img
                      :src="bandeira(jogo.selecao_visitante.sigla)"
                      :alt="jogo.selecao_visitante.nome"
                      class="mx-auto h-10 w-14 rounded object-cover shadow"
                      @error="($event.target as HTMLImageElement).style.display='none'"
                    />
                    <p class="mt-2 text-xs font-medium sm:text-sm">{{ jogo.selecao_visitante.nome }}</p>
                  </div>
                </div>

                <!-- Knockout: who advances -->
                <div v-if="jogo.fase.tipo !== 'grupos' && placaresEliminatorios[jogo.id]" class="mt-4">
                  <label class="block">
                    <span class="mb-1.5 block text-xs text-text-muted">Quem avanca?</span>
                    <select v-model="placaresEliminatorios[jogo.id].selecao_classificada_id" @change="agendarAutoSave()">
                      <option value="">Selecione</option>
                      <option :value="String(jogo.selecao_mandante.id)">{{ jogo.selecao_mandante.nome }}</option>
                      <option :value="String(jogo.selecao_visitante.id)">{{ jogo.selecao_visitante.nome }}</option>
                    </select>
                  </label>
                </div>
              </div>

              <div v-if="!jogosDoDia.length" class="rounded-2xl border border-border bg-bg-card py-12 text-center">
                <p class="text-text-muted">Nenhum jogo neste dia.</p>
              </div>
            </div>

            <!-- Sub-tabs for other bet types -->
            <div class="flex gap-2 overflow-x-auto border-t border-border pt-4">
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

            <!-- Classificacao dos Grupos -->
            <div v-if="subTabAtiva === 'classificacao'" class="rounded-2xl border border-border bg-bg-card p-5">
              <h2 class="mb-2 text-base font-bold">Classificados dos Grupos</h2>
              <p class="mb-4 text-xs text-text-muted">Selecione o primeiro e segundo colocado de cada grupo.</p>
              <div class="space-y-4">
                <div v-for="grupo in torneio.grupos" :key="grupo.id" class="rounded-xl bg-bg-input p-4">
                  <h3 class="mb-3 text-sm font-bold text-primary">{{ grupo.nome }}</h3>
                  <div class="grid gap-3 sm:grid-cols-2">
                    <label class="block">
                      <span class="mb-1 block text-xs text-text-muted">Primeiro colocado</span>
                      <select v-model="classificacaoGrupos[grupo.id].primeiro">
                        <option value="">Selecione</option>
                        <option v-for="s in grupo.selecoes" :key="s.id" :value="String(s.id)">{{ s.nome }}</option>
                      </select>
                    </label>
                    <label class="block">
                      <span class="mb-1 block text-xs text-text-muted">Segundo colocado</span>
                      <select v-model="classificacaoGrupos[grupo.id].segundo">
                        <option value="">Selecione</option>
                        <option v-for="s in grupo.selecoes" :key="s.id" :value="String(s.id)">{{ s.nome }}</option>
                      </select>
                    </label>
                  </div>
                </div>
              </div>
              <button type="button" @click="salvarClassificacao" class="mt-4 w-full rounded-xl bg-primary py-3 text-sm font-bold text-bg transition hover:bg-primary-hover">
                Salvar classificados
              </button>
            </div>

            <!-- Finais & Artilheiro -->
            <div v-if="subTabAtiva === 'finais'" class="space-y-4">
              <div class="rounded-2xl border border-border bg-bg-card p-5">
                <h2 class="mb-4 text-base font-bold">Artilheiro</h2>
                <select v-model="artilheiroId">
                  <option value="">Selecione o artilheiro</option>
                  <option v-for="j in jogadores" :key="j.id" :value="String(j.id)">{{ j.nome }} ({{ j.selecao_sigla }})</option>
                </select>
                <button type="button" @click="salvarArtilheiro" class="mt-3 w-full rounded-xl bg-primary py-3 text-sm font-bold text-bg transition hover:bg-primary-hover">
                  Salvar artilheiro
                </button>
              </div>
              <div class="rounded-2xl border border-border bg-bg-card p-5">
                <h2 class="mb-4 text-base font-bold">Palpites Finais</h2>
                <div class="space-y-3">
                  <label class="block">
                    <span class="mb-1 block text-xs text-text-muted">Campeao</span>
                    <select v-model="palpitesFinais.campeao">
                      <option value="">Selecione</option>
                      <option v-for="s in todasSelecoes" :key="s.id" :value="String(s.id)">{{ s.nome }}</option>
                    </select>
                  </label>
                  <label class="block">
                    <span class="mb-1 block text-xs text-text-muted">Vice-campeao</span>
                    <select v-model="palpitesFinais.vice_campeao">
                      <option value="">Selecione</option>
                      <option v-for="s in todasSelecoes" :key="s.id" :value="String(s.id)">{{ s.nome }}</option>
                    </select>
                  </label>
                  <label class="block">
                    <span class="mb-1 block text-xs text-text-muted">Terceiro colocado</span>
                    <select v-model="palpitesFinais.terceiro_colocado">
                      <option value="">Selecione</option>
                      <option v-for="s in todasSelecoes" :key="s.id" :value="String(s.id)">{{ s.nome }}</option>
                    </select>
                  </label>
                </div>
                <button type="button" @click="salvarPalpitesFinais" class="mt-4 w-full rounded-xl bg-primary py-3 text-sm font-bold text-bg transition hover:bg-primary-hover">
                  Salvar palpites finais
                </button>
              </div>
            </div>
          </div>

          <!-- Sidebar: Ranking ao Vivo -->
          <div class="hidden lg:block">
            <div class="sticky top-20 rounded-2xl border border-border bg-bg-card p-4">
              <div class="flex items-center gap-2 mb-4">
                <span class="text-lg">🏆</span>
                <h3 class="text-sm font-bold">Ranking ao Vivo</h3>
              </div>

              <div v-if="carregandoRanking" class="space-y-2">
                <div v-for="n in 5" :key="n" class="flex items-center gap-2">
                  <div class="h-8 w-8 animate-pulse rounded-full bg-bg-input" />
                  <div class="h-3 flex-1 animate-pulse rounded bg-bg-input" />
                </div>
              </div>

              <div v-else-if="!ranking.length" class="py-4 text-center text-xs text-text-muted">
                Nenhum palpite registrado
              </div>

              <div v-else class="space-y-2">
                <div
                  v-for="(item, i) in ranking.slice(0, 10)"
                  :key="item.id"
                  class="flex items-center gap-2 rounded-lg px-2 py-1.5 transition"
                  :class="item.cupom.id === cupom.id ? 'bg-primary/10' : 'hover:bg-bg-input'"
                >
                  <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                    :class="{ 'bg-gold/20 text-gold': i === 0, 'bg-silver/20 text-silver': i === 1, 'bg-bronze/20 text-bronze': i === 2, 'bg-bg-input text-text-muted': i > 2 }">
                    {{ i + 1 }}
                  </div>
                  <div class="min-w-0 flex-1">
                    <p class="truncate text-xs font-medium">{{ item.cupom.usuario.nome }}</p>
                    <p class="text-[10px] text-text-muted">{{ item.cupom.codigo }}</p>
                  </div>
                  <span class="text-sm font-bold" :class="item.cupom.id === cupom.id ? 'text-primary' : 'text-text'">
                    {{ item.pontuacao_total }}
                  </span>
                  <span v-if="item.cupom.id === cupom.id" class="rounded-full bg-primary px-2 py-0.5 text-[10px] font-bold text-bg">
                    Voce
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- ═══════ Tab Ranking ═══════ -->
      <section v-if="tabAtiva === 'ranking'">
        <div v-if="carregandoRanking" class="rounded-2xl border border-border bg-bg-card overflow-hidden">
          <div class="divide-y divide-border/50">
            <div v-for="n in 5" :key="n" class="flex items-center gap-4 px-4 py-3">
              <div class="h-8 w-8 animate-pulse rounded-full bg-bg-input" />
              <div class="flex-1"><div class="h-4 w-28 animate-pulse rounded bg-bg-input" /></div>
              <div class="h-5 w-10 animate-pulse rounded bg-bg-input" />
            </div>
          </div>
        </div>
        <div v-else-if="!ranking.length" class="rounded-2xl border border-border bg-bg-card py-8 text-center">
          <p class="text-text-muted">Nenhum resultado disponivel ainda.</p>
        </div>
        <div v-else class="rounded-2xl border border-border bg-bg-card overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full">
              <thead><tr class="bg-bg-input text-xs uppercase tracking-wider text-text-muted">
                <th class="px-4 py-3 text-left">#</th><th class="px-4 py-3 text-left">Cupom</th><th class="px-4 py-3 text-left">Usuario</th><th class="px-4 py-3 text-right">Pontos</th><th class="px-4 py-3 text-right">Exatos</th>
              </tr></thead>
              <tbody>
                <tr v-for="(item, i) in ranking" :key="item.id" class="border-t border-border/50 transition hover:bg-bg-card-hover" :class="item.cupom.id === cupom.id ? 'bg-primary/10' : ''">
                  <td class="px-4 py-3"><span class="inline-flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold" :class="{ 'text-gold': i === 0, 'text-silver': i === 1, 'text-bronze': i === 2, 'text-text-muted': i > 2 }">{{ i + 1 }}</span></td>
                  <td class="px-4 py-3 text-sm font-mono text-text-muted">{{ item.cupom.codigo }}</td>
                  <td class="px-4 py-3 text-sm font-medium">{{ item.cupom.usuario.nome }}</td>
                  <td class="px-4 py-3 text-right font-bold text-primary">{{ item.pontuacao_total }}</td>
                  <td class="px-4 py-3 text-right text-sm">{{ item.quantidade_placares_exatos }}</td>
                </tr>
              </tbody>
            </table>
          </div>
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
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { requisicaoApi } from '../services/api'
import { usarTorneioStore } from '../stores/torneio'
import { useToast } from '../composables/useToast'
import type { Aposta, Cupom, RankingItem, Selecao, Torneio } from '../tipos'

const rota = useRoute()
const torneioStore = usarTorneioStore()
const { mostrar } = useToast()

const torneio = ref<Torneio | null>(null)
const cupom = ref<Cupom | null>(null)
const apostas = ref<Aposta[]>([])
const ranking = ref<RankingItem[]>([])
const carregando = ref(true)
const carregandoRanking = ref(false)

const tabAtiva = ref<'palpites' | 'ranking' | 'resultados'>('palpites')
const subTabAtiva = ref<'jogos' | 'classificacao' | 'finais'>('jogos')
const indiceFase = ref(0)
const diaSelecionado = ref('')

const tabs = [
  { id: 'palpites' as const, nome: 'Palpites', icone: 'M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125' },
  { id: 'ranking' as const, nome: 'Ranking', icone: 'M16.5 18.75h-9m9 0a3 3 0 013 3h-15a3 3 0 013-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872' },
  { id: 'resultados' as const, nome: 'Meus Resultados', icone: 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z' },
]

const subTabs = [
  { id: 'jogos' as const, nome: 'Jogos' },
  { id: 'classificacao' as const, nome: 'Classificacao' },
  { id: 'finais' as const, nome: 'Finais & Artilheiro' },
]

const placaresGrupos = ref<Record<number, { placar_mandante: string; placar_visitante: string }>>({})
const placaresEliminatorios = ref<Record<number, { placar_mandante: string; placar_visitante: string; selecao_classificada_id: string }>>({})
const classificacaoGrupos = ref<Record<number, { primeiro: string; segundo: string }>>({})
const artilheiroId = ref('')
const palpitesFinais = ref({ campeao: '', vice_campeao: '', terceiro_colocado: '' })

// Auto-save
const salvando = ref(false)
const ultimoSalvo = ref(false)
let autoSaveTimer: ReturnType<typeof setTimeout> | null = null

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

function bandeira(sigla: string): string {
  const iso = fifaParaIso[sigla]
  if (!iso) return ''
  return `https://flagcdn.com/w80/${iso}.png`
}

function temPalpite(jogoId: number): boolean {
  return placaresGrupos.value[jogoId]?.placar_mandante !== '' && placaresGrupos.value[jogoId]?.placar_visitante !== ''
}

// Fases e rodadas disponíveis
const fasesRodadas = computed(() => {
  if (!torneio.value) return []
  const items: { fase: typeof torneio.value.fases[0]; rodada?: typeof torneio.value.jogos[0]['rodada'] }[] = []
  const faseGrupos = torneio.value.fases.find(f => f.tipo === 'grupos')
  if (faseGrupos) {
    const rodadaObjs = torneio.value.jogos.filter(j => j.rodada).map(j => j.rodada!).filter((r, i, a) => a.findIndex(x => x.id === r.id) === i).sort((a, b) => a.ordem - b.ordem)
    for (const r of rodadaObjs) {
      items.push({ fase: faseGrupos, rodada: r })
    }
  }
  for (const fase of torneio.value.fases.filter(f => f.tipo !== 'grupos').sort((a, b) => a.ordem - b.ordem)) {
    items.push({ fase })
  }
  return items
})

const faseAtual = computed(() => fasesRodadas.value[indiceFase.value]?.fase)
const rodadaAtual = computed(() => fasesRodadas.value[indiceFase.value]?.rodada)

// Jogos da fase/rodada atual
const jogosFaseAtual = computed(() => {
  if (!torneio.value || !faseAtual.value) return []
  let jogos = torneio.value.jogos.filter(j => j.fase_id === faseAtual.value!.id)
  if (rodadaAtual.value) {
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
    if (!temPalpite(jogo.id)) entry.semPalpite = true
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
  if (!faseAtual.value?.data_fechamento) return 'Sem prazo definido'
  const diff = new Date(faseAtual.value.data_fechamento).getTime() - Date.now()
  if (diff <= 0) return 'Fechado'
  const dias = Math.floor(diff / 86400000)
  if (dias > 30) return `Fecha em ${Math.floor(dias / 30)} meses`
  if (dias > 0) return `Fecha em ${dias} dia${dias > 1 ? 's' : ''}`
  const horas = Math.floor(diff / 3600000)
  return `Fecha em ${horas}h`
})

const todasSelecoes = computed<Selecao[]>(() => torneio.value?.grupos.flatMap(g => g.selecoes) ?? [])
const jogadores = computed(() => todasSelecoes.value.flatMap(s => (s.jogadores ?? []).map(j => ({ ...j, selecao_sigla: s.sigla }))))

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

// Auto-save debounce 2s
function agendarAutoSave() {
  if (autoSaveTimer) clearTimeout(autoSaveTimer)
  autoSaveTimer = setTimeout(() => autoSalvar(), 2000)
}

async function autoSalvar() {
  const todos = jogosFaseAtual.value
  const jogosGrupo = todos.filter(j => j.fase.tipo === 'grupos')
  const jogosElim = todos.filter(j => j.fase.tipo !== 'grupos')

  const apostasGrupo = jogosGrupo
    .filter(j => placaresGrupos.value[j.id].placar_mandante !== '' && placaresGrupos.value[j.id].placar_visitante !== '')
    .map(j => ({ tipo: 'placar_jogo_grupos', jogo_id: j.id, placar_mandante: Number(placaresGrupos.value[j.id].placar_mandante), placar_visitante: Number(placaresGrupos.value[j.id].placar_visitante) }))

  const apostasElim = jogosElim
    .filter(j => placaresEliminatorios.value[j.id]?.placar_mandante !== '' && placaresEliminatorios.value[j.id]?.placar_visitante !== '' && placaresEliminatorios.value[j.id]?.selecao_classificada_id !== '')
    .map(j => ({ tipo: 'placar_jogo_eliminatoria', jogo_id: j.id, placar_mandante: Number(placaresGrupos.value[j.id].placar_mandante), placar_visitante: Number(placaresGrupos.value[j.id].placar_visitante), selecao_classificada_id: Number(placaresEliminatorios.value[j.id].selecao_classificada_id) }))

  const apostasArr = [...apostasGrupo, ...apostasElim]
  if (!apostasArr.length) return

  salvando.value = true
  ultimoSalvo.value = false
  try {
    await requisicaoApi(`/cupons/${rota.params.id}/apostas/lote`, { metodo: 'POST', corpo: { apostas: apostasArr } })
    // Reload apostas silently
    const rA = await requisicaoApi<{ apostas: Aposta[] }>(`/cupons/${rota.params.id}/apostas`)
    apostas.value = rA.apostas
    ultimoSalvo.value = true
    setTimeout(() => { ultimoSalvo.value = false }, 3000)
  } catch {
    mostrar('erro', 'Falha ao salvar automaticamente.')
  } finally {
    salvando.value = false
  }
}

function formatarHora(dataHora: string) {
  return new Date(dataHora).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })
}

function encontrarAposta(tipo: string, referenciaId?: number) {
  return apostas.value.find(a => {
    if (a.tipo !== tipo) return false
    if (tipo === 'classificacao_grupo') return a.grupo_id === referenciaId
    if (tipo === 'artilheiro') return true
    if (['campeao', 'vice_campeao', 'terceiro_colocado'].includes(tipo)) return true
    return a.jogo_id === referenciaId
  })
}

function preencherFormulario() {
  if (!torneio.value) return
  for (const jogo of torneio.value.jogos) {
    const tipo = jogo.fase.tipo === 'grupos' ? 'placar_jogo_grupos' : 'placar_jogo_eliminatoria'
    const aposta = encontrarAposta(tipo, jogo.id)
    placaresGrupos.value[jogo.id] = {
      placar_mandante: String(aposta?.conteudo.placar_mandante ?? ''),
      placar_visitante: String(aposta?.conteudo.placar_visitante ?? ''),
    }
    if (jogo.fase.tipo !== 'grupos') {
      placaresEliminatorios.value[jogo.id] = {
        placar_mandante: String(aposta?.conteudo.placar_mandante ?? ''),
        placar_visitante: String(aposta?.conteudo.placar_visitante ?? ''),
        selecao_classificada_id: String(aposta?.conteudo.selecao_classificada_id ?? ''),
      }
    }
  }
  for (const grupo of torneio.value.grupos) {
    const aposta = encontrarAposta('classificacao_grupo', grupo.id)
    classificacaoGrupos.value[grupo.id] = {
      primeiro: String(aposta?.conteudo.primeiro_colocado_id ?? ''),
      segundo: String(aposta?.conteudo.segundo_colocado_id ?? ''),
    }
  }
  artilheiroId.value = String(encontrarAposta('artilheiro')?.conteudo.jogador_id ?? '')
  palpitesFinais.value.campeao = String(encontrarAposta('campeao')?.conteudo.selecao_id ?? '')
  palpitesFinais.value.vice_campeao = String(encontrarAposta('vice_campeao')?.conteudo.selecao_id ?? '')
  palpitesFinais.value.terceiro_colocado = String(encontrarAposta('terceiro_colocado')?.conteudo.selecao_id ?? '')
}

async function carregarDados() {
  carregando.value = true
  try {
    const [rT, rC, rA] = await Promise.all([
      requisicaoApi<{ torneio: Torneio }>('/torneio'),
      requisicaoApi<{ cupom: Cupom }>(`/cupons/${rota.params.id}`),
      requisicaoApi<{ apostas: Aposta[] }>(`/cupons/${rota.params.id}/apostas`),
    ])
    torneio.value = rT.torneio
    cupom.value = rC.cupom
    apostas.value = rA.apostas
    preencherFormulario()
  } catch {
    mostrar('erro', 'Falha ao carregar dados do cupom.')
  } finally {
    carregando.value = false
  }
}

async function salvar(apostasArr: Record<string, unknown>[]) {
  if (!apostasArr.length) { mostrar('erro', 'Preencha pelo menos um palpite.'); return }
  try {
    await requisicaoApi(`/cupons/${rota.params.id}/apostas/lote`, { metodo: 'POST', corpo: { apostas: apostasArr } })
    mostrar('sucesso', 'Salvo com sucesso!')
    const rA = await requisicaoApi<{ apostas: Aposta[] }>(`/cupons/${rota.params.id}/apostas`)
    apostas.value = rA.apostas
  } catch (e) { mostrar('erro', e instanceof Error ? e.message : 'Falha ao salvar.') }
}

async function salvarClassificacao() {
  if (!torneio.value) return
  await salvar(torneio.value.grupos.filter(g => classificacaoGrupos.value[g.id].primeiro !== '' && classificacaoGrupos.value[g.id].segundo !== '').map(g => ({ tipo: 'classificacao_grupo', torneio_id: torneio.value?.id, grupo_id: g.id, primeiro_colocado_id: Number(classificacaoGrupos.value[g.id].primeiro), segundo_colocado_id: Number(classificacaoGrupos.value[g.id].segundo) })))
}

async function salvarArtilheiro() {
  if (!torneio.value || !artilheiroId.value) return
  await salvar([{ tipo: 'artilheiro', torneio_id: torneio.value.id, jogador_id: Number(artilheiroId.value) }])
}

async function salvarPalpitesFinais() {
  if (!torneio.value) return
  await salvar([['campeao', palpitesFinais.value.campeao], ['vice_campeao', palpitesFinais.value.vice_campeao], ['terceiro_colocado', palpitesFinais.value.terceiro_colocado]].filter(([, v]) => v !== '').map(([tipo, valor]) => ({ tipo, torneio_id: torneio.value?.id, selecao_id: Number(valor) })))
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

onMounted(async () => {
  await Promise.all([carregarDados(), torneioStore.carregar()])
  carregarRanking()
})
</script>
