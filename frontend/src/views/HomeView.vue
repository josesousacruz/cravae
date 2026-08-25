<template>
  <div class="mx-auto max-w-5xl space-y-4">
    <div v-if="carregando" class="space-y-4">
      <div class="h-20 animate-pulse rounded-2xl border border-border bg-bg-card" />
      <div class="h-40 animate-pulse rounded-2xl border border-border bg-bg-card" />
    </div>

    <template v-else>
      <!-- Faixa de assinatura (estilo "assina que vale") -->
      <RouterLink
        v-if="elegivelNoMes === false && !temAssinatura"
        to="/assinatura"
        class="flex items-center justify-between gap-3 rounded-xl border border-gold/50 bg-ambar/15 px-4 py-2.5 text-sm"
      >
        <span class="min-w-0 truncate">
          <span class="mr-2 rounded bg-ambar px-1.5 py-0.5 text-[10px] font-black uppercase text-[#17301f]">Fundador</span>
          <span class="font-semibold text-text">Só R$ 4,95/mês, vitalício<span v-if="vagasFundador"> — restam {{ vagasFundador }} vagas</span></span>
        </span>
        <span class="shrink-0 font-black text-gold">Assina que vale →</span>
      </RouterLink>
      <RouterLink
        v-else-if="elegivelNoMes === false && temAssinatura"
        to="/assinatura"
        class="flex items-center justify-between gap-3 rounded-xl border border-warning/40 bg-warning/10 px-4 py-2.5 text-sm"
      >
        <span class="min-w-0 truncate font-semibold text-text">Confirme o pagamento de {{ nomeMes }} para concorrer ao prêmio</span>
        <span class="shrink-0 font-black text-warning">Regularizar →</span>
      </RouterLink>

      <div class="grid gap-4 lg:grid-cols-[300px_minmax(0,1fr)]">
        <!-- Coluna do perfil (sidebar no desktop) -->
        <aside class="space-y-4">
          <section class="rounded-2xl border border-border bg-bg-card p-5 text-center">
            <span class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-primary text-xl font-black text-white">
              {{ autenticacao.nome.slice(0, 1).toUpperCase() }}
            </span>
            <h2 class="mt-3 truncate text-lg font-black text-text">{{ autenticacao.nome }}</h2>
            <p v-if="elegivelNoMes" class="mt-1 text-xs font-bold text-gold">★ Assinante</p>
            <p v-else class="mt-1 text-xs text-text-muted">Plano gratuito</p>

            <div class="mt-4 grid grid-cols-2 gap-2 border-t border-border pt-4">
              <div>
                <p class="text-[10px] font-bold uppercase tracking-wide text-text-muted">{{ nomeMes }}</p>
                <p class="text-xl font-black text-primary tabular-nums">{{ pontosNoMes }}</p>
                <p class="text-[11px] text-text-muted">{{ posicaoNoMes ? `${posicaoNoMes}º no mês` : 'sem pontos' }}</p>
              </div>
              <div>
                <p class="text-[10px] font-bold uppercase tracking-wide text-text-muted">Temporada</p>
                <p class="text-xl font-black text-text tabular-nums">{{ totalTemporada }}</p>
                <p class="text-[11px] text-text-muted">pontos</p>
              </div>
            </div>
            <RouterLink to="/perfil" class="mt-4 inline-flex rounded-full border border-border-light px-4 py-1.5 text-xs font-bold text-text-secondary transition hover:border-primary hover:text-primary">
              Ver perfil
            </RouterLink>
          </section>

          <!-- Acesso rápido -->
          <section class="grid grid-cols-3 gap-2 lg:grid-cols-1">
            <RouterLink to="/ranking" class="rounded-2xl border border-border bg-bg-card px-3 py-3 text-center text-xs font-bold text-text-secondary transition hover:border-primary hover:text-primary lg:flex lg:items-center lg:gap-2 lg:text-left lg:text-sm">
              🏆 Ranking do mês
            </RouterLink>
            <RouterLink to="/resultados" class="rounded-2xl border border-border bg-bg-card px-3 py-3 text-center text-xs font-bold text-text-secondary transition hover:border-primary hover:text-primary lg:flex lg:items-center lg:gap-2 lg:text-left lg:text-sm">
              📊 Meu extrato
            </RouterLink>
            <RouterLink to="/assinatura" class="rounded-2xl border border-border bg-bg-card px-3 py-3 text-center text-xs font-bold text-text-secondary transition hover:border-primary hover:text-primary lg:flex lg:items-center lg:gap-2 lg:text-left lg:text-sm">
              ⭐ Assinatura
            </RouterLink>
          </section>
        </aside>

        <!-- Coluna principal -->
        <div class="space-y-4">
          <!-- CTA de palpites (equivalente ao "Escalar meu time") -->
          <section class="overflow-hidden rounded-2xl border border-border bg-gradient-to-br from-topo to-topo-claro p-6 text-white">
            <div class="flex flex-wrap items-center justify-between gap-2">
              <p class="text-xs font-bold uppercase tracking-[0.2em] text-emerald-200/80">{{ rodadaAtualNome }}</p>
              <span v-if="textoFechamento" class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1 text-xs font-bold text-ambar">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                {{ textoFechamento }}
              </span>
            </div>
            <h1 class="mt-2 text-2xl font-black sm:text-3xl">
              {{ progressoTexto }}
            </h1>
            <div v-if="totalJogosRodada" class="mt-3 h-2 max-w-sm overflow-hidden rounded-full bg-white/15">
              <div class="h-full rounded-full bg-ambar transition-all" :style="{ width: `${Math.round((palpitesFeitos / totalJogosRodada) * 100)}%` }" />
            </div>
            <RouterLink
              to="/palpites"
              class="mt-5 inline-flex items-center gap-2 rounded-xl bg-ambar px-6 py-3.5 text-sm font-black uppercase tracking-wide text-[#17301f] shadow-lg transition hover:scale-[1.02] hover:brightness-105"
            >
              {{ palpitesFeitos >= totalJogosRodada && totalJogosRodada > 0 ? 'Revisar meus palpites' : 'Fazer meus palpites' }}
              <span aria-hidden="true">→</span>
            </RouterLink>
          </section>

          <!-- Ranking do mês (teaser) -->
          <section v-if="topRanking.length" class="rounded-2xl border border-border bg-bg-card p-5">
            <div class="mb-3 flex items-center justify-between">
              <h2 class="text-sm font-black uppercase tracking-wide text-text">Disputa de {{ nomeMes }}</h2>
              <RouterLink to="/ranking" class="text-xs font-bold text-primary underline-offset-2 hover:underline">Ver ranking →</RouterLink>
            </div>
            <div class="space-y-2">
              <div v-for="(item, i) in topRanking" :key="item.id" class="flex items-center gap-3 rounded-xl bg-bg px-3 py-2">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-black" :class="i === 0 ? 'bg-ambar text-[#17301f]' : 'bg-bg-card text-text-muted border border-border'">{{ i + 1 }}</span>
                <span class="min-w-0 flex-1 truncate text-sm font-semibold text-text">{{ item.cupom.usuario.nome }}</span>
                <span v-if="item.assinante" class="text-xs text-gold">★</span>
                <span class="shrink-0 text-sm font-black text-primary tabular-nums">{{ Math.round(Number(item.pontuacao_total)) }} pts</span>
              </div>
            </div>
          </section>

          <!-- Jogos da rodada -->
          <section class="rounded-2xl border border-border bg-bg-card p-5">
            <div class="mb-3 flex items-center justify-between">
              <h2 class="text-sm font-black uppercase tracking-wide text-text">Jogos da {{ rodadaAtualNome }}</h2>
              <RouterLink to="/palpites" class="text-xs font-bold text-primary underline-offset-2 hover:underline">Palpitar →</RouterLink>
            </div>
            <div class="divide-y divide-border/70">
              <RouterLink v-for="jogo in jogosRodada" :key="jogo.id" to="/palpites" class="flex items-center gap-3 py-2.5 transition hover:bg-bg-card-hover">
                <span class="w-24 shrink-0 text-[11px] text-text-muted">{{ formatarDataJogo(jogo.data_hora_inicio) }}</span>
                <span class="min-w-0 flex-1 truncate text-right text-sm font-semibold text-text">{{ jogo.selecao_mandante?.nome ?? 'A definir' }}</span>
                <span class="shrink-0 text-xs font-black text-text-muted">×</span>
                <span class="min-w-0 flex-1 truncate text-sm font-semibold text-text">{{ jogo.selecao_visitante?.nome ?? 'A definir' }}</span>
                <span class="w-14 shrink-0 text-right">
                  <span v-if="palpitou(jogo.id)" class="text-xs font-bold text-primary">✓</span>
                  <span v-else class="text-xs font-bold text-warning">—</span>
                </span>
              </RouterLink>
            </div>
          </section>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { requisicaoApi } from '../services/api'
