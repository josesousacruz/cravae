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
        <!-- Coluna do perfil (sidebar no desktop, formato Cartola) -->
        <aside class="space-y-4">
          <section class="rounded-2xl border border-border bg-bg-card p-5 text-center">
            <div class="relative mx-auto h-24 w-24">
              <span class="flex h-24 w-24 items-center justify-center overflow-hidden rounded-full border-4 border-primary-dim bg-primary text-3xl font-black text-white">
                <img v-if="autenticacao.fotoUrl" :src="autenticacao.fotoUrl" :alt="autenticacao.nome" class="h-full w-full object-cover">
                <template v-else>{{ autenticacao.nome.slice(0, 1).toUpperCase() }}</template>
              </span>
              <img src="/marca/cravae-simbolo.svg" alt="" class="absolute -bottom-1 -right-1 h-9 w-9 rounded-full border-2 border-bg-card bg-bg-card p-1">
            </div>
            <h2 class="mt-3 truncate text-lg font-black text-text">{{ autenticacao.nome }}</h2>
            <p v-if="elegivelNoMes" class="mt-0.5 text-xs font-bold text-gold">★ Assinante</p>
            <p v-else class="mt-0.5 text-xs text-text-muted">Plano gratuito</p>

            <RouterLink to="/perfil" class="mt-4 block w-full rounded-lg border border-border-light py-2.5 text-xs font-black uppercase tracking-[0.12em] text-text transition hover:border-primary hover:text-primary">
              Ver perfil
            </RouterLink>
          </section>

          <!-- Régua de estatísticas -->
          <section class="grid grid-cols-3 divide-x divide-border rounded-2xl border border-border bg-bg-card py-3 text-center">
            <div class="px-2">
              <p class="text-[10px] font-black uppercase tracking-wide text-text-muted">{{ nomeMes }}</p>
              <p class="mt-1 text-lg font-black text-primary tabular-nums">{{ pontosNoMes }}</p>
            </div>
            <div class="px-2">
              <p class="text-[10px] font-black uppercase tracking-wide text-text-muted">Posição</p>
              <p class="mt-1 text-lg font-black text-text tabular-nums">{{ posicaoNoMes ? `${posicaoNoMes}º` : '—' }}</p>
            </div>
            <div class="px-2">
              <p class="text-[10px] font-black uppercase tracking-wide text-text-muted">Total</p>
              <p class="mt-1 text-lg font-black text-text tabular-nums">{{ totalTemporada }}</p>
            </div>
          </section>

          <!-- Acesso rápido -->
          <section>
            <h3 class="mb-2 px-1 text-sm font-bold text-text">Acesso rápido</h3>
            <div class="grid grid-cols-2 gap-2">
              <RouterLink
                v-for="atalho in atalhos"
                :key="atalho.rota"
                :to="atalho.rota"
                class="flex flex-col items-center justify-center gap-2 rounded-2xl border border-border bg-bg-card px-3 py-5 text-center transition hover:border-primary"
              >
                <span class="flex h-11 w-11 items-center justify-center rounded-full bg-ambar/15">
                  <svg class="h-6 w-6 text-gold" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" :d="atalho.icone" />
                  </svg>
                </span>
                <span class="text-[11px] font-black uppercase leading-tight tracking-wide text-text-secondary">{{ atalho.nome }}</span>
              </RouterLink>
            </div>
          </section>
        </aside>

        <!-- Coluna principal -->
        <div class="space-y-4">
          <!-- CTA de palpites (formato do card de escalação do Cartola) -->
          <section class="rounded-2xl border border-border bg-bg-card px-6 py-7 text-center">
            <p class="text-sm font-medium text-text-secondary">{{ fraseStatus }}</p>

            <div class="mt-5 flex items-center justify-center">
              <span
                v-for="(jogo, i) in jogosRodada.slice(0, 10)"
                :key="jogo.id"
                class="relative inline-flex h-12 w-12 items-center justify-center rounded-full border-2 border-bg-card bg-bg shadow-sm"
                :class="i > 0 ? '-ml-2.5' : ''"
                :style="{ zIndex: 10 - i }"
              >
                <img
                  :src="escudoDe(jogo)"
                  :alt="jogo.selecao_mandante?.nome ?? ''"
                  class="h-8 w-8 object-contain transition"
                  :class="palpitou(jogo.id) ? '' : 'opacity-35 grayscale'"
                >
                <span v-if="palpitou(jogo.id)" class="absolute -bottom-0.5 -right-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-primary text-[9px] font-black text-white">✓</span>
              </span>
            </div>

            <p class="mt-4 text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
              {{ rodadaAtualNome }}<span v-if="textoFechamento"> · {{ textoFechamento }}</span>
            </p>

            <RouterLink
              to="/palpites"
              class="mt-5 block w-full rounded-xl bg-ambar py-4 text-sm font-black uppercase tracking-[0.08em] text-[#17301f] shadow-lg transition hover:brightness-105"
            >
              {{ palpitesFeitos >= totalJogosRodada && totalJogosRodada > 0 ? 'Revisar meus palpites' : 'Fazer meus palpites' }}
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
                <span class="flex min-w-0 flex-1 items-center justify-end gap-2">
                  <span class="min-w-0 truncate text-right text-sm font-semibold text-text">{{ jogo.selecao_mandante?.nome ?? 'A definir' }}</span>
                  <img v-if="jogo.selecao_mandante?.escudo_url" :src="jogo.selecao_mandante.escudo_url" alt="" class="h-6 w-6 shrink-0 object-contain">
                </span>
                <span class="shrink-0 text-xs font-black text-text-muted">×</span>
                <span class="flex min-w-0 flex-1 items-center gap-2">
                  <img v-if="jogo.selecao_visitante?.escudo_url" :src="jogo.selecao_visitante.escudo_url" alt="" class="h-6 w-6 shrink-0 object-contain">
                  <span class="min-w-0 truncate text-sm font-semibold text-text">{{ jogo.selecao_visitante?.nome ?? 'A definir' }}</span>
                </span>
                <span class="w-14 shrink-0 text-right">
                  <span v-if="palpitou(jogo.id)" class="text-xs font-bold text-primary">✓</span>
                  <span v-else class="text-xs font-bold text-warning">—</span>
                </span>
              </RouterLink>
            </div>
          </section>

          <!-- Notícias dos clubes -->
          <section class="rounded-2xl border border-border bg-bg-card p-5">
            <h2 class="mb-3 text-sm font-black uppercase tracking-wide text-text">Veja notícias dos clubes</h2>
            <div class="flex gap-3 overflow-x-auto pb-1 scrollbar-none">
              <a
                v-for="clube in clubes"
                :key="clube.id"
                :href="linkNoticias(clube.nome)"
                target="_blank"
                rel="noopener"
                class="flex shrink-0 flex-col items-center gap-1.5"
                :title="`Notícias do ${clube.nome}`"
              >
                <span class="flex h-12 w-12 items-center justify-center rounded-full border border-border bg-bg transition hover:border-primary">
                  <img v-if="clube.escudo_url" :src="clube.escudo_url" :alt="clube.nome" class="h-8 w-8 object-contain">
                  <span v-else class="text-xs font-black text-text-muted">{{ clube.sigla }}</span>
                </span>
                <span class="text-[10px] font-bold uppercase text-text-muted">{{ clube.sigla }}</span>
              </a>
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

