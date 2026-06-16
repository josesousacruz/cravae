<template>
  <div class="mx-auto max-w-md space-y-4">
    <!-- Cabecalho -->
    <header class="flex items-center justify-between gap-3">
      <div class="flex items-center gap-2">
        <svg class="h-7 w-7 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
          <path stroke-linecap="round" stroke-linejoin="round" d="M8 21h8m-4-4v4m-7-17h14v3a5 5 0 0 1-5 5h-4a5 5 0 0 1-5-5V4Zm0 2H3a2 2 0 0 0 2 2m14-4h2a2 2 0 0 1-2 4" />
        </svg>
        <h1 class="text-2xl font-bold text-text">Ranking</h1>
      </div>
      <div class="flex items-center gap-2">
        <button
          v-for="filtro in ['Rodada', 'Mes']"
          :key="filtro"
          type="button"
          class="inline-flex items-center gap-1 rounded-xl border border-border bg-bg-card px-3 py-2 text-sm font-medium text-text-secondary transition-colors hover:border-primary/30"
          @click="avisarEmBreve"
        >
          {{ filtro }}
          <svg class="h-4 w-4 text-text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" /></svg>
        </button>
      </div>
    </header>

    <!-- Progresso de partidas + Pontos -->
    <div class="flex items-center justify-between gap-3">
      <div class="inline-flex items-center gap-1.5 rounded-full border border-border bg-bg-card px-3 py-1.5 text-sm">
        <strong class="text-text">{{ partidas.finalizadas }} / {{ partidas.total }}</strong>
        <span class="text-text-secondary">partidas finalizadas</span>
        <span class="text-text-muted">({{ percentualPartidas }}%)</span>
      </div>
      <button
        type="button"
        class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-sm font-medium transition-colors"
        :class="mostrarPontos ? 'border-primary/40 bg-primary/10 text-primary' : 'border-border bg-bg-card text-text-secondary hover:border-primary/30'"
        @click="mostrarPontos = !mostrarPontos"
      >
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9" /><path stroke-linecap="round" stroke-linejoin="round" d="M12 16v-4m0-4h.01" /></svg>
        Pontos
      </button>
    </div>

    <!-- Legenda de pontuacao -->
    <div v-if="mostrarPontos" class="rounded-2xl border border-border bg-bg-card p-4">
      <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-primary">Como pontuar</p>
      <ul v-if="regras.length" class="space-y-1.5">
        <li v-for="regra in regras" :key="regra.id" class="flex items-center justify-between gap-3 text-sm">
          <span class="text-text-secondary">{{ regra.nome }}</span>
          <span class="shrink-0 font-bold text-primary">+{{ regra.pontos }} pts</span>
        </li>
      </ul>
      <p v-else class="text-sm text-text-muted">Regras de pontuacao ainda nao configuradas.</p>
    </div>

    <!-- Skeleton -->
    <div v-if="carregando" class="h-80 animate-pulse rounded-[28px] border border-border/50 bg-bg-card/40" />

    <!-- Podio -->
    <section
      v-else-if="podioOrdenado.length"
      class="relative overflow-hidden rounded-[28px] border border-primary/30 bg-[radial-gradient(circle_at_top,#0f2a1f,transparent_60%),linear-gradient(180deg,#111513,#0a0c0b)] p-5 pt-6 shadow-[0_0_40px_-12px_rgba(16,185,129,0.4)]"
    >
      <!-- Topo: titulo e compartilhar -->
      <div class="relative mb-2 flex items-center justify-center">
        <span class="inline-flex items-center gap-2 rounded-full border border-primary/50 bg-primary/10 px-5 py-2 text-sm font-bold uppercase tracking-wider text-primary shadow-[0_0_20px_-4px_rgba(16,185,129,0.6)]">
          🏆 Ranking 🏆
        </span>
        <button
          type="button"
          class="absolute right-0 inline-flex h-9 w-9 items-center justify-center rounded-full border border-primary/30 bg-primary/10 text-primary transition-colors hover:bg-primary/20"
          aria-label="Compartilhar ranking"
          @click="compartilhar"
        >
          <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8.7 10.7a3 3 0 1 0 0 2.6m0-2.6 6.6-3.8m-6.6 6.4 6.6 3.8M18 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm0 14a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" /></svg>
        </button>
      </div>

      <!-- Coroa -->
      <div class="flex justify-center text-primary">
        <svg class="h-7 w-7" viewBox="0 0 24 24" fill="currentColor"><path d="M3 7l4 4 5-6 5 6 4-4-2 12H5L3 7Z" /></svg>
      </div>

      <!-- Tres posicoes -->
      <div class="mt-1 grid grid-cols-3 items-end gap-2">
        <article v-for="lugar in podioOrdenado" :key="lugar.item.id" class="flex flex-col items-center text-center" :class="lugar.posicao === 1 ? '-translate-y-3' : ''">
          <div class="relative">
            <div
              class="overflow-hidden rounded-full border-[3px]"
              :class="[lugar.anel, lugar.posicao === 1 ? 'h-24 w-24 shadow-[0_0_24px_-4px_rgba(16,185,129,0.7)]' : 'h-20 w-20']"
            >
              <img v-if="fotoDe(lugar.item)" :src="fotoDe(lugar.item)!" :alt="lugar.item.cupom.usuario.nome" class="h-full w-full object-cover" />
              <div v-else class="flex h-full w-full items-center justify-center bg-bg-input text-xl font-bold" :class="lugar.cor">
                {{ iniciais(lugar.item.cupom.usuario.nome) }}
              </div>
            </div>
            <span
              class="absolute -bottom-1 left-1/2 inline-flex h-7 w-7 -translate-x-1/2 items-center justify-center rounded-full border-2 border-bg text-sm font-bold text-bg"
              :class="lugar.fundoBadge"
            >
              {{ lugar.posicao }}
            </span>
          </div>
          <p class="mt-3 truncate text-sm font-bold text-text" :title="lugar.item.cupom.usuario.nome">
            {{ primeiroNome(lugar.item.cupom.usuario.nome) }}
          </p>
          <p class="mt-1 text-2xl font-black tabular-nums" :class="lugar.cor">{{ inteiro(lugar.item.pontuacao_total) }}</p>
          <p class="text-[11px] text-text-muted">pontos</p>
        </article>
      </div>
    </section>

    <!-- Estado vazio -->
    <div v-else class="rounded-[28px] border border-border bg-bg-card py-12 text-center">
      <p class="px-6 text-sm text-text-muted">Nenhum resultado disponivel. O ranking sera atualizado conforme os resultados forem lancados.</p>
    </div>

    <!-- Sua posicao atual -->
    <section
      v-if="minhaPosicao"
      class="relative overflow-hidden rounded-[28px] border border-violet-500/40 bg-[linear-gradient(135deg,rgba(139,92,246,0.18),rgba(139,92,246,0.04))] p-5 shadow-[0_0_30px_-12px_rgba(139,92,246,0.6)]"
    >
      <div class="flex items-center gap-4">
        <div class="h-16 w-16 shrink-0 overflow-hidden rounded-full border-2 border-violet-400/60">
          <img v-if="minhaFoto" :src="minhaFoto" :alt="autenticacao.nome" class="h-full w-full object-cover" />
          <div v-else class="flex h-full w-full items-center justify-center bg-bg-input text-lg font-bold text-violet-300">
            {{ iniciais(minhaPosicao.item.cupom.usuario.nome) }}
          </div>
        </div>
        <div class="min-w-0 flex-1">
          <p class="text-[11px] font-semibold uppercase tracking-wider text-violet-300">Sua posicao atual</p>
          <p class="truncate text-lg font-bold text-text">{{ minhaPosicao.item.cupom.usuario.nome }}</p>
          <p class="mt-0.5 text-xs text-text-secondary">
            {{ minhaPosicao.item.quantidade_placares_exatos }} exatos · {{ minhaPosicao.item.quantidade_classificados_corretos }} classificados
          </p>
        </div>
        <div class="shrink-0 text-right">
          <p class="text-3xl font-black leading-none text-violet-400">#{{ minhaPosicao.posicao }}</p>
          <p class="mt-1 text-xl font-black leading-none text-text tabular-nums">{{ inteiro(minhaPosicao.item.pontuacao_total) }}</p>
          <p class="text-[11px] text-text-muted">pontos</p>
        </div>
      </div>
    </section>

    <!-- Classificacao geral -->
    <section v-if="!carregando && ranking.length" class="space-y-3">
      <h2 class="px-1 text-base font-bold text-text">Classificacao Geral</h2>

      <div
        v-for="(item, i) in ranking"
        :key="item.id"
        class="overflow-hidden rounded-2xl border transition"
        :class="item.cupom.id === cupomDestaque ? 'border-primary/40 bg-primary/10' : 'border-border bg-bg-card hover:border-primary/20'"
      >
        <button type="button" class="flex w-full items-center gap-3 px-3 py-3 text-left" @click="alternar(item.cupom.id)">
          <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-sm font-bold tabular-nums" :class="classePosicao(i)">
            {{ i + 1 }}
          </div>
          <div class="h-10 w-10 shrink-0 overflow-hidden rounded-full border border-border">
            <img v-if="fotoDe(item)" :src="fotoDe(item)!" :alt="item.cupom.usuario.nome" class="h-full w-full object-cover" />
            <div v-else class="flex h-full w-full items-center justify-center bg-bg-input text-xs font-bold text-text-secondary">
              {{ iniciais(item.cupom.usuario.nome) }}
            </div>
          </div>
          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
              <strong class="truncate text-sm text-text">{{ item.cupom.usuario.nome }}</strong>
              <span v-if="item.cupom.id === cupomDestaque" class="rounded-full bg-primary px-2 py-0.5 text-[10px] font-bold text-bg">Voce</span>
            </div>
            <div class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[11px] text-text-muted">
              <span>Cupom {{ item.cupom.codigo }}</span>
              <span>{{ item.quantidade_placares_exatos }} exatos</span>
              <span>{{ item.quantidade_classificados_corretos }} classificados</span>
            </div>
          </div>
          <div class="text-right">
            <strong class="block text-xl font-black text-primary tabular-nums">{{ inteiro(item.pontuacao_total) }}</strong>
            <span class="text-[11px] text-text-muted">pts</span>
          </div>
          <svg class="h-5 w-5 shrink-0 text-text-muted transition-transform" :class="expandidoId === item.cupom.id ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
        </button>

        <div v-if="expandidoId === item.cupom.id" class="border-t border-border/70 px-4 py-3">
          <div v-if="carregandoId === item.cupom.id" class="py-2 text-center text-xs text-text-muted">Carregando pontuacoes...</div>
          <div v-else-if="!cache[item.cupom.id]?.length" class="py-2 text-center text-xs text-text-muted">Nenhuma pontuacao registrada ainda.</div>
          <div v-else class="space-y-2">
            <div v-for="evento in cache[item.cupom.id]" :key="evento.id" class="flex items-center justify-between gap-3 rounded-lg bg-bg-input px-3 py-2">
              <div class="min-w-0">
                <span class="block text-sm">{{ evento.descricao }}</span>
                <span v-if="descricaoJogo(evento)" class="mt-0.5 block truncate text-xs text-text-muted">{{ descricaoJogo(evento) }}</span>
              </div>
              <span class="shrink-0 text-sm font-bold text-primary">+{{ evento.pontos }} pts</span>
            </div>
          </div>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { requisicaoApi, urlAsset } from '../services/api'
