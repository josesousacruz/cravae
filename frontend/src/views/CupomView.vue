<template>
  <div class="mx-auto max-w-5xl">
    <!-- Loading -->
    <div v-if="carregando" class="flex items-center justify-center py-20">
      <span class="text-text-muted">Carregando...</span>
    </div>

    <div v-else-if="cupom && torneio" class="space-y-6">
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
          :class="cupom.status === 'ativo' ? 'bg-primary/20 text-primary' : 'bg-warning/20 text-warning'"
        >
          {{ cupom.status }}
        </span>
      </div>

      <!-- Feedback -->
      <p v-if="mensagem" class="rounded-lg bg-primary/10 px-3 py-2 text-sm text-primary">{{ mensagem }}</p>
      <p v-if="erro" class="rounded-lg bg-danger/10 px-3 py-2 text-sm text-danger">{{ erro }}</p>

      <!-- Tabs -->
      <div class="flex overflow-x-auto border-b border-border">
        <button
          v-for="tab in tabs"
          :key="tab.id"
          @click="tabAtiva = tab.id"
          class="whitespace-nowrap px-4 py-3 text-sm transition cursor-pointer"
          :class="tabAtiva === tab.id ? 'border-b-2 border-primary text-primary font-medium' : 'text-text-muted hover:text-text-secondary'"
        >
          {{ tab.nome }}
        </button>
      </div>

      <!-- ═══════ Tab Palpites ═══════ -->
      <section v-if="tabAtiva === 'palpites'">
        <!-- Sub-tabs -->
        <div class="flex gap-2 mt-4 mb-6 overflow-x-auto">
          <button
            v-for="sub in subTabs"
            :key="sub.id"
            @click="subTabAtiva = sub.id"
            class="px-3 py-1.5 rounded-lg text-xs font-medium whitespace-nowrap transition cursor-pointer"
            :class="subTabAtiva === sub.id ? 'bg-primary/20 text-primary' : 'bg-bg-input text-text-muted hover:text-text-secondary'"
          >
            {{ sub.nome }}
          </button>
        </div>

        <!-- Fase de Grupos -->
        <div v-if="subTabAtiva === 'grupos'" class="rounded-2xl border border-border bg-bg-card p-5">
          <h2 class="mb-2 text-lg font-bold">Fase de Grupos</h2>
          <p class="mb-4 text-sm text-text-muted">Informe o placar de cada jogo da fase de grupos.</p>

          <div v-for="grupo in torneio.grupos" :key="grupo.id" class="mb-6">
            <h3 class="mb-3 text-sm font-bold text-primary">{{ grupo.nome }}</h3>
            <div class="space-y-3">
              <div
                v-for="jogo in jogosDoGrupo(grupo.id)"
                :key="jogo.id"
                class="flex items-center gap-3 rounded-xl bg-bg-input p-3 sm:p-4"
              >
                <div class="flex flex-1 items-center justify-end gap-2 text-right">
                  <span class="text-sm font-semibold">{{ jogo.selecao_mandante.sigla }}</span>
                </div>
                <input
                  v-model="placaresGrupos[jogo.id].placar_mandante"
                  type="number" min="0" class="!w-14" placeholder="-"
                />
                <span class="text-xs text-text-muted">x</span>
                <input
                  v-model="placaresGrupos[jogo.id].placar_visitante"
                  type="number" min="0" class="!w-14" placeholder="-"
                />
                <div class="flex flex-1 items-center gap-2">
                  <span class="text-sm font-semibold">{{ jogo.selecao_visitante.sigla }}</span>
                </div>
              </div>
            </div>
          </div>

          <button type="button" @click="salvarPlacaresGrupos" class="mt-2 w-full rounded-lg bg-primary py-2.5 text-sm font-semibold text-bg transition-colors hover:bg-primary-hover">
            Salvar placares da fase de grupos
          </button>
        </div>

        <!-- Classificacao dos Grupos -->
        <div v-if="subTabAtiva === 'classificacao'" class="rounded-2xl border border-border bg-bg-card p-5">
          <h2 class="mb-2 text-lg font-bold">Classificados dos Grupos</h2>
          <p class="mb-4 text-sm text-text-muted">Selecione o primeiro e segundo colocado de cada grupo.</p>

          <div class="space-y-5">
            <div v-for="grupo in torneio.grupos" :key="grupo.id" class="rounded-xl bg-bg-input p-4">
              <h3 class="mb-3 text-sm font-bold text-primary">{{ grupo.nome }}</h3>
              <div class="grid gap-3 sm:grid-cols-2">
                <label class="block">
                  <span class="mb-1.5 block text-xs text-text-muted">Primeiro colocado</span>
                  <select v-model="classificacaoGrupos[grupo.id].primeiro">
                    <option value="">Selecione</option>
                    <option v-for="s in grupo.selecoes" :key="s.id" :value="String(s.id)">{{ s.nome }}</option>
                  </select>
                </label>
                <label class="block">
                  <span class="mb-1.5 block text-xs text-text-muted">Segundo colocado</span>
                  <select v-model="classificacaoGrupos[grupo.id].segundo">
                    <option value="">Selecione</option>
                    <option v-for="s in grupo.selecoes" :key="s.id" :value="String(s.id)">{{ s.nome }}</option>
                  </select>
                </label>
              </div>
            </div>
          </div>

          <button type="button" @click="salvarClassificacao" class="mt-5 w-full rounded-lg bg-primary py-2.5 text-sm font-semibold text-bg transition-colors hover:bg-primary-hover">
            Salvar classificados dos grupos
          </button>
        </div>

        <!-- Mata-Mata -->
        <div v-if="subTabAtiva === 'mata-mata'" class="rounded-2xl border border-border bg-bg-card p-5">
          <h2 class="mb-2 text-lg font-bold">Mata-Mata</h2>
          <p class="mb-4 text-sm text-text-muted">Informe o placar e quem avanca em cada jogo eliminatorio.</p>

          <div v-if="!jogosEliminatorios.length" class="py-8 text-center text-text-muted">
            Jogos eliminatorios serao exibidos apos a definicao dos classificados.
          </div>

          <div v-else class="space-y-4">
            <div v-for="jogo in jogosEliminatorios" :key="jogo.id" class="rounded-xl bg-bg-input p-4">
              <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-primary">{{ jogo.fase.nome }}</p>
              <div class="flex items-center gap-3">
                <div class="flex flex-1 items-center justify-end gap-2 text-right">
                  <span class="text-sm font-semibold">{{ jogo.selecao_mandante.sigla }}</span>
                </div>
                <input v-model="placaresEliminatorios[jogo.id].placar_mandante" type="number" min="0" class="!w-14" placeholder="-" />
                <span class="text-xs text-text-muted">x</span>
                <input v-model="placaresEliminatorios[jogo.id].placar_visitante" type="number" min="0" class="!w-14" placeholder="-" />
                <div class="flex flex-1 items-center gap-2">
                  <span class="text-sm font-semibold">{{ jogo.selecao_visitante.sigla }}</span>
                </div>
              </div>
              <label class="mt-3 block">
                <span class="mb-1.5 block text-xs text-text-muted">Quem avanca?</span>
                <select v-model="placaresEliminatorios[jogo.id].selecao_classificada_id">
                  <option value="">Selecione</option>
                  <option :value="String(jogo.selecao_mandante.id)">{{ jogo.selecao_mandante.nome }}</option>
                  <option :value="String(jogo.selecao_visitante.id)">{{ jogo.selecao_visitante.nome }}</option>
                </select>
              </label>
            </div>
          </div>

          <button v-if="jogosEliminatorios.length" type="button" @click="salvarMataMata" class="mt-5 w-full rounded-lg bg-primary py-2.5 text-sm font-semibold text-bg transition-colors hover:bg-primary-hover">
            Salvar palpites mata-mata
          </button>
        </div>

        <!-- Finais & Artilheiro -->
        <div v-if="subTabAtiva === 'finais'" class="space-y-6">
          <div class="rounded-2xl border border-border bg-bg-card p-5">
            <h2 class="mb-4 text-lg font-bold">Artilheiro</h2>
            <select v-model="artilheiroId">
              <option value="">Selecione o artilheiro</option>
              <option v-for="jogador in jogadores" :key="jogador.id" :value="String(jogador.id)">
                {{ jogador.nome }} ({{ jogador.selecao_sigla }})
              </option>
            </select>
            <button type="button" @click="salvarArtilheiro" class="mt-4 w-full rounded-lg bg-primary py-2.5 text-sm font-semibold text-bg transition-colors hover:bg-primary-hover">
              Salvar artilheiro
            </button>
          </div>

          <div class="rounded-2xl border border-border bg-bg-card p-5">
            <h2 class="mb-4 text-lg font-bold">Palpites Finais</h2>
            <p class="mb-4 text-sm text-text-muted">Quem sera campeao, vice e terceiro colocado?</p>
            <div class="space-y-3">
              <label class="block">
                <span class="mb-1.5 block text-xs text-text-muted">Campeao</span>
                <select v-model="palpitesFinais.campeao">
                  <option value="">Selecione</option>
                  <option v-for="s in todasSelecoes" :key="s.id" :value="String(s.id)">{{ s.nome }}</option>
                </select>
              </label>
              <label class="block">
                <span class="mb-1.5 block text-xs text-text-muted">Vice-campeao</span>
                <select v-model="palpitesFinais.vice_campeao">
                  <option value="">Selecione</option>
                  <option v-for="s in todasSelecoes" :key="s.id" :value="String(s.id)">{{ s.nome }}</option>
                </select>
              </label>
              <label class="block">
                <span class="mb-1.5 block text-xs text-text-muted">Terceiro colocado</span>
                <select v-model="palpitesFinais.terceiro_colocado">
                  <option value="">Selecione</option>
                  <option v-for="s in todasSelecoes" :key="s.id" :value="String(s.id)">{{ s.nome }}</option>
                </select>
              </label>
            </div>
            <button type="button" @click="salvarPalpitesFinais" class="mt-5 w-full rounded-lg bg-primary py-2.5 text-sm font-semibold text-bg transition-colors hover:bg-primary-hover">
              Salvar palpites finais
            </button>
          </div>
        </div>
      </section>

      <!-- ═══════ Tab Ranking ═══════ -->
      <section v-if="tabAtiva === 'ranking'">
        <div v-if="carregandoRanking" class="rounded-2xl border border-border bg-bg-card overflow-hidden">
          <div class="bg-bg-input px-4 py-3">
            <div class="flex gap-4">
              <div class="h-3 w-8 animate-pulse rounded bg-border"></div>
              <div class="h-3 w-20 animate-pulse rounded bg-border"></div>
              <div class="h-3 w-16 animate-pulse rounded bg-border"></div>
            </div>
          </div>
          <div class="divide-y divide-border/50">
            <div v-for="n in 5" :key="n" class="flex items-center gap-4 px-4 py-3">
              <div class="h-8 w-8 animate-pulse rounded-full bg-bg-input"></div>
              <div class="flex-1 space-y-1">
                <div class="h-4 w-28 animate-pulse rounded bg-bg-input"></div>
              </div>
              <div class="h-5 w-10 animate-pulse rounded bg-bg-input"></div>
            </div>
          </div>
        </div>

        <div v-else-if="!ranking.length" class="rounded-2xl border border-border bg-bg-card py-8 text-center">
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
                    <span class="inline-flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold"
                      :class="{ 'text-gold': i === 0, 'text-silver': i === 1, 'text-bronze': i === 2, 'text-text-muted': i > 2 }">
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

      <!-- ═══════ Tab Meus Resultados ═══════ -->
      <section v-if="tabAtiva === 'resultados'">
        <div v-if="cupom.eventos_pontuacao?.length" class="rounded-2xl border border-border bg-bg-card p-5">
          <h2 class="mb-4 text-lg font-bold">Eventos de Pontuacao</h2>
          <div class="space-y-2">
            <div v-for="evento in cupom.eventos_pontuacao" :key="evento.id" class="flex items-center justify-between rounded-lg bg-bg-input px-3.5 py-2.5">
              <span class="text-sm">{{ evento.descricao }}</span>
              <span class="text-sm font-bold text-primary">+{{ evento.pontos }} pts</span>
            </div>
          </div>
        </div>
        <div v-else class="rounded-2xl border border-border bg-bg-card py-12 text-center">
          <p class="text-text-muted">Nenhum evento de pontuacao registrado para este cupom ainda.</p>
        </div>
      </section>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { requisicaoApi } from '../services/api'
