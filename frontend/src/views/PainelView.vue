<template>
  <div class="mx-auto max-w-5xl space-y-6">
    <!-- Header do painel -->
    <section class="rounded-2xl bg-gradient-to-r from-primary-dim/30 to-bg-card p-6 sm:p-8">
      <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <h1 class="text-xl font-bold sm:text-2xl">Ola, {{ autenticacao.nome }}</h1>
          <p class="mt-1 text-sm text-text-secondary">
            Cada cupom representa um conjunto independente de apostas.
          </p>
        </div>
        <RouterLink
          to="/checkout"
          class="inline-block rounded-xl bg-primary px-6 py-2.5 text-center font-semibold text-bg transition hover:bg-primary-hover"
        >
          Comprar novo cupom
        </RouterLink>
      </div>
    </section>

    <!-- Meus Bolões section (reference style) -->
    <section class="rounded-2xl border border-border bg-bg-card">
      <!-- Section header -->
      <div class="flex items-center justify-between px-6 py-4">
        <div class="flex items-center gap-3">
          <div class="flex h-10 w-10 items-center justify-center rounded-full bg-primary/20">
            <svg class="h-5 w-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9 0a3 3 0 013 3h-15a3 3 0 013-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 01-.982-3.172M9.497 14.25a7.454 7.454 0 00.981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 007.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M18.75 4.236c.982.143 1.954.317 2.916.52A6.003 6.003 0 0016.27 9.728M18.75 4.236V4.5c0 2.108-.966 3.99-2.48 5.228m0 0a6.023 6.023 0 01-2.27.308m4.75 0a6.023 6.023 0 002.27.308" />
            </svg>
          </div>
          <div>
            <h2 class="text-base font-bold">Meus Cupons</h2>
            <p class="text-xs text-text-muted">
              {{ carregando ? '...' : cupons.length === 0 ? 'Nenhum cupom' : `${cupons.length} cupom${cupons.length > 1 ? 's' : ''} ativo${cupons.length > 1 ? 's' : ''}` }}
            </p>
          </div>
        </div>
        <svg class="h-5 w-5 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5" />
        </svg>
      </div>

      <!-- Content -->
      <div class="px-6 pb-6">
        <!-- Loading -->
        <div v-if="carregando" class="space-y-4">
          <SkeletonCard />
          <SkeletonCard />
        </div>

        <!-- Empty state -->
        <div
          v-else-if="!cupons.length"
          class="rounded-2xl border border-dashed border-border bg-bg px-8 py-16 text-center"
        >
          <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-primary/10">
            <svg class="h-8 w-8 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
          </div>
          <h3 class="mt-4 text-lg font-bold">Nenhum cupom ainda</h3>
          <p class="mx-auto mt-2 max-w-md text-sm text-text-secondary">
            Compre seu primeiro cupom e comece a fazer seus palpites para o bolao.
          </p>
          <RouterLink
            to="/checkout"
            class="mt-6 inline-flex items-center gap-2 rounded-xl bg-primary px-8 py-3 text-base font-bold text-bg transition hover:bg-primary-hover hover:shadow-lg hover:shadow-primary/20"
          >
            Comprar primeiro cupom
          </RouterLink>
        </div>

        <!-- Cards grid: 1 col mobile, 2 col desktop -->
        <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <CupomCard v-for="cupom in cupons" :key="cupom.id" :cupom="cupom" />
        </div>
      </div>
    </section>

    <!-- Info torneio -->
    <section>
      <InfoTorneio />
    </section>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { requisicaoApi } from '../services/api'
import { usarAutenticacaoStore } from '../stores/autenticacao'
import { usarTorneioStore } from '../stores/torneio'
import type { Cupom } from '../tipos'
import SkeletonCard from '../components/SkeletonCard.vue'
import CupomCard from '../components/CupomCard.vue'
import InfoTorneio from '../components/InfoTorneio.vue'

const autenticacao = usarAutenticacaoStore()
const torneioStore = usarTorneioStore()
const cupons = ref<Cupom[]>([])
const carregando = ref(true)

async function carregarCupons() {
  carregando.value = true
  try {
    const resposta = await requisicaoApi<{ cupons: Cupom[] }>('/cupons')
    cupons.value = resposta.cupons
  } catch {
    // Erro silencioso
  } finally {
    carregando.value = false
  }
}

onMounted(() => {
  carregarCupons()
  torneioStore.carregar()
})
</script>
