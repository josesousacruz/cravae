<template>
  <div class="mx-auto max-w-5xl space-y-6">
    <!-- Header -->
    <div>
      <h1 class="text-2xl font-bold">Ranking</h1>
      <p class="mt-1 text-sm text-text-secondary">
        Classificacao por cupom do torneio ativo.
      </p>
    </div>

    <!-- Skeleton loading -->
    <div v-if="carregando" class="rounded-2xl border border-border bg-bg-card overflow-hidden">
      <div class="bg-bg-input px-4 py-3">
        <div class="flex gap-4">
          <div class="h-3 w-8 animate-pulse rounded bg-border"></div>
          <div class="h-3 w-20 animate-pulse rounded bg-border"></div>
          <div class="h-3 w-16 animate-pulse rounded bg-border"></div>
          <div class="h-3 w-12 animate-pulse rounded bg-border"></div>
          <div class="h-3 w-12 animate-pulse rounded bg-border"></div>
        </div>
      </div>
      <div class="divide-y divide-border/50">
        <div v-for="n in 5" :key="n" class="flex items-center gap-4 px-4 py-3">
          <div class="h-8 w-8 animate-pulse rounded-full bg-bg-input"></div>
          <div class="flex-1 space-y-1">
            <div class="h-4 w-28 animate-pulse rounded bg-bg-input"></div>
            <div class="h-3 w-20 animate-pulse rounded bg-bg-input"></div>
          </div>
          <div class="h-5 w-10 animate-pulse rounded bg-bg-input"></div>
        </div>
      </div>
    </div>

    <!-- Empty state -->
    <div
      v-else-if="!ranking.length"
      class="rounded-2xl border border-border bg-bg-card py-12 text-center"
    >
      <p class="text-text-muted">
        Nenhum resultado disponivel. O ranking sera atualizado conforme os resultados dos jogos forem lancados.
      </p>
    </div>

    <!-- Tabela ranking -->
    <div v-else class="rounded-2xl border border-border bg-bg-card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full">
          <thead>
            <tr class="bg-bg-input text-xs uppercase tracking-wider text-text-muted">
              <th class="px-4 py-3 text-left">#</th>
              <th class="px-4 py-3 text-left">Cupom</th>
              <th class="px-4 py-3 text-left">Usuario</th>
              <th class="px-4 py-3 text-right">Pontos</th>
              <th class="px-4 py-3 text-right">Exatos</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="(item, i) in ranking"
              :key="item.id"
              class="border-t border-border/50 transition-colors hover:bg-bg-card-hover"
              :class="item.cupom.id === cupomDestaque ? 'bg-primary/10 border-l-2 border-l-primary' : ''"
            >
              <td class="px-4 py-3">
                <span
                  class="inline-flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold"
                  :class="{
                    'text-gold': i === 0,
                    'text-silver': i === 1,
                    'text-bronze': i === 2,
                    'text-text-muted': i > 2,
                  }"
                >
                  {{ i + 1 }}
                </span>
              </td>
              <td class="px-4 py-3 text-sm font-mono text-text-muted">{{ item.cupom.codigo }}</td>
              <td class="px-4 py-3 text-sm font-medium">{{ item.cupom.usuario.nome }}</td>
              <td class="px-4 py-3 text-right font-bold text-primary">{{ item.pontuacao_total }}</td>
              <td class="px-4 py-3 text-right text-sm">{{ item.quantidade_placares_exatos }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
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