import { usarTorneioStore } from '../stores/torneio'
import { useToast } from '../composables/useToast'
import type { Aposta, Cupom, Jogador, RankingItem, Selecao, Torneio } from '../tipos'

const rota = useRoute()
const torneioStore = usarTorneioStore()
const { mostrar } = useToast()

const torneio = ref<Torneio | null>(null)
const cupom = ref<Cupom | null>(null)
const apostas = ref<Aposta[]>([])
const ranking = ref<RankingItem[]>([])
const carregando = ref(true)
const carregandoRanking = ref(false)
const mensagem = ref('')
const erro = ref('')

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

const placaresGrupos = ref<Record<number, { placar_mandante: string; placar_visitante: string }>>({})
const placaresEliminatorios = ref<Record<number, { placar_mandante: string; placar_visitante: string; selecao_classificada_id: string }>>({})
const classificacaoGrupos = ref<Record<number, { primeiro: string; segundo: string }>>({})
const artilheiroId = ref('')
const palpitesFinais = ref({ campeao: '', vice_campeao: '', terceiro_colocado: '' })

const jogosGrupos = computed(() => torneio.value?.jogos.filter((j) => j.fase.tipo === 'grupos') ?? [])
const jogosEliminatorios = computed(() => torneio.value?.jogos.filter((j) => j.fase.tipo !== 'grupos') ?? [])
const todasSelecoes = computed<Selecao[]>(() => torneio.value?.grupos.flatMap((g) => g.selecoes) ?? [])
const jogadores = computed(() => {
  return todasSelecoes.value.flatMap((s) =>
    (s.jogadores ?? []).map((j) => ({ ...j, selecao_sigla: s.sigla })),
  )
})