import { usarAutenticacaoStore } from '../stores/autenticacao'
import { usarMeuCupomStore } from '../stores/meuCupom'
import { usarTorneioStore } from '../stores/torneio'
import type { Aposta, Cupom, Jogo, RankingItem, RespostaRanking } from '../tipos'

const autenticacao = usarAutenticacaoStore()
const meuCupomStore = usarMeuCupomStore()
const torneioStore = usarTorneioStore()

const carregando = ref(true)
const cupom = ref<Cupom | null>(null)
const apostas = ref<Aposta[]>([])
const elegivelNoMes = ref<boolean | null>(null)
const temAssinatura = ref(false)
const vagasFundador = ref<number | null>(null)
const pontosNoMes = ref(0)
const posicaoNoMes = ref<number | null>(null)
const topRanking = ref<RankingItem[]>([])
const agoraMs = ref(Date.now())

const competencia = new Date().toISOString().slice(0, 7)
const nomeMes = new Date().toLocaleDateString('pt-BR', { month: 'long' }).replace(/^./, (c) => c.toUpperCase())

const totalTemporada = computed(() => Math.round(Number(cupom.value?.pontuacao?.pontuacao_total ?? 0)))

// Rodada atual: a que contém o próximo jogo a iniciar (ou a última com jogos).
const jogosRodada = computed<Jogo[]>(() => {
  const jogos = [...(torneioStore.torneio?.jogos ?? [])].sort((a, b) => a.data_hora_inicio.localeCompare(b.data_hora_inicio))
  if (!jogos.length) return []
  const proximo = jogos.find((j) => new Date(j.data_hora_inicio).getTime() > agoraMs.value)
  const rodadaId = (proximo ?? jogos[jogos.length - 1]).rodada_id
  return jogos.filter((j) => j.rodada_id === rodadaId)
})

