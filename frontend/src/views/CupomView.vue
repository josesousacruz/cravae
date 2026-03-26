<template>
  <div class="mx-auto max-w-5xl">
    <!-- Loading -->
    <div v-if="carregando" class="flex items-center justify-center py-20">
      <span class="text-text-muted">Carregando...</span>
    </div>

    <div v-else-if="cupom" class="space-y-6">
      <!-- Breadcrumb -->
      <nav class="text-sm text-text-muted">
        <RouterLink to="/painel" class="hover:text-text-secondary transition">Meus Cupons</RouterLink>
        <span class="mx-2">&gt;</span>
        <span>Cupom {{ cupom.codigo }}</span>
      </nav>

      <!-- Header do cupom -->
      <div class="flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-xl font-bold">Cupom {{ cupom.codigo }}</h1>
        <span
          class="rounded-full px-2 py-0.5 text-xs uppercase tracking-wider"
          :class="
            cupom.status === 'ativo'
              ? 'bg-primary/20 text-primary'
              : 'bg-warning/20 text-warning'
          "
        >
          {{ cupom.status }}
        </span>
      </div>

      <!-- Tabs -->
      <div class="flex overflow-x-auto border-b border-border">
        <button
          v-for="tab in tabs"
          :key="tab.id"
          @click="tabAtiva = tab.id"
          class="whitespace-nowrap px-4 py-3 text-sm transition cursor-pointer"
          :class="
            tabAtiva === tab.id
              ? 'border-b-2 border-primary text-primary font-medium'
              : 'text-text-muted hover:text-text-secondary'
          "
        >
          {{ tab.nome }}
        </button>
      </div>

      <!-- Tab Palpites -->
      <section v-if="tabAtiva === 'palpites'">
        <!-- Sub-tabs -->
        <div class="flex gap-2 mt-4 mb-6 overflow-x-auto">
          <button
            v-for="sub in subTabs"
            :key="sub.id"
            @click="subTabAtiva = sub.id"
            class="px-3 py-1.5 rounded-lg text-xs font-medium whitespace-nowrap transition cursor-pointer"
            :class="
              subTabAtiva === sub.id
                ? 'bg-primary/20 text-primary'
                : 'bg-bg-input text-text-muted hover:text-text-secondary'
            "
          >
            {{ sub.nome }}
          </button>
        </div>

        <div class="rounded-2xl border border-border bg-bg-card py-12 text-center">
          <p class="text-text-muted">
            Palpites para esta secao estarao disponiveis na Phase 3.
          </p>
        </div>
      </section>

      <!-- Tab Ranking -->
      <section v-if="tabAtiva === 'ranking'">
        <div v-if="carregandoRanking" class="rounded-2xl border border-border bg-bg-card overflow-hidden">
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

        <div
          v-else-if="!ranking.length"
          class="rounded-2xl border border-border bg-bg-card py-8 text-center"
        >
          <p class="text-text-muted">Nenhum resultado disponivel ainda.</p>
        </div>

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
                  :class="item.cupom.id === cupom.id ? 'bg-primary/10 border-l-2 border-l-primary' : ''"
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
      </section>

      <!-- Tab Meus Resultados -->
      <section v-if="tabAtiva === 'resultados'">
        <div class="rounded-2xl border border-border bg-bg-card py-12 text-center">
          <p class="text-text-muted">
            Historico de pontos deste cupom estara disponivel na Phase 4.
          </p>
        </div>
      </section>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { requisicaoApi } from '../services/api'
import { usarTorneioStore } from '../stores/torneio'
import type { Cupom, RankingItem } from '../tipos'

const rota = useRoute()
const torneioStore = usarTorneioStore()

const cupom = ref<Cupom | null>(null)
const ranking = ref<RankingItem[]>([])
const carregando = ref(true)
const carregandoRanking = ref(false)

const tabAtiva = ref<'palpites' | 'ranking' | 'resultados'>('palpites')
const subTabAtiva = ref<'grupos' | 'classificacao' | 'mata-mata' | 'finais'>('grupos')

const tabs = [
  { id: 'palpites' as const, nome: 'Palpites' },
  { id: 'ranking' as const, nome: 'Ranking' },
  { id: 'resultados' as const, nome: 'Meus Resultados' },
]

const subTabs = [
  { id: 'grupos' as const, nome: 'Grupos' },
  { id: 'classificacao' as const, nome: 'Classificacao' },
  { id: 'mata-mata' as const, nome: 'Mata-Mata' },
  { id: 'finais' as const, nome: 'Finais' },
]

async function carregarCupom() {
  carregando.value = true
  try {
    const resposta = await requisicaoApi<{ cupom: Cupom }>(`/cupons/${rota.params.id}`)
    cupom.value = resposta.cupom
  } catch {
    // Erro silencioso
  } finally {
    carregando.value = false
  }
}

async function carregarRanking() {
  if (!torneioStore.torneio) return
  carregandoRanking.value = true
  try {
    const resposta = await requisicaoApi<{ ranking: RankingItem[] }>(
      `/torneios/${torneioStore.torneio.id}/ranking`,
    )
    ranking.value = resposta.ranking
  } catch {
    // Erro silencioso
  } finally {
    carregandoRanking.value = false
  }
}

onMounted(async () => {
  await Promise.all([carregarCupom(), torneioStore.carregar()])
  carregarRanking()
})
</script>
