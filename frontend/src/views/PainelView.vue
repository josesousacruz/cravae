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

    <!-- Lista de cupons -->
    <section>
      <!-- Loading -->
      <div v-if="carregando" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <SkeletonCard />
        <SkeletonCard />
      </div>

      <!-- Empty state -->
      <div
        v-else-if="!cupons.length"
        class="rounded-2xl border border-border bg-bg-card px-8 py-16 text-center"
      >
        <h2 class="text-xl font-bold">Nenhum cupom ainda</h2>
        <p class="mx-auto mt-2 max-w-md text-text-secondary">
          Compre seu primeiro cupom e comece a fazer seus palpites para o bolao.
        </p>
        <RouterLink
          to="/checkout"
          class="mt-6 inline-block rounded-xl bg-primary px-8 py-3 text-lg font-semibold text-bg transition hover:bg-primary-hover"
        >
          Comprar primeiro cupom
        </RouterLink>
      </div>

      <!-- Grid de cupons -->
      <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <CupomCard v-for="cupom in cupons" :key="cupom.id" :cupom="cupom" />
      </div>
    </section>

    <!-- Info torneio -->
    <section class="mt-8">
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
    // Erro silencioso - cupons ficam vazios
  } finally {
    carregando.value = false
  }
}

onMounted(() => {
  carregarCupons()
  torneioStore.carregar()
})
</script>