import { usarTorneioStore } from '../stores/torneio'
import { usarAutenticacaoStore } from '../stores/autenticacao'
import { useEventosCupom } from '../composables/useEventosCupom'
import { useToast } from '../composables/useToast'
import type { MinhaPosicao, RankingItem, RespostaRanking } from '../tipos'

const rota = useRoute()
const torneioStore = usarTorneioStore()
const autenticacao = usarAutenticacaoStore()
const { mostrar } = useToast()
const { expandidoId, cache, carregandoId, alternar, descricaoJogo } = useEventosCupom()

const ranking = ref<RankingItem[]>([])
const minhaPosicao = ref<MinhaPosicao | null>(null)
const partidas = ref({ finalizadas: 0, total: 0 })
const carregando = ref(true)
const mostrarPontos = ref(false)

const cupomDestaque = computed(() => {
  const id = rota.query.cupom
  return id ? Number(id) : null
})

const regras = computed(() => (torneioStore.torneio?.regras_pontuacao ?? []).filter((r) => r.ativo))

const percentualPartidas = computed(() => {
  if (!partidas.value.total) return 0
  return Math.round((partidas.value.finalizadas / partidas.value.total) * 100)
})

const minhaFoto = computed(() => autenticacao.fotoUrl ?? urlAsset(minhaPosicao.value?.item.cupom.usuario.foto_url))

