<template>
  <div v-if="!torneio" class="flex items-center justify-center py-20">
    <span class="text-text-muted">Carregando...</span>
  </div>

  <div v-else class="mx-auto max-w-6xl space-y-6">
    <section class="rounded-2xl border border-border bg-bg-card p-6 sm:p-8">
      <p class="mb-2 text-xs uppercase tracking-wider text-text-muted">Administracao</p>
      <h1 class="text-lg font-bold">{{ torneio.nome }} {{ torneio.edicao }}</h1>
      <p class="mt-1 text-sm text-text-secondary">Lance resultados por fase e acompanhe o reprocessamento em segundo plano.</p>
      <p v-if="mensagem" class="mt-3 rounded-lg bg-primary/10 px-3 py-2 text-sm text-primary">{{ mensagem }}</p>
      <p v-if="erro" class="mt-3 rounded-lg bg-danger/10 px-3 py-2 text-sm text-danger">{{ erro }}</p>
    </section>

    <div class="flex overflow-x-auto border-b border-border">
      <button
        v-for="tab in tabs"
        :key="tab.id"
        class="whitespace-nowrap px-4 py-3 text-sm transition cursor-pointer"
        :class="tabAtiva === tab.id ? 'border-b-2 border-primary text-primary font-medium' : 'text-text-muted hover:text-text-secondary'"
        @click="tabAtiva = tab.id"
      >
        {{ tab.nome }}
      </button>
    </div>

    <section v-if="tabAtiva === 'jogos'" class="space-y-4">
      <div class="rounded-2xl border border-border bg-bg-card p-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h2 class="text-lg font-bold">Resultados dos Jogos</h2>
            <p class="mt-1 text-sm text-text-secondary">Selecione a fase, informe o placar e salve. Em empate no mata-mata, escolha o classificado.</p>
          </div>
          <span class="rounded-full border border-primary/30 bg-primary/10 px-3 py-1 text-xs text-primary">
            {{ jogosFiltrados.length }} jogo{{ jogosFiltrados.length === 1 ? '' : 's' }}
          </span>
        </div>

        <div class="mt-4 flex gap-2 overflow-x-auto pb-1">
          <button
            v-for="fase in fasesComJogos"
            :key="fase.id"
            type="button"
            class="whitespace-nowrap rounded-full px-3 py-1.5 text-xs font-medium transition"
            :class="faseSelecionadaId === fase.id ? 'bg-primary text-bg' : 'bg-bg-input text-text-muted hover:text-text'"
            @click="faseSelecionadaId = fase.id"
          >
            {{ fase.nome }}
          </button>
        </div>
      </div>

      <div class="grid gap-4">
        <div v-for="jogo in jogosFiltrados" :key="jogo.id" class="rounded-2xl border border-border bg-bg-card p-5">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
              <p class="text-xs font-semibold uppercase tracking-wider text-primary">
                {{ jogo.fase.nome }} <span class="text-text-muted">#{{ jogo.ordem_na_fase }}</span>
              </p>
              <p class="mt-1 text-xs text-text-muted">
                {{ jogo.grupo?.nome ?? 'Mata-mata' }}
              </p>
            </div>
            <button
              type="button"
              class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-bg transition hover:bg-primary-hover disabled:cursor-not-allowed disabled:opacity-60"
              :disabled="salvandoJogoId === jogo.id"
              @click="salvarResultadoJogo(jogo.id)"
            >
              {{ salvandoJogoId === jogo.id ? 'Salvando...' : 'Salvar resultado' }}
            </button>
          </div>

          <div class="mt-4 grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] sm:items-center">
            <div class="rounded-xl bg-bg-input p-3 text-center sm:text-right">
              <p class="text-xs text-text-muted">Mandante</p>
              <strong class="text-sm">{{ nomeMandanteAdmin(jogo) }}</strong>
            </div>

            <div class="flex items-center justify-center gap-2">
              <input v-model="resultadosJogos[jogo.id].placar_mandante" type="number" min="0" class="!w-16 text-center" placeholder="0" />
              <span class="text-xs font-medium text-text-muted">x</span>
              <input v-model="resultadosJogos[jogo.id].placar_visitante" type="number" min="0" class="!w-16 text-center" placeholder="0" />
            </div>

            <div class="rounded-xl bg-bg-input p-3 text-center sm:text-left">
              <p class="text-xs text-text-muted">Visitante</p>
              <strong class="text-sm">{{ nomeVisitanteAdmin(jogo) }}</strong>
            </div>
          </div>

          <div v-if="jogoExigeClassificado(jogo)" class="mt-4 rounded-xl border border-primary/20 bg-primary/5 p-4">
            <label class="block">
              <span class="mb-1.5 block text-xs text-text-muted">Classificado nos penaltis</span>
              <select v-model="resultadosJogos[jogo.id].selecao_classificada_id">
                <option value="">Selecione o classificado</option>
                <option
                  v-for="selecao in opcoesClassificado(jogo)"
                  :key="selecao.id"
                  :value="String(selecao.id)"
                >
                  {{ selecao.nome }}
                </option>
              </select>
            </label>
          </div>
        </div>
      </div>
    </section>

    <section v-if="tabAtiva === 'regras'" class="rounded-2xl border border-border bg-bg-card p-6">
      <h2 class="mb-4 text-lg font-bold">Regras de Pontuacao</h2>
      <div class="space-y-3">
        <div
          v-for="regra in regrasPontuacaoVisiveis"
          :key="regra.id"
          class="flex flex-col gap-3 rounded-xl bg-bg-input p-4 sm:flex-row sm:items-center"
        >
          <div class="min-w-0 flex-1">
            <p class="text-sm font-medium">{{ regra.nome }}</p>
            <p v-if="regra.descricao" class="text-xs text-text-muted">{{ regra.descricao }}</p>
          </div>
          <input v-model="pontosRegras[regra.id]" type="number" min="0" class="!w-24" />
          <button
            type="button"
            class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-bg transition hover:bg-primary-hover disabled:cursor-not-allowed disabled:opacity-60"
            :disabled="salvandoRegraId === regra.id"
            @click="salvarRegra(regra.id)"
          >
            {{ salvandoRegraId === regra.id ? 'Salvando...' : 'Salvar regra' }}
          </button>
        </div>
      </div>
    </section>

    <section v-if="tabAtiva === 'torneio'" class="rounded-2xl border border-border bg-bg-card p-6">
      <h2 class="mb-4 text-lg font-bold">Resultado Final do Torneio</h2>
      <div class="grid gap-3 sm:grid-cols-3">
        <div class="rounded-xl bg-bg-input p-4">
          <span class="mb-1.5 block text-xs text-text-muted">Campeao</span>
          <strong class="text-sm">{{ podioDerivado.campeao ?? 'A definir' }}</strong>
        </div>
        <div class="rounded-xl bg-bg-input p-4">
          <span class="mb-1.5 block text-xs text-text-muted">Vice-campeao</span>
          <strong class="text-sm">{{ podioDerivado.vice ?? 'A definir' }}</strong>
        </div>
        <div class="rounded-xl bg-bg-input p-4">
          <span class="mb-1.5 block text-xs text-text-muted">Terceiro colocado</span>
          <strong class="text-sm">{{ podioDerivado.terceiro ?? 'A definir' }}</strong>
        </div>
      </div>

      <button
        type="button"
        class="mt-5 w-full rounded-lg bg-primary py-2.5 text-sm font-medium text-bg transition hover:bg-primary-hover disabled:cursor-not-allowed disabled:opacity-60"
        :disabled="salvandoResultadoTorneio"
        @click="salvarResultadoTorneioFn"
      >
        {{ salvandoResultadoTorneio ? 'Salvando...' : 'Sincronizar resultado final' }}
      </button>
    </section>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { requisicaoApi } from '../services/api'