const rodadaAtualNome = computed(() => jogosRodada.value[0]?.rodada?.nome ?? 'Rodada atual')
const totalJogosRodada = computed(() => jogosRodada.value.length)

const palpitesFeitos = computed(() => jogosRodada.value.filter((j) => palpitou(j.id)).length)

function palpitou(jogoId: number) {
  return apostas.value.some((a) => a.jogo_id === jogoId && a.tipo.startsWith('placar_jogo'))
}

const progressoTexto = computed(() => {
  if (!totalJogosRodada.value) return 'Sem jogos na rodada'
  if (palpitesFeitos.value >= totalJogosRodada.value) return 'Rodada completa — boa sorte!'
  if (palpitesFeitos.value === 0) return 'Seus palpites estão em branco'
  return `${palpitesFeitos.value} de ${totalJogosRodada.value} palpites feitos`
})

const textoFechamento = computed(() => {
  const proximos = jogosRodada.value
    .map((j) => new Date(j.data_hora_inicio).getTime())
    .filter((ms) => ms > agoraMs.value)
    .sort((a, b) => a - b)
  if (!proximos.length) return ''
  const restante = proximos[0] - agoraMs.value
  const horas = Math.floor(restante / 3_600_000)
  if (horas >= 48) return `Fecha em ${Math.floor(horas / 24)} dias`
  if (horas > 0) return `Fecha em ${horas}h${String(Math.floor((restante % 3_600_000) / 60_000)).padStart(2, '0')}`
  return `Fecha em ${Math.floor(restante / 60_000)}min`
})

function formatarDataJogo(dataHora: string) {
  const data = new Date(dataHora)
  return data.toLocaleDateString('pt-BR', { weekday: 'short', day: '2-digit' }).replace('.', '') +
    ' ' + data.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })
}

onMounted(async () => {
  try {
    const base = await meuCupomStore.carregar()
    await torneioStore.carregar()

    if (base) {
      cupom.value = base
      try {
        const [rCupom, rApostas] = await Promise.all([
          requisicaoApi<{ cupom: Cupom }>(`/cupons/${base.id}`),
          requisicaoApi<{ apostas: Aposta[] }>(`/cupons/${base.id}/apostas`),
        ])
        cupom.value = rCupom.cupom
        apostas.value = rApostas.apostas
      } catch { /* extrato opcional */ }
    }

    try {
      const [a, p] = await Promise.all([
        requisicaoApi<{ assinatura: { status: string } | null; elegivel_no_mes: boolean }>('/assinatura'),
        requisicaoApi<{ planos: { vagas_fundador_restantes: number }[] }>('/planos'),
      ])
      elegivelNoMes.value = a.elegivel_no_mes
      temAssinatura.value = Boolean(a.assinatura && a.assinatura.status !== 'cancelada')
      vagasFundador.value = p.planos[0]?.vagas_fundador_restantes ?? null
    } catch { /* faixa opcional */ }

    try {
      if (torneioStore.torneio) {
        const mensal = await requisicaoApi<RespostaRanking>(`/torneios/${torneioStore.torneio.id}/ranking?competencia=${competencia}`)
        topRanking.value = mensal.ranking.slice(0, 3)
        pontosNoMes.value = Math.round(Number(mensal.minha_posicao?.item.pontuacao_total ?? 0))
        posicaoNoMes.value = mensal.minha_posicao?.posicao ?? null
      }
    } catch { /* teaser opcional */ }
  } finally {
    carregando.value = false
  }
})
</script>