const podioOrdenado = computed(() => {
  const podio = ranking.value.slice(0, 3)
  const estilos = [
    { indice: 1, posicao: 2, anel: 'border-gold', cor: 'text-gold', fundoBadge: 'bg-gold' },
    { indice: 0, posicao: 1, anel: 'border-primary', cor: 'text-primary', fundoBadge: 'bg-primary' },
    { indice: 2, posicao: 3, anel: 'border-bronze', cor: 'text-bronze', fundoBadge: 'bg-bronze' },
  ]

  return estilos
    .map((estilo) => ({ ...estilo, item: podio[estilo.indice] }))
    .filter((estilo) => estilo.item)
})

function fotoDe(item: RankingItem) {
  return urlAsset(item.cupom.usuario.foto_url)
}

function classePosicao(indice: number) {
  if (indice === 0) return 'bg-primary text-bg'
  if (indice === 1) return 'bg-gold text-bg'
  if (indice === 2) return 'bg-bronze text-bg'
  return 'bg-bg-input text-text-muted'
}

function inteiro(valor: string) {
  return Math.round(Number(valor))
}

function iniciais(nome: string) {
  return nome
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((parte) => parte[0]?.toUpperCase() ?? '')
    .join('')
}

function primeiroNome(nome: string) {
  return nome.trim().split(/\s+/)[0] ?? nome
}

function avisarEmBreve() {
  mostrar('sucesso', 'Filtros de rodada e periodo em breve.')
}

async function compartilhar() {
  const url = window.location.href
  try {
    if (navigator.share) {
      await navigator.share({ title: 'Ranking do Bolao', url })
      return
    }
    await navigator.clipboard.writeText(url)
    mostrar('sucesso', 'Link do ranking copiado.')
  } catch {
    // Compartilhamento cancelado pelo usuario
  }
}

onMounted(async () => {
  try {
    await torneioStore.carregar()
    if (torneioStore.torneio) {
      const resposta = await requisicaoApi<RespostaRanking>(`/torneios/${torneioStore.torneio.id}/ranking`)
      ranking.value = resposta.ranking
      minhaPosicao.value = resposta.minha_posicao
      partidas.value = resposta.partidas
    }
  } catch {
    // Erro silencioso
  } finally {
    carregando.value = false
  }
})
</script>