function jogosDoGrupo(grupoId: number) {
  return jogosGrupos.value.filter((j) => j.grupo_id === grupoId)
}

function encontrarAposta(tipo: string, referenciaId?: number) {
  return apostas.value.find((a) => {
    if (a.tipo !== tipo) return false
    if (tipo === 'classificacao_grupo') return a.grupo_id === referenciaId
    if (tipo === 'artilheiro') return true
    if (['campeao', 'vice_campeao', 'terceiro_colocado'].includes(tipo)) return true
    return a.jogo_id === referenciaId
  })
}

function preencherFormulario() {
  for (const jogo of jogosGrupos.value) {
    const aposta = encontrarAposta('placar_jogo_grupos', jogo.id)
    placaresGrupos.value[jogo.id] = {
      placar_mandante: String(aposta?.conteudo.placar_mandante ?? ''),
      placar_visitante: String(aposta?.conteudo.placar_visitante ?? ''),
    }
  }

  for (const grupo of torneio.value?.grupos ?? []) {
    const aposta = encontrarAposta('classificacao_grupo', grupo.id)
    classificacaoGrupos.value[grupo.id] = {
      primeiro: String(aposta?.conteudo.primeiro_colocado_id ?? ''),
      segundo: String(aposta?.conteudo.segundo_colocado_id ?? ''),
    }
  }

  for (const jogo of jogosEliminatorios.value) {
    const aposta = encontrarAposta('placar_jogo_eliminatoria', jogo.id)
    placaresEliminatorios.value[jogo.id] = {
      placar_mandante: String(aposta?.conteudo.placar_mandante ?? ''),
      placar_visitante: String(aposta?.conteudo.placar_visitante ?? ''),
      selecao_classificada_id: String(aposta?.conteudo.selecao_classificada_id ?? ''),
    }
  }

  artilheiroId.value = String(encontrarAposta('artilheiro')?.conteudo.jogador_id ?? '')
  palpitesFinais.value.campeao = String(encontrarAposta('campeao')?.conteudo.selecao_id ?? '')
  palpitesFinais.value.vice_campeao = String(encontrarAposta('vice_campeao')?.conteudo.selecao_id ?? '')
  palpitesFinais.value.terceiro_colocado = String(encontrarAposta('terceiro_colocado')?.conteudo.selecao_id ?? '')
}

