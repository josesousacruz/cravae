<template>
  <div class="mx-auto max-w-6xl space-y-6">
    <section class="relative overflow-hidden rounded-[32px] border border-primary/20 bg-[radial-gradient(circle_at_top,#123225,transparent_45%),linear-gradient(180deg,#111513,#0a0c0b)] p-6 shadow-[0_0_0_1px_rgba(16,185,129,0.08)] sm:p-8">
      <div class="absolute inset-0 bg-[linear-gradient(135deg,transparent,rgba(16,185,129,0.06),transparent)]" />
      <div class="relative">
        <div class="inline-flex items-center gap-2 rounded-full border border-primary/30 bg-primary/10 px-4 py-2 text-sm font-semibold text-primary">
          Ranking
        </div>
        <div class="mt-4 flex flex-wrap items-end justify-between gap-4">
          <div>
            <h1 class="text-2xl font-bold text-text sm:text-3xl">Classificacao geral</h1>
            <p class="mt-2 max-w-2xl text-sm text-text-secondary">
              O podio do bolao mostra os tres melhores cupons do torneio ativo e a lista completa continua logo abaixo.
            </p>
          </div>
          <span class="rounded-full border border-border bg-bg-card/60 px-3 py-1 text-xs text-text-muted">
            {{ ranking.length }} participante{{ ranking.length === 1 ? '' : 's' }}
          </span>
        </div>

        <div v-if="carregando" class="mt-8 grid gap-4 lg:grid-cols-[1fr_1.15fr_1fr]">
          <div v-for="item in 3" :key="item" class="h-48 animate-pulse rounded-3xl border border-border/50 bg-bg-card/40" />
        </div>

        <div v-else-if="podio.length" class="mt-8 grid gap-4 lg:grid-cols-[1fr_1.15fr_1fr] lg:items-end">
          <article
            v-for="item in podioOrdenado"
            :key="item.ranking.id"
            class="rounded-3xl border p-5 text-center backdrop-blur"
            :class="item.destaque"
          >
            <span class="inline-flex h-10 w-10 items-center justify-center rounded-full border text-sm font-bold" :class="item.badge">
              {{ item.posicao }}
            </span>
            <div class="mx-auto mt-4 flex h-24 w-24 items-center justify-center rounded-full border text-3xl text-primary" :class="item.badge">
              {{ iniciais(item.ranking.cupom.usuario.nome) }}
            </div>
            <p class="mt-4 text-lg font-bold text-text">{{ item.ranking.cupom.usuario.nome }}</p>
            <p class="mt-1 text-xs uppercase tracking-[0.2em] text-text-muted">{{ item.rotulo }}</p>
            <p class="mt-4 text-4xl font-black text-primary">{{ item.ranking.pontuacao_total }}</p>
            <p class="text-xs text-text-muted">pontos</p>
            <div class="mt-4 inline-flex rounded-full border border-border bg-bg-card/70 px-3 py-1 text-xs text-text-secondary">
              Cupom {{ item.ranking.cupom.codigo }}
            </div>
          </article>
        </div>

        <div v-else-if="!carregando" class="mt-8 rounded-3xl border border-border bg-bg-card/60 py-12 text-center">
          <p class="text-text-muted">Nenhum resultado disponivel. O ranking sera atualizado conforme os resultados forem lancados.</p>
        </div>
      </div>
    </section>

    <section v-if="!carregando && ranking.length" class="rounded-3xl border border-border bg-bg-card p-4 sm:p-5">
      <div class="mb-4 flex items-center justify-between gap-3">
        <div>
          <h2 class="text-lg font-bold">Classificacao geral</h2>
          <p class="text-sm text-text-secondary">Lista completa de cupons ordenada pelos criterios oficiais.</p>
        </div>
      </div>

      <div class="space-y-3">
        <article
          v-for="(item, i) in ranking"
          :key="item.id"
          class="flex items-center gap-3 rounded-2xl border border-border bg-bg-input/60 px-4 py-3 transition"
          :class="item.cupom.id === cupomDestaque ? 'border-primary/40 bg-primary/10' : 'hover:border-primary/20'"
        >
          <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border text-sm font-bold" :class="classePosicao(i)">
            {{ i + 1 }}
          </div>
          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
              <strong class="truncate text-sm">{{ item.cupom.usuario.nome }}</strong>
              <span v-if="item.cupom.id === cupomDestaque" class="rounded-full bg-primary px-2 py-0.5 text-[10px] font-bold text-bg">Voce</span>
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
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { requisicaoApi } from '../services/api'
import { usarTorneioStore } from '../stores/torneio'
import type { RankingItem } from '../tipos'

const rota = useRoute()
const torneioStore = usarTorneioStore()

const ranking = ref<RankingItem[]>([])
const carregando = ref(true)

const cupomDestaque = computed(() => {
  const id = rota.query.cupom
  return id ? Number(id) : null
})

const podio = computed(() => ranking.value.slice(0, 3))
const podioOrdenado = computed(() => {
  const mapa = [
    { indice: 1, posicao: 2, rotulo: 'Segundo lugar', destaque: 'border-silver/40 bg-silver/10', badge: 'border-silver/40 text-silver lg:h-10 lg:w-10' },
    { indice: 0, posicao: 1, rotulo: 'Primeiro lugar', destaque: 'border-primary/40 bg-primary/10 shadow-[0_0_40px_rgba(16,185,129,0.16)] lg:-translate-y-4', badge: 'border-primary/40 text-primary lg:h-12 lg:w-12' },
    { indice: 2, posicao: 3, rotulo: 'Terceiro lugar', destaque: 'border-bronze/40 bg-bronze/10', badge: 'border-bronze/40 text-bronze lg:h-10 lg:w-10' },
  ]

  return mapa
    .map((item) => ({
      ...item,
      ranking: podio.value[item.indice],
    }))
    .filter((item) => item.ranking)
})

function classePosicao(indice: number) {
  if (indice === 0) return 'border-primary/40 bg-primary/10 text-primary'
  if (indice === 1) return 'border-silver/40 bg-silver/10 text-silver'
  if (indice === 2) return 'border-bronze/40 bg-bronze/10 text-bronze'
  return 'border-border bg-bg-card text-text-muted'
}

function iniciais(nome: string) {
  return nome
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((parte) => parte[0]?.toUpperCase() ?? '')
    .join('')
}

onMounted(async () => {
  try {
    await torneioStore.carregar()
    if (torneioStore.torneio) {
      const resposta = await requisicaoApi<{ ranking: RankingItem[] }>(
        `/torneios/${torneioStore.torneio.id}/ranking`,
      )
      ranking.value = resposta.ranking
    }
  } catch {
    // Erro silencioso
  } finally {
    carregando.value = false
  }
})
</script>
