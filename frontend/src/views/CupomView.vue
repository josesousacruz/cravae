<template>
  <div v-if="!torneio || !cupom" class="flex items-center justify-center py-20">
    <span class="text-text-muted">Carregando...</span>
  </div>

  <div v-else class="space-y-6">
    <!-- Header do cupom -->
    <section class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-primary-dim to-bg-card p-6 sm:p-8">
      <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
          <span class="inline-block rounded-full bg-primary/20 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-primary">
            Cupom {{ cupom.codigo }}
          </span>
          <h1 class="mt-3 text-2xl font-bold">Fazer Palpites</h1>
          <p class="mt-1 text-text-secondary">Preencha seus palpites e salve cada secao.</p>
        </div>
        <div class="rounded-lg bg-bg-input px-4 py-3 text-center">
          <p class="text-2xl font-bold text-primary">{{ cupom.pontuacao?.pontuacao_total ?? '0' }}</p>
          <p class="text-xs text-text-muted">PONTOS</p>
        </div>
      </div>
      <p v-if="mensagem" class="mt-3 rounded-lg bg-primary/10 px-3 py-2 text-sm text-primary">{{ mensagem }}</p>
      <p v-if="erro" class="mt-3 rounded-lg bg-danger/10 px-3 py-2 text-sm text-danger">{{ erro }}</p>
    </section>

    <!-- Tabs -->
    <div class="flex gap-1 overflow-x-auto rounded-xl bg-bg-card p-1">
      <button
        v-for="tab in tabs"
        :key="tab.id"
        @click="tabAtiva = tab.id"
        class="shrink-0 rounded-lg px-4 py-2 text-sm font-medium transition-colors"
        :class="tabAtiva === tab.id ? 'bg-primary text-bg' : 'text-text-secondary hover:text-text'"
      >
        {{ tab.nome }}
      </button>
    </div>

    <!-- Fase de grupos -->
    <section v-if="tabAtiva === 'grupos'" class="rounded-2xl border border-border bg-bg-card p-5">
      <h2 class="mb-4 text-lg font-bold">Fase de Grupos</h2>
      <p class="mb-4 text-sm text-text-muted">Informe o placar de cada jogo da fase de grupos.</p>

      <div class="space-y-3">
        <div
          v-for="jogo in jogosGrupos"
          :key="jogo.id"
          class="flex items-center gap-3 rounded-xl bg-bg-input p-3 sm:p-4"
        >
          <div class="flex flex-1 items-center justify-end gap-2 text-right">
            <span class="text-sm font-semibold">{{ jogo.selecao_mandante.sigla }}</span>
          </div>
          <input
            v-model="placaresGrupos[jogo.id].placar_mandante"
            type="number"
            min="0"
            class="!w-14"
            placeholder="-"
          />
          <span class="text-xs text-text-muted">x</span>
          <input
            v-model="placaresGrupos[jogo.id].placar_visitante"
            type="number"
            min="0"
            class="!w-14"
            placeholder="-"
          />
          <div class="flex flex-1 items-center gap-2">
            <span class="text-sm font-semibold">{{ jogo.selecao_visitante.sigla }}</span>
          </div>
        </div>
      </div>

      <button
        type="button"
        @click="salvarPlacaresGrupos"
        class="mt-5 w-full rounded-lg bg-primary py-2.5 text-sm font-semibold text-bg transition-colors hover:bg-primary-hover"
      >
        Salvar placares da fase de grupos
      </button>
    </section>

    <!-- Classificacao dos grupos -->
    <section v-if="tabAtiva === 'classificacao'" class="rounded-2xl border border-border bg-bg-card p-5">
      <h2 class="mb-4 text-lg font-bold">Classificados dos Grupos</h2>
      <p class="mb-4 text-sm text-text-muted">Selecione o primeiro e segundo colocado de cada grupo.</p>

      <div class="space-y-5">
        <div v-for="grupo in torneio.grupos" :key="grupo.id" class="rounded-xl bg-bg-input p-4">
          <h3 class="mb-3 text-sm font-bold text-primary">{{ grupo.nome }}</h3>
          <div class="grid gap-3 sm:grid-cols-2">
            <label class="block">
              <span class="mb-1.5 block text-xs text-text-muted">Primeiro colocado</span>
              <select v-model="classificacaoGrupos[grupo.id].primeiro">
                <option value="">Selecione</option>
                <option v-for="selecao in grupo.selecoes" :key="selecao.id" :value="String(selecao.id)">
                  {{ selecao.nome }}
                </option>
              </select>
            </label>
            <label class="block">
              <span class="mb-1.5 block text-xs text-text-muted">Segundo colocado</span>
              <select v-model="classificacaoGrupos[grupo.id].segundo">
                <option value="">Selecione</option>
                <option v-for="selecao in grupo.selecoes" :key="selecao.id" :value="String(selecao.id)">
                  {{ selecao.nome }}
                </option>
              </select>
            </label>
          </div>
        </div>
      </div>

      <button
        type="button"
        @click="salvarClassificacao"
        class="mt-5 w-full rounded-lg bg-primary py-2.5 text-sm font-semibold text-bg transition-colors hover:bg-primary-hover"
      >
        Salvar classificados dos grupos
      </button>
    </section>

    <!-- Mata-mata -->
    <section v-if="tabAtiva === 'matamata'" class="rounded-2xl border border-border bg-bg-card p-5">
      <h2 class="mb-4 text-lg font-bold">Mata-Mata</h2>
      <p class="mb-4 text-sm text-text-muted">
        Em um palpite de mata-mata, informe o placar mais quem avanca.
      </p>

      <div class="space-y-4">
        <div v-for="jogo in jogosEliminatorios" :key="jogo.id" class="rounded-xl bg-bg-input p-4">
          <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-primary">{{ jogo.fase.nome }}</p>
          <div class="flex items-center gap-3">
            <div class="flex flex-1 items-center justify-end gap-2 text-right">
              <span class="text-sm font-semibold">{{ jogo.selecao_mandante.sigla }}</span>
            </div>
            <input
              v-model="placaresEliminatorios[jogo.id].placar_mandante"
              type="number"
              min="0"
              class="!w-14"
              placeholder="-"
            />
            <span class="text-xs text-text-muted">x</span>
            <input
              v-model="placaresEliminatorios[jogo.id].placar_visitante"
              type="number"
              min="0"
              class="!w-14"
              placeholder="-"
            />
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

      <button
        type="button"
        @click="salvarMataMata"
        class="mt-5 w-full rounded-lg bg-primary py-2.5 text-sm font-semibold text-bg transition-colors hover:bg-primary-hover"
      >
        Salvar palpites mata-mata
      </button>
    </section>

    <!-- Artilheiro -->
    <section v-if="tabAtiva === 'finais'" class="space-y-6">
      <div class="rounded-2xl border border-border bg-bg-card p-5">
        <h2 class="mb-4 text-lg font-bold">Artilheiro</h2>
        <select v-model="artilheiroId">
          <option value="">Selecione o artilheiro</option>
          <option v-for="jogador in jogadores" :key="jogador.id" :value="String(jogador.id)">
            {{ jogador.nome }}
          </option>
        </select>
        <button
          type="button"
          @click="salvarArtilheiro"
          class="mt-4 w-full rounded-lg bg-primary py-2.5 text-sm font-semibold text-bg transition-colors hover:bg-primary-hover"
        >
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
              <option v-for="selecao in selecoes" :key="selecao.id" :value="String(selecao.id)">{{ selecao.nome }}</option>
            </select>
          </label>
          <label class="block">
            <span class="mb-1.5 block text-xs text-text-muted">Vice-campeao</span>
            <select v-model="palpitesFinais.vice_campeao">
              <option value="">Selecione</option>
              <option v-for="selecao in selecoes" :key="selecao.id" :value="String(selecao.id)">{{ selecao.nome }}</option>
            </select>
          </label>
          <label class="block">
            <span class="mb-1.5 block text-xs text-text-muted">Terceiro colocado</span>
            <select v-model="palpitesFinais.terceiro_colocado">
              <option value="">Selecione</option>
              <option v-for="selecao in selecoes" :key="selecao.id" :value="String(selecao.id)">{{ selecao.nome }}</option>
            </select>
          </label>
        </div>
        <button
          type="button"
          @click="salvarPalpitesFinais"
          class="mt-5 w-full rounded-lg bg-primary py-2.5 text-sm font-semibold text-bg transition-colors hover:bg-primary-hover"
        >
          Salvar palpites finais
        </button>
      </div>
    </section>

    <!-- Eventos de pontuacao -->
    <section v-if="cupom.eventos_pontuacao?.length" class="rounded-2xl border border-border bg-bg-card p-5">
      <h2 class="mb-4 text-lg font-bold">Eventos de Pontuacao</h2>
      <div class="space-y-2">
        <div
          v-for="evento in cupom.eventos_pontuacao"
          :key="evento.id"
          class="flex items-center justify-between rounded-lg bg-bg-input px-3.5 py-2.5"
        >
          <span class="text-sm">{{ evento.descricao }}</span>
          <span class="text-sm font-bold text-primary">+{{ evento.pontos }} pts</span>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { requisicaoApi } from '../services/api'
