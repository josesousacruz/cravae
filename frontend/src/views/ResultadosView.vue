<template>
  <div class="mx-auto max-w-3xl space-y-6">
    <header>
      <h1 class="text-2xl font-black text-text">Resultados</h1>
      <p class="mt-1 text-sm text-text-secondary">Seus pontos e o histórico de palpites, jogo a jogo.</p>
    </header>

    <div v-if="carregando" class="h-48 animate-pulse rounded-2xl border border-border bg-bg-card" />

    <template v-else-if="cupom">
      <!-- Meus pontos -->
      <section class="rounded-2xl border border-border bg-bg-card p-5">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
          <h2 class="text-lg font-bold">Meus pontos</h2>
          <span class="rounded-full bg-primary-dim px-3 py-1 text-sm font-black text-primary tabular-nums">
            {{ totalPontos }} pts
          </span>
        </div>
        <div v-if="cupom.eventos_pontuacao?.length" class="space-y-2">
          <div v-for="evento in cupom.eventos_pontuacao" :key="evento.id" class="flex items-center justify-between gap-3 rounded-lg bg-bg-input px-3.5 py-2.5">
            <div class="min-w-0">
              <span class="block text-sm">{{ evento.descricao }}</span>
              <span v-if="descricaoJogo(evento)" class="mt-0.5 block truncate text-xs text-text-muted">{{ descricaoJogo(evento) }}</span>
            </div>
            <span class="shrink-0 text-sm font-bold text-primary">+{{ evento.pontos }} pts</span>
          </div>
        </div>
        <p v-else class="py-6 text-center text-sm text-text-muted">
          Nenhum ponto ainda — eles aparecem aqui assim que os resultados da rodada entram.
        </p>
      </section>

      <!-- Histórico de palpites -->
      <section>
        <HistoricoPalpites :cupom-id="cupom.id" />
      </section>
    </template>

    <div v-else class="rounded-2xl border border-border bg-bg-card py-12 text-center text-sm text-text-muted">
      Não foi possível carregar seu cupom.
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import HistoricoPalpites from '../components/HistoricoPalpites.vue'
import { useEventosCupom } from '../composables/useEventosCupom'
import { requisicaoApi } from '../services/api'
import { usarMeuCupomStore } from '../stores/meuCupom'
import type { Cupom } from '../tipos'

const meuCupomStore = usarMeuCupomStore()
const { descricaoJogo } = useEventosCupom()

const cupom = ref<Cupom | null>(null)
const carregando = ref(true)

const totalPontos = computed(() => {
  const total = Number(cupom.value?.pontuacao?.pontuacao_total ?? 0)
  return Number.isFinite(total) ? Math.round(total) : 0
})

onMounted(async () => {
  try {
    const base = await meuCupomStore.carregar()
    if (base) {
      // Recarrega com eventos de pontuação (payload completo do cupom).
      const resposta = await requisicaoApi<{ cupom: Cupom }>(`/cupons/${base.id}`)
      cupom.value = resposta.cupom
    }
  } finally {
    carregando.value = false
  }
})
</script>