async function carregarDados() {
  carregando.value = true
  try {
    const [respostaTorneio, respostaCupom, respostaApostas] = await Promise.all([
      requisicaoApi<{ torneio: Torneio }>('/torneio'),
      requisicaoApi<{ cupom: Cupom }>(`/cupons/${rota.params.id}`),
      requisicaoApi<{ apostas: Aposta[] }>(`/cupons/${rota.params.id}/apostas`),
    ])
    torneio.value = respostaTorneio.torneio
    cupom.value = respostaCupom.cupom
    apostas.value = respostaApostas.apostas
    preencherFormulario()
  } catch {
    erro.value = 'Falha ao carregar dados do cupom.'
  } finally {
    carregando.value = false
  }
}

async function salvar(apostasParaSalvar: Record<string, unknown>[]) {
  mensagem.value = ''
  erro.value = ''
  if (!apostasParaSalvar.length) {
    mostrar('erro', 'Preencha pelo menos um palpite antes de salvar.')
    return
  }
  try {
    await requisicaoApi(`/cupons/${rota.params.id}/apostas/lote`, {
      metodo: 'POST',
      corpo: { apostas: apostasParaSalvar },
    })
    mostrar('sucesso', 'Apostas salvas com sucesso!')
    await carregarDados()
  } catch (error) {
    mostrar('erro', error instanceof Error ? error.message : 'Falha ao salvar apostas.')
  }
}