import type { Aposta, Cupom, Jogador, Selecao, Torneio } from '../tipos'

const rota = useRoute()
const torneio = ref<Torneio | null>(null)
const cupom = ref<Cupom | null>(null)
const apostas = ref<Aposta[]>([])
const mensagem = ref('')
const erro = ref('')
const tabAtiva = ref('grupos')

const tabs = [
  { id: 'grupos', nome: 'Fase de Grupos' },
  { id: 'classificacao', nome: 'Classificacao' },
  { id: 'matamata', nome: 'Mata-Mata' },
  { id: 'finais', nome: 'Finais & Artilheiro' },
]

const placaresGrupos = ref<Record<number, { placar_mandante: string; placar_visitante: string }>>({})
const placaresEliminatorios = ref<
  Record<number, { placar_mandante: string; placar_visitante: string; selecao_classificada_id: string }>
>({})
const classificacaoGrupos = ref<Record<number, { primeiro: string; segundo: string }>>({})
const artilheiroId = ref('')
const palpitesFinais = ref({
  campeao: '',
  vice_campeao: '',
  terceiro_colocado: '',
})

const jogosGrupos = computed(() => torneio.value?.jogos.filter((jogo) => jogo.fase.tipo === 'grupos') ?? [])
const jogosEliminatorios = computed(() => torneio.value?.jogos.filter((jogo) => jogo.fase.tipo !== 'grupos') ?? [])
const selecoes = computed<Selecao[]>(() => torneio.value?.grupos.flatMap((grupo) => grupo.selecoes) ?? [])
const jogadores = computed<Jogador[]>(() => selecoes.value.flatMap((selecao) => selecao.jogadores ?? []))

