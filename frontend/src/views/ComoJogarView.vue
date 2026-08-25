<template>
  <div class="mx-auto max-w-3xl space-y-6">
    <header>
      <h1 class="text-2xl font-black text-text">Entenda o jogo</h1>
      <p class="mt-1 text-sm text-text-secondary">Como o Cravaê funciona, do palpite ao prêmio.</p>
    </header>

    <!-- Como funciona -->
    <section class="space-y-3">
      <article v-for="(passo, i) in passos" :key="passo.titulo" class="flex gap-4 rounded-2xl border border-border bg-bg-card p-5">
        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary-dim text-sm font-black text-primary">{{ i + 1 }}</span>
        <div>
          <h2 class="text-base font-bold text-text">{{ passo.titulo }}</h2>
          <p class="mt-1 text-sm leading-6 text-text-secondary">{{ passo.descricao }}</p>
        </div>
      </article>
    </section>

    <!-- Pontuação -->
    <section class="rounded-2xl border border-border bg-bg-card p-5">
      <h2 class="text-sm font-black uppercase tracking-wide text-text">Como pontuar</h2>
      <p class="mt-1 text-sm text-text-secondary">As regras valem para todos e ficam registradas no sistema.</p>
      <ul v-if="regras.length" class="mt-4 space-y-2">
        <li v-for="regra in regras" :key="regra.id" class="flex items-center justify-between gap-3 rounded-xl bg-bg px-4 py-2.5 text-sm">
          <span class="text-text">{{ regra.nome }}</span>
          <span class="shrink-0 font-black text-primary">+{{ regra.pontos }} pts</span>
        </li>
      </ul>
      <p v-else class="mt-4 text-sm text-text-muted">As regras do campeonato aparecem aqui assim que o torneio carrega.</p>
    </section>

    <!-- Fechamento e prêmio -->
    <section class="grid gap-3 sm:grid-cols-2">
      <article class="rounded-2xl border border-border bg-bg-card p-5">
        <h2 class="text-sm font-black uppercase tracking-wide text-text">⏱ Fechamento</h2>
        <p class="mt-2 text-sm leading-6 text-text-secondary">
          Cada jogo fecha no seu horário de início. Deu a bola, o palpite daquele jogo trava — os outros da rodada seguem abertos até começarem.
        </p>
      </article>
      <article class="rounded-2xl border border-border bg-bg-card p-5">
        <h2 class="text-sm font-black uppercase tracking-wide text-text">🏆 Prêmio do mês</h2>
        <p class="mt-2 text-sm leading-6 text-text-secondary">
          Jogar é grátis: você palpita, pontua e aparece no ranking. Assinantes concorrem ao prêmio de cada mês — quem está com a assinatura ativa no mês disputa com todos os pontos daquele mês.
        </p>
        <RouterLink to="/assinatura" class="mt-3 inline-flex text-sm font-bold text-primary underline-offset-2 hover:underline">
          Conhecer a assinatura →
        </RouterLink>
      </article>
    </section>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { usarTorneioStore } from '../stores/torneio'

const torneioStore = usarTorneioStore()

const passos = [
  { titulo: 'Crave o placar', descricao: 'Em cada jogo da rodada do Brasileirão, palpite o placar exato usando os controles de mais e menos.' },
  { titulo: 'Crave quem marca', descricao: 'Para cada gol do seu palpite, aponte o autor escolhendo do elenco real do clube. Acertar o goleador vale bônus — e o placar exato com todos os autores vale o bônus máximo.' },
  { titulo: 'Acompanhe os pontos', descricao: 'Quando os resultados entram, sua pontuação é recalculada na hora e cada acerto vira um evento registrado no seu extrato.' },
  { titulo: 'Dispute o ranking', descricao: 'O ranking do mês define quem leva o prêmio entre os assinantes; o ranking geral conta a temporada inteira.' },
]

const regras = computed(() => (torneioStore.torneio?.regras_pontuacao ?? []).filter((r) => r.ativo))

onMounted(() => {
  torneioStore.carregar()
})
</script>