const atalhos = [
  { nome: 'Ranking do mês', rota: '/ranking', icone: 'M16.5 18.75h-9m9 0a3 3 0 013 3h-15a3 3 0 013-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872' },
  { nome: 'Meu extrato', rota: '/resultados', icone: 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z' },
  { nome: 'Assinatura', rota: '/assinatura', icone: 'M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z' },
  { nome: 'Entenda o jogo', rota: '/como-jogar', icone: 'M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z' },
]

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

const clubes = computed(() => {
  const lista = torneioStore.torneio?.selecoes ?? torneioStore.torneio?.grupos?.flatMap((g) => g.selecoes) ?? []
  return [...lista].sort((a, b) => a.nome.localeCompare(b.nome))
})

function linkNoticias(nomeClube: string) {
  return `https://news.google.com/search?q=${encodeURIComponent(nomeClube + ' futebol')}&hl=pt-BR&gl=BR`
}

const rodadaAtualNome = computed(() => jogosRodada.value[0]?.rodada?.nome ?? 'Rodada atual')
const totalJogosRodada = computed(() => jogosRodada.value.length)

const palpitesFeitos = computed(() => jogosRodada.value.filter((j) => palpitou(j.id)).length)

function palpitou(jogoId: number) {
  return apostas.value.some((a) => a.jogo_id === jogoId && a.tipo.startsWith('placar_jogo'))
}

const fraseStatus = computed(() => {
  if (!totalJogosRodada.value) return 'Sem jogos na rodada por enquanto.'
  if (palpitesFeitos.value >= totalJogosRodada.value) return 'Palpites cravados para a rodada — boa sorte!'
  if (palpitesFeitos.value === 0) return 'Seus palpites estão em branco para a rodada!'
  return `Faltam ${totalJogosRodada.value - palpitesFeitos.value} palpites para a rodada!`
})

function escudoDe(jogo: Jogo) {
  // Ajuste provisório a pedido do dono (torcedor do Vitória), até existir a
  // feature "clube do coração": jogos do Vitória mostram o Vitória; jogos do
  // Bahia mostram o adversário.
  const mandante = jogo.selecao_mandante
  const visitante = jogo.selecao_visitante
  if (visitante?.sigla === 'VIT') return visitante.escudo_url ?? ''
  if (mandante?.sigla === 'BAH') return visitante?.escudo_url ?? ''
  if (visitante?.sigla === 'BAH') return mandante?.escudo_url ?? ''
  return mandante?.escudo_url ?? ''
}

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