function encontrarAposta(tipo: string, referenciaId?: number) {
  return apostas.value.find((aposta) => {
    if (aposta.tipo !== tipo) return false
    if (tipo === 'classificacao_grupo') return aposta.grupo_id === referenciaId
    if (tipo === 'artilheiro') return true
    if (['campeao', 'vice_campeao', 'terceiro_colocado'].includes(tipo)) return true
    return aposta.jogo_id === referenciaId
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
  const [respostaTorneio, respostaCupom, respostaApostas] = await Promise.all([
    requisicaoApi<{ torneio: Torneio }>('/torneio'),
    requisicaoApi<{ cupom: Cupom }>(`/cupons/${rota.params.id}`),
    requisicaoApi<{ apostas: Aposta[] }>(`/cupons/${rota.params.id}/apostas`),
  ])

  torneio.value = respostaTorneio.torneio
  cupom.value = respostaCupom.cupom
  apostas.value = respostaApostas.apostas
  preencherFormulario()
}

async function salvar(apostasParaSalvar: Record<string, unknown>[]) {
  mensagem.value = ''
  erro.value = ''

  if (!apostasParaSalvar.length) {
    erro.value = 'Preencha pelo menos um palpite antes de salvar.'
    return
  }

  try {
    await requisicaoApi(`/cupons/${rota.params.id}/apostas/lote`, {
      metodo: 'POST',
      corpo: { apostas: apostasParaSalvar },
    })
    mensagem.value = 'Apostas salvas com sucesso!'
    await carregarDados()
  } catch (error) {
    erro.value = error instanceof Error ? error.message : 'Falha ao salvar apostas.'
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
      .filter(
        (j) =>
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

onMounted(() => {
  carregarDados()
})
</script>
