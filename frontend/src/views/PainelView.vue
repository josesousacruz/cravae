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
        <span
          class="inline-flex cursor-not-allowed items-center gap-2 rounded-xl border border-border bg-bg-input px-6 py-2.5 text-center text-sm font-semibold text-text-muted"
          title="A compra de cupons esta encerrada ate o fim do campeonato"
        >
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" /></svg>
          Compra de cupons encerrada
        </span>
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
            A compra de cupons esta encerrada ate o fim do campeonato.
          </p>
        </div>

        <!-- Cards carousel -->
        <div v-else class="relative group/carousel">
          <!-- Left arrow -->
          <button
            v-if="cupons.length > 1"
            @click="scrollCarousel(-1)"
            class="absolute -left-3 top-1/2 z-10 -translate-y-1/2 flex h-8 w-8 items-center justify-center rounded-full bg-bg-card border border-border text-text-muted shadow-lg transition opacity-0 group-hover/carousel:opacity-100 hover:bg-primary hover:text-bg hover:border-primary"
          >
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" /></svg>
          </button>

          <!-- Cards -->
          <div ref="carouselRef" class="flex snap-x snap-mandatory gap-4 overflow-x-auto scrollbar-none pb-2 scroll-smooth">
            <CupomCard v-for="cupom in cupons" :key="cupom.id" :cupom="cupom" class="w-72 shrink-0 snap-start sm:w-80" />
          </div>

          <!-- Right arrow -->
          <button
            v-if="cupons.length > 1"
            @click="scrollCarousel(1)"
            class="absolute -right-3 top-1/2 z-10 -translate-y-1/2 flex h-8 w-8 items-center justify-center rounded-full bg-bg-card border border-border text-text-muted shadow-lg transition opacity-0 group-hover/carousel:opacity-100 hover:bg-primary hover:text-bg hover:border-primary"
          >
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
          </button>

          <!-- Dots indicator -->
          <div v-if="cupons.length > 1" class="mt-3 flex justify-center gap-1.5">
            <span
              v-for="(_, i) in cupons"
              :key="i"
              class="h-1.5 rounded-full transition-all"
              :class="i === cupomAtualIndex ? 'w-4 bg-primary' : 'w-1.5 bg-border'"
            />
          </div>
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
import { onMounted, onUnmounted, ref } from 'vue'
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
const carouselRef = ref<HTMLElement | null>(null)
const cupomAtualIndex = ref(0)

function scrollCarousel(direction: number) {
  if (!carouselRef.value) return
  const cardWidth = carouselRef.value.querySelector(':scope > *')?.clientWidth ?? 320
  carouselRef.value.scrollBy({ left: direction * (cardWidth + 16), behavior: 'smooth' })
}

function onCarouselScroll() {
  if (!carouselRef.value) return
  const el = carouselRef.value
  const cardWidth = el.querySelector(':scope > *')?.clientWidth ?? 320
  cupomAtualIndex.value = Math.round(el.scrollLeft / (cardWidth + 16))
}

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
  carouselRef.value?.addEventListener('scroll', onCarouselScroll, { passive: true })
})

onUnmounted(() => {
  carouselRef.value?.removeEventListener('scroll', onCarouselScroll)
})
</script>