import type { Torneio } from '../tipos'

const torneio = ref<Torneio | null>(null)
const mensagem = ref('')
const erro = ref('')
const tabAtiva = ref<'jogos' | 'regras' | 'torneio'>('jogos')
const faseSelecionadaId = ref<number | null>(null)
const pontosRegras = ref<Record<number, string>>({})
const resultadosJogos = ref<Record<number, { placar_mandante: string; placar_visitante: string; selecao_classificada_id: string }>>({})
const salvandoJogoId = ref<number | null>(null)
const salvandoRegraId = ref<number | null>(null)
const salvandoResultadoTorneio = ref(false)

const tabs = [
  { id: 'jogos' as const, nome: 'Resultados dos Jogos' },
  { id: 'regras' as const, nome: 'Regras' },
  { id: 'torneio' as const, nome: 'Resultado Final' },
]

const selecoes = computed(() => torneio.value?.grupos.flatMap((g) => g.selecoes) ?? [])
const regrasPontuacaoVisiveis = computed(() => torneio.value?.regras_pontuacao.filter((regra) => regra.chave !== 'artilheiro') ?? [])
const fasesComJogos = computed(() => {
  if (!torneio.value) return []
  return torneio.value.fases
    .filter((fase) => torneio.value?.jogos.some((jogo) => jogo.fase_id === fase.id))
    .sort((a, b) => a.ordem - b.ordem)
})
const jogosFiltrados = computed(() => {
  if (!torneio.value || !faseSelecionadaId.value) return []
  return torneio.value.jogos
    .filter((jogo) => jogo.fase_id === faseSelecionadaId.value)
    .sort((a, b) => a.ordem_na_fase - b.ordem_na_fase)
})
const podioDerivado = computed(() => {
  if (!torneio.value) {
    return { campeao: null, vice: null, terceiro: null }
  }

  const nomeSelecao = (id: number | null | undefined) => selecoes.value.find((selecao) => selecao.id === id)?.nome ?? null

  return {
    campeao: nomeSelecao(torneio.value.resultado_torneio?.campeao_selecao_id),
    vice: nomeSelecao(torneio.value.resultado_torneio?.vice_campeao_selecao_id),
    terceiro: nomeSelecao(torneio.value.resultado_torneio?.terceiro_colocado_selecao_id),
  }
})