async function salvarPlacaresGrupos() {
  await salvar(
    jogosGrupos.value
      .filter((j) => placaresGrupos.value[j.id].placar_mandante !== '' && placaresGrupos.value[j.id].placar_visitante !== '')
      .map((j) => ({
        tipo: 'placar_jogo_grupos',
        jogo_id: j.id,
        placar_mandante: Number(placaresGrupos.value[j.id].placar_mandante),
        placar_visitante: Number(placaresGrupos.value[j.id].placar_visitante),
      })),
  )
}

async function salvarClassificacao() {
  if (!torneio.value) return
  await salvar(
    torneio.value.grupos
      .filter((g) => classificacaoGrupos.value[g.id].primeiro !== '' && classificacaoGrupos.value[g.id].segundo !== '')
      .map((g) => ({
        tipo: 'classificacao_grupo',
        torneio_id: torneio.value?.id,
        grupo_id: g.id,
        primeiro_colocado_id: Number(classificacaoGrupos.value[g.id].primeiro),
        segundo_colocado_id: Number(classificacaoGrupos.value[g.id].segundo),
      })),
  )
}

async function salvarArtilheiro() {
  if (!torneio.value || !artilheiroId.value) return
  await salvar([{ tipo: 'artilheiro', torneio_id: torneio.value.id, jogador_id: Number(artilheiroId.value) }])
}

async function salvarMataMata() {
  await salvar(
    jogosEliminatorios.value
      .filter((j) =>
        placaresEliminatorios.value[j.id].placar_mandante !== '' &&
        placaresEliminatorios.value[j.id].placar_visitante !== '' &&
        placaresEliminatorios.value[j.id].selecao_classificada_id !== '',
      )
      .map((j) => ({
        tipo: 'placar_jogo_eliminatoria',
        jogo_id: j.id,
        placar_mandante: Number(placaresEliminatorios.value[j.id].placar_mandante),
        placar_visitante: Number(placaresEliminatorios.value[j.id].placar_visitante),
        selecao_classificada_id: Number(placaresEliminatorios.value[j.id].selecao_classificada_id),
      })),
  )
}

async function salvarPalpitesFinais() {
  if (!torneio.value) return
  await salvar(
    [
      ['campeao', palpitesFinais.value.campeao],
      ['vice_campeao', palpitesFinais.value.vice_campeao],
      ['terceiro_colocado', palpitesFinais.value.terceiro_colocado],
    ]
      .filter(([, valor]) => valor !== '')
      .map(([tipo, valor]) => ({ tipo, torneio_id: torneio.value?.id, selecao_id: Number(valor) })),
  )
}

async function carregarRanking() {
  if (!torneioStore.torneio) return
  carregandoRanking.value = true
  try {
    const resposta = await requisicaoApi<{ ranking: RankingItem[] }>(`/torneios/${torneioStore.torneio.id}/ranking`)
    ranking.value = resposta.ranking
  } catch {
    // Silencioso
  } finally {
    carregandoRanking.value = false
  }
}

onMounted(async () => {
  await Promise.all([carregarDados(), torneioStore.carregar()])
  carregarRanking()
})
</script>
