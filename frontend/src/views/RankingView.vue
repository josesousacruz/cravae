<template>
  <div class="space-y-6">
    <section class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-primary-dim to-bg-card p-6 sm:p-8">
      <div class="relative z-10">
        <span class="inline-block rounded-full bg-primary/20 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-primary">
          Ranking geral
        </span>
        <h1 class="mt-3 text-2xl font-bold">Classificacao por cupom</h1>
        <p class="mt-1 text-text-secondary">Acompanhe a pontuacao de todos os participantes em tempo real.</p>
      </div>
    </section>

    <section class="rounded-2xl border border-border bg-bg-card">
      <div v-if="carregando" class="px-5 py-10 text-center text-text-muted">Carregando ranking...</div>

      <div v-else-if="!ranking.length" class="px-5 py-10 text-center text-text-muted">
        Nenhum cupom pontuado ainda. Faca seus palpites para aparecer no ranking.
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full">
          <thead>
            <tr class="border-b border-border text-left text-xs uppercase tracking-wider text-text-muted">
              <th class="px-5 py-3.5">#</th>
              <th class="px-5 py-3.5">Participante</th>
              <th class="px-5 py-3.5 text-right">Pontos</th>
              <th class="hidden px-5 py-3.5 text-right sm:table-cell">Exatos</th>
              <th class="hidden px-5 py-3.5 text-right sm:table-cell">Classificados</th>
              <th class="hidden px-5 py-3.5 text-right md:table-cell">Finais</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="(item, i) in ranking"
              :key="item.id"
              class="border-b border-border/50 transition-colors hover:bg-bg-card-hover"
            >
              <td class="px-5 py-3.5">
                <span
                  class="flex h-8 w-8 items-center justify-center rounded-full text-sm font-bold"
                  :class="{
                    'bg-gold/20 text-gold': i === 0,
                    'bg-silver/20 text-silver': i === 1,
                    'bg-bronze/20 text-bronze': i === 2,
                    'bg-bg-input text-text-muted': i > 2,
                  }"
                >
                  {{ i + 1 }}
                </span>
              </td>
              <td class="px-5 py-3.5">
                <p class="font-medium">{{ item.cupom.usuario.nome }}</p>
                <p class="text-xs text-text-muted">{{ item.cupom.codigo }}</p>
              </td>
              <td class="px-5 py-3.5 text-right">
                <span class="text-lg font-bold text-primary">{{ item.pontuacao_total }}</span>
              </td>
              <td class="hidden px-5 py-3.5 text-right sm:table-cell">{{ item.quantidade_placares_exatos }}</td>
              <td class="hidden px-5 py-3.5 text-right sm:table-cell">{{ item.quantidade_classificados_corretos }}</td>
              <td class="hidden px-5 py-3.5 text-right md:table-cell">{{ item.quantidade_palpites_finais_corretos }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { requisicaoApi } from '../services/api'
import type { RankingItem, Torneio } from '../tipos'

const ranking = ref<RankingItem[]>([])
const carregando = ref(true)

onMounted(async () => {
  try {
    const respostaTorneio = await requisicaoApi<{ torneio: Torneio }>('/torneio')
    const respostaRanking = await requisicaoApi<{ ranking: RankingItem[] }>(
      `/torneios/${respostaTorneio.torneio.id}/ranking`,
    )
    ranking.value = respostaRanking.ranking
  } catch {
    // torneio nao encontrado
  } finally {
    carregando.value = false
  }
})
</script>