function opcoesClassificado(jogo: Torneio['jogos'][number]) {
  if (jogo.fase.tipo === 'grupos') {
    return [jogo.selecao_mandante, jogo.selecao_visitante].filter((selecao): selecao is NonNullable<typeof selecao> => Boolean(selecao))
  }

  return jogo.participantes_admin ?? []
}

function nomeMandanteAdmin(jogo: Torneio['jogos'][number]) {
  if (jogo.fase.tipo === 'grupos') {
    return jogo.selecao_mandante?.nome ?? 'A definir'
  }

  return jogo.participantes_admin?.[0]?.nome ?? 'A definir'
}

function nomeVisitanteAdmin(jogo: Torneio['jogos'][number]) {
  if (jogo.fase.tipo === 'grupos') {
    return jogo.selecao_visitante?.nome ?? 'A definir'
  }

  return jogo.participantes_admin?.[1]?.nome ?? 'A definir'
}

function jogoExigeClassificado(jogo: Torneio['jogos'][number]) {
  if (jogo.fase.tipo === 'grupos') {
    return false
  }

  const resultado = resultadosJogos.value[jogo.id]
  return resultado
    && resultado.placar_mandante !== ''
    && resultado.placar_visitante !== ''
    && resultado.placar_mandante === resultado.placar_visitante
}

function preencherFormulario() {
  if (!torneio.value) return

  for (const regra of torneio.value.regras_pontuacao) {
    pontosRegras.value[regra.id] = String(regra.pontos)
  }

  for (const jogo of torneio.value.jogos) {
    resultadosJogos.value[jogo.id] = {
      placar_mandante: String(jogo.resultado?.placar_mandante ?? ''),
      placar_visitante: String(jogo.resultado?.placar_visitante ?? ''),
      selecao_classificada_id: String(jogo.resultado?.selecao_classificada_id ?? ''),
    }
  }

  if (!faseSelecionadaId.value || !fasesComJogos.value.some((fase) => fase.id === faseSelecionadaId.value)) {
    faseSelecionadaId.value = fasesComJogos.value[0]?.id ?? null
  }
}

async function carregarDados() {
  const resposta = await requisicaoApi<{ torneio: Torneio }>('/admin/dados')
  torneio.value = resposta.torneio
  preencherFormulario()
}

async function executarAcao(acao: () => Promise<void>, mensagemSucesso = 'Salvo com sucesso. Recalculo enviado para processamento.') {
  mensagem.value = ''
  erro.value = ''

  try {
    await acao()
    mensagem.value = mensagemSucesso
    await carregarDados()
  } catch (error) {
    erro.value = error instanceof Error ? error.message : 'Falha ao salvar.'
  }
}

async function salvarRegra(regraId: number) {
  salvandoRegraId.value = regraId
  await executarAcao(async () => {
    await requisicaoApi(`/admin/regras-pontuacao/${regraId}`, {
      metodo: 'PUT',
      corpo: { pontos: Number(pontosRegras.value[regraId] || 0) },
    })
  }, 'Regra salva. Recalculo enviado para processamento.')
  salvandoRegraId.value = null
}

async function salvarResultadoJogo(jogoId: number) {
  salvandoJogoId.value = jogoId
  await executarAcao(async () => {
    await requisicaoApi(`/admin/jogos/${jogoId}/resultado`, {
      metodo: 'PUT',
      corpo: {
        placar_mandante: Number(resultadosJogos.value[jogoId].placar_mandante || 0),
        placar_visitante: Number(resultadosJogos.value[jogoId].placar_visitante || 0),
        selecao_classificada_id: resultadosJogos.value[jogoId].selecao_classificada_id
          ? Number(resultadosJogos.value[jogoId].selecao_classificada_id)
          : null,
      },
    })
  }, 'Resultado salvo. Recalculo enviado para processamento.')
  salvandoJogoId.value = null
}

async function salvarResultadoTorneioFn() {
  if (!torneio.value) return
  const torneioId = torneio.value.id

  salvandoResultadoTorneio.value = true
  await executarAcao(async () => {
    await requisicaoApi(`/admin/torneios/${torneioId}/resultado`, {
      metodo: 'PUT',
      corpo: {},
    })
  }, 'Resultado final salvo. Recalculo enviado para processamento.')
  salvandoResultadoTorneio.value = false
}

onMounted(() => {
  void carregarDados()
})
</script>
