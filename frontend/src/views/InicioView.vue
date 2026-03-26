<template>
  <div class="space-y-20">
    <!-- Hero -->
    <section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-primary/10 via-bg-card to-bg-card px-6 py-16 text-center sm:px-12 sm:py-24">
      <div class="absolute inset-0 bg-[radial-gradient(circle_at_30%_20%,rgba(16,185,129,0.15),transparent_50%)]" />
      <div class="absolute inset-0 bg-[radial-gradient(circle_at_70%_80%,rgba(16,185,129,0.08),transparent_50%)]" />
      <div class="relative z-10">
        <span class="inline-block rounded-full border border-primary/30 bg-primary/10 px-4 py-1.5 text-xs font-semibold uppercase tracking-widest text-primary">
          Copa do Mundo 2026
        </span>
        <h1 class="mx-auto mt-6 max-w-3xl text-3xl font-extrabold leading-tight sm:text-5xl">
          Cupom de Futebol
          <span class="text-primary">Online</span>
        </h1>
        <p class="mx-auto mt-4 max-w-xl text-text-secondary sm:text-lg">
          Compre seus cupons, faca seus palpites e acompanhe o ranking em tempo real.
          Cada cupom e independente com pontuacao automatica.
        </p>
        <div class="mt-8 flex flex-wrap items-center justify-center gap-4">
          <RouterLink
            to="/cadastro"
            class="rounded-xl bg-primary px-7 py-3 text-sm font-bold text-bg shadow-lg shadow-primary/25 transition-all hover:bg-primary-hover hover:shadow-primary/40"
          >
            Comecar agora
          </RouterLink>
          <RouterLink
            to="/ranking"
            class="rounded-xl border border-border bg-bg-card/60 px-7 py-3 text-sm font-bold text-text-secondary backdrop-blur transition-colors hover:border-primary/50 hover:text-primary"
          >
            Ver ranking
          </RouterLink>
        </div>
      </div>
    </section>

    <!-- Como funciona -->
    <section>
      <div class="mb-10 text-center">
        <h2 class="text-2xl font-extrabold sm:text-3xl">
          Como funciona o <span class="text-primary">Inter World Cup</span>
        </h2>
        <p class="mt-2 text-text-secondary">Em apenas 3 passos voce ja esta participando</p>
      </div>
      <div class="grid gap-6 sm:grid-cols-3">
        <div
          v-for="(passo, i) in passos"
          :key="i"
          class="group rounded-2xl border border-border bg-bg-card p-6 transition-colors hover:border-primary/40"
        >
          <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-primary/15 text-lg font-bold text-primary transition-colors group-hover:bg-primary group-hover:text-bg">
            {{ i + 1 }}
          </span>
          <h3 class="mt-4 text-lg font-bold">{{ passo.titulo }}</h3>
          <p class="mt-2 text-sm leading-relaxed text-text-secondary">{{ passo.descricao }}</p>
        </div>
      </div>
    </section>

    <!-- Jogos e Ranking ao vivo -->
    <section v-if="torneio" class="grid gap-6 lg:grid-cols-5">
      <!-- Proximos Jogos -->
      <div class="rounded-2xl border border-border bg-bg-card p-5 lg:col-span-3">
        <div class="mb-5 flex items-center justify-between">
          <h2 class="text-lg font-bold">Jogos do Torneio</h2>
          <span class="rounded-full bg-primary/15 px-3 py-1 text-xs font-semibold text-primary">
            {{ torneio.jogos.length }} jogos
          </span>
        </div>
        <div class="space-y-2">
          <div
            v-for="jogo in torneio.jogos"
            :key="jogo.id"
            class="flex items-center gap-3 rounded-xl bg-bg-input px-4 py-3 transition-colors hover:bg-bg-card-hover"
          >
            <span class="w-20 text-[11px] font-medium text-text-muted">{{ jogo.fase.nome }}</span>
            <div class="flex flex-1 items-center justify-center gap-3">
              <div class="flex items-center gap-2">
                <span class="text-sm font-bold">{{ jogo.selecao_mandante.sigla }}</span>
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-bg text-sm font-bold">
                  {{ jogo.resultado?.placar_mandante ?? '-' }}
                </span>
              </div>
              <span class="text-xs font-medium text-text-muted">VS</span>
              <div class="flex items-center gap-2">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-bg text-sm font-bold">
                  {{ jogo.resultado?.placar_visitante ?? '-' }}
                </span>
                <span class="text-sm font-bold">{{ jogo.selecao_visitante.sigla }}</span>
              </div>
            </div>
            <span
              class="w-20 text-right text-[11px] font-medium"
              :class="jogo.status === 'encerrado' ? 'text-primary' : 'text-text-muted'"
            >
              {{ jogo.status === 'encerrado' ? 'Encerrado' : 'Agendado' }}
            </span>
          </div>
        </div>
      </div>

      <!-- Ranking ao vivo -->
      <div class="rounded-2xl border border-border bg-bg-card p-5 lg:col-span-2">
        <div class="mb-5 flex items-center gap-2">
          <span class="h-2 w-2 animate-pulse rounded-full bg-primary" />
          <h2 class="text-lg font-bold">Ranking ao Vivo</h2>
        </div>
        <div v-if="!ranking.length" class="py-8 text-center text-sm text-text-muted">
          Nenhum cupom pontuado ainda.<br />Faca seus palpites!
        </div>
        <div v-else class="space-y-2">
          <div
            v-for="(item, i) in ranking.slice(0, 10)"
            :key="item.id"
            class="flex items-center gap-3 rounded-xl bg-bg-input px-3.5 py-2.5 transition-colors hover:bg-bg-card-hover"
          >
            <span
              class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-bold"
              :class="{
                'bg-gold/20 text-gold': i === 0,
                'bg-silver/20 text-silver': i === 1,
                'bg-bronze/20 text-bronze': i === 2,
                'bg-bg text-text-muted': i > 2,
              }"
            >
              {{ i + 1 }}
            </span>
            <div class="min-w-0 flex-1">
              <p class="truncate text-sm font-medium">{{ item.cupom.usuario.nome }}</p>
              <p class="text-[11px] text-text-muted">{{ item.cupom.codigo }}</p>
            </div>
            <span class="text-sm font-bold text-primary">{{ item.pontuacao_total }}</span>
          </div>
        </div>
      </div>
    </section>

    <!-- Regras de pontuacao -->
    <section v-if="torneio">
      <div class="mb-10 text-center">
        <h2 class="text-2xl font-extrabold sm:text-3xl">
          Defina o nivel de <span class="text-primary">disputa</span>
        </h2>
        <p class="mt-2 text-text-secondary">Confira as regras de pontuacao do torneio</p>
      </div>
      <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <div
          v-for="regra in torneio.regras_pontuacao"
          :key="regra.id"
          class="flex items-center justify-between rounded-xl border border-border bg-bg-card px-5 py-4 transition-colors hover:border-primary/30"
        >
          <div>
            <p class="text-sm font-semibold">{{ regra.nome }}</p>
            <p v-if="regra.descricao" class="mt-0.5 text-xs text-text-muted">{{ regra.descricao }}</p>
          </div>
          <span class="ml-4 shrink-0 rounded-lg bg-primary/15 px-3 py-1.5 text-sm font-bold text-primary">
            +{{ regra.pontos }} pts
          </span>
        </div>
      </div>
    </section>

    <!-- Fases -->
    <section v-if="torneio">
      <div class="mb-10 text-center">
        <h2 class="text-2xl font-extrabold sm:text-3xl">
          Fases do <span class="text-primary">torneio</span>
        </h2>
      </div>
      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div
          v-for="(fase, i) in torneio.fases"
          :key="fase.id"
          class="rounded-2xl border border-border bg-bg-card p-5 transition-colors hover:border-primary/40"
        >
          <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary/15 text-sm font-bold text-primary">
            {{ i + 1 }}
          </span>
          <h3 class="mt-3 font-bold">{{ fase.nome }}</h3>
          <p class="mt-1 text-xs text-text-muted">Tipo: {{ fase.tipo }}</p>
        </div>
      </div>
    </section>

    <!-- CTA Final -->
    <section class="rounded-3xl border border-primary/20 bg-gradient-to-br from-primary/10 to-bg-card px-6 py-12 text-center sm:px-12 sm:py-16">
      <h2 class="text-2xl font-extrabold sm:text-3xl">
        Crie seu cupom em menos de <span class="text-primary">2 minutos</span>
      </h2>
      <p class="mx-auto mt-3 max-w-md text-text-secondary">
        Cadastre-se, compre seu cupom e comece a fazer seus palpites agora mesmo.
      </p>
      <RouterLink
        to="/cadastro"
        class="mt-8 inline-block rounded-xl bg-primary px-8 py-3.5 text-sm font-bold text-bg shadow-lg shadow-primary/25 transition-all hover:bg-primary-hover hover:shadow-primary/40"
      >
        Criar minha conta
      </RouterLink>
    </section>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { requisicaoApi } from '../services/api'
import type { RankingItem, Torneio } from '../tipos'

const torneio = ref<Torneio | null>(null)
const ranking = ref<RankingItem[]>([])

const passos = [
  {
    titulo: 'Crie sua conta',
    descricao: 'Cadastre-se gratuitamente em segundos e acesse a plataforma para participar do bolao.',
  },
  {
    titulo: 'Compre um cupom',
    descricao: 'Cada cupom e um conjunto independente de palpites. Compre quantos quiser para aumentar suas chances.',
  },
  {
    titulo: 'Faca seus palpites',
    descricao: 'Preencha seus palpites para todos os jogos, classificados, artilheiro e resultado final do torneio.',
  },
]

onMounted(async () => {
  try {
    const respostaTorneio = await requisicaoApi<{ torneio: Torneio }>('/torneio')
    torneio.value = respostaTorneio.torneio

    const respostaRanking = await requisicaoApi<{ ranking: RankingItem[] }>(
      `/torneios/${respostaTorneio.torneio.id}/ranking`,
    )
    ranking.value = respostaRanking.ranking
  } catch {
    // torneio ainda nao publicado
  }
})
</script>
