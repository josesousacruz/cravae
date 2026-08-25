<template>
  <div class="mx-auto max-w-3xl space-y-6">
    <header>
      <h1 class="text-2xl font-black text-text">Resultados</h1>
      <p class="mt-1 text-sm text-text-secondary">Seu extrato de pontos, rodada a rodada.</p>
    </header>

    <div v-if="carregando" class="space-y-4">
      <div class="h-24 animate-pulse rounded-2xl border border-border bg-bg-card" />
      <div class="h-48 animate-pulse rounded-2xl border border-border bg-bg-card" />
    </div>

    <template v-else-if="cupom">
      <!-- Resumo -->
      <section class="grid grid-cols-3 gap-3">
        <article class="rounded-2xl border border-border bg-bg-card px-4 py-4 text-center">
          <p class="text-[11px] font-bold uppercase tracking-wide text-text-muted">Pontos em {{ nomeMes }}</p>
          <p class="mt-1 text-2xl font-black text-primary tabular-nums">{{ pontosNoMes }}</p>
        </article>
        <article class="rounded-2xl border border-border bg-bg-card px-4 py-4 text-center">
          <p class="text-[11px] font-bold uppercase tracking-wide text-text-muted">Posição no mês</p>
          <p class="mt-1 text-2xl font-black tabular-nums" :class="posicaoNoMes ? 'text-gold' : 'text-text-muted'">
            {{ posicaoNoMes ? `${posicaoNoMes}º` : '—' }}
          </p>
        </article>
        <article class="rounded-2xl border border-border bg-bg-card px-4 py-4 text-center">
          <p class="text-[11px] font-bold uppercase tracking-wide text-text-muted">Temporada</p>
          <p class="mt-1 text-2xl font-black text-text tabular-nums">{{ totalTemporada }}</p>
        </article>
      </section>

      <!-- Extrato por rodada -->
      <section v-if="rodadasComPontos.length" class="space-y-4">
        <article
          v-for="rodada in rodadasComPontos"
          :key="rodada.nome"
          class="overflow-hidden rounded-2xl border border-border bg-bg-card"
        >
          <header class="flex items-center justify-between gap-3 border-b border-border bg-bg-card-hover px-5 py-3">
            <h2 class="text-sm font-black uppercase tracking-wide text-text">{{ rodada.nome }}</h2>
            <span class="rounded-full bg-primary-dim px-3 py-1 text-sm font-black text-primary tabular-nums">+{{ rodada.total }} pts</span>
          </header>
          <div class="divide-y divide-border/70">
            <div v-for="evento in rodada.eventos" :key="evento.id" class="flex items-center justify-between gap-3 px-5 py-3">
              <div class="min-w-0">
                <p class="truncate text-sm font-semibold text-text">{{ confrontoDe(evento) }}</p>
                <p class="mt-0.5 text-xs text-text-muted">{{ evento.descricao }}<span v-if="placarRealDe(evento)"> · placar real {{ placarRealDe(evento) }}</span></p>
              </div>
              <span class="shrink-0 text-sm font-black text-primary tabular-nums">+{{ evento.pontos }}</span>
            </div>
          </div>
        </article>
      </section>

      <section v-else class="rounded-2xl border border-border bg-bg-card py-12 text-center">
        <p class="px-6 text-sm text-text-muted">
          Nenhum ponto ainda — seu extrato aparece aqui assim que os resultados das rodadas entram.
        </p>
      </section>

      <!-- Histórico de alterações, fora do caminho principal -->
      <section>
        <button
          type="button"
          class="text-sm font-semibold text-text-muted underline-offset-2 transition hover:text-primary hover:underline"
          @click="historicoAberto = !historicoAberto"
        >
          {{ historicoAberto ? 'Ocultar histórico de alterações' : 'Ver histórico de alterações dos palpites' }}
        </button>
        <div v-if="historicoAberto" class="mt-4">
          <HistoricoPalpites :cupom-id="cupom.id" />
        </div>
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
import { requisicaoApi } from '../services/api'
import { usarMeuCupomStore } from '../stores/meuCupom'
import { usarTorneioStore } from '../stores/torneio'
import type { Cupom, EventoPontuacao, Jogo, RespostaRanking } from '../tipos'

const meuCupomStore = usarMeuCupomStore()
const torneioStore = usarTorneioStore()

const cupom = ref<Cupom | null>(null)
const carregando = ref(true)
const historicoAberto = ref(false)
const pontosNoMes = ref(0)
const posicaoNoMes = ref<number | null>(null)

const competencia = new Date().toISOString().slice(0, 7)
const nomeMes = new Date().toLocaleDateString('pt-BR', { month: 'long' }).replace(/^./, (c) => c.toUpperCase())

const totalTemporada = computed(() => {
  const total = Number(cupom.value?.pontuacao?.pontuacao_total ?? 0)
  return Number.isFinite(total) ? Math.round(total) : 0
})

const jogosPorId = computed(() => {
  const mapa = new Map<number, Jogo>()
  for (const jogo of torneioStore.torneio?.jogos ?? []) mapa.set(jogo.id, jogo)
  return mapa
})

const rodadasComPontos = computed(() => {
  const grupos = new Map<string, { nome: string; ordem: number; total: number; eventos: EventoPontuacao[] }>()

  for (const evento of cupom.value?.eventos_pontuacao ?? []) {
    const jogo = evento.jogo_id ? jogosPorId.value.get(evento.jogo_id) : null
    const rodada = jogo?.rodada
    const nome = rodada?.nome ?? (jogo ? jogo.fase?.nome ?? 'Outros pontos' : 'Prêmios do campeonato')
    const ordem = rodada?.ordem ?? -1

    const grupo = grupos.get(nome) ?? { nome, ordem, total: 0, eventos: [] }
    grupo.total += evento.pontos
    grupo.eventos.push(evento)
    grupos.set(nome, grupo)
  }

  // Rodada mais recente primeiro; "Prêmios do campeonato" por último.
  return [...grupos.values()].sort((a, b) => b.ordem - a.ordem)
})

function confrontoDe(evento: EventoPontuacao) {
  const jogo = evento.jogo ?? (evento.jogo_id ? jogosPorId.value.get(evento.jogo_id) : null)
  if (!jogo) return 'Campeonato'
  const mandante = jogo.selecao_mandante?.nome ?? 'A definir'
  const visitante = jogo.selecao_visitante?.nome ?? 'A definir'
  return `${mandante} × ${visitante}`
}

function placarRealDe(evento: EventoPontuacao) {
  const jogo = evento.jogo_id ? jogosPorId.value.get(evento.jogo_id) : null
  const resultado = jogo?.resultado
  if (!resultado || resultado.placar_mandante === null || resultado.placar_visitante === null) return ''
  return `${resultado.placar_mandante}×${resultado.placar_visitante}`
}

onMounted(async () => {
  try {
    const base = await meuCupomStore.carregar()
    if (!base) return

    const [respostaCupom] = await Promise.all([
      requisicaoApi<{ cupom: Cupom }>(`/cupons/${base.id}`),
      torneioStore.carregar(),
    ])
    cupom.value = respostaCupom.cupom

    if (torneioStore.torneio) {
      const mensal = await requisicaoApi<RespostaRanking>(`/torneios/${torneioStore.torneio.id}/ranking?competencia=${competencia}`)
      pontosNoMes.value = Math.round(Number(mensal.minha_posicao?.item.pontuacao_total ?? 0))
      posicaoNoMes.value = mensal.minha_posicao?.posicao ?? null
    }
  } finally {
    carregando.value = false
  }
})
</script>
