<template>
  <div v-if="!torneio" class="flex items-center justify-center py-20">
    <span class="text-text-muted">Carregando...</span>
  </div>

  <div v-else class="mx-auto max-w-5xl space-y-6">
    <!-- Header -->
    <section class="rounded-2xl border border-border bg-bg-card p-6 sm:p-8">
      <p class="text-xs uppercase tracking-wider text-text-muted mb-2">Administracao</p>
      <h1 class="text-lg font-bold">{{ torneio.nome }} {{ torneio.edicao }}</h1>
      <p class="mt-1 text-sm text-text-secondary">Gerencie resultados, regras e dados do torneio.</p>
      <p v-if="mensagem" class="mt-3 rounded-lg bg-primary/10 px-3 py-2 text-sm text-primary">{{ mensagem }}</p>
      <p v-if="erro" class="mt-3 rounded-lg bg-danger/10 px-3 py-2 text-sm text-danger">{{ erro }}</p>
    </section>

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

    <!-- Regras de pontuacao -->
    <section v-if="tabAtiva === 'regras'" class="rounded-2xl border border-border bg-bg-card p-6">
      <h2 class="text-lg font-bold mb-4">Regras de Pontuacao</h2>
      <div class="space-y-3">
        <div
          v-for="regra in torneio.regras_pontuacao"
          :key="regra.id"
          class="flex items-center gap-3 rounded-xl bg-bg-input p-3"
        >
          <div class="min-w-0 flex-1">
            <p class="text-sm font-medium">{{ regra.nome }}</p>
            <p v-if="regra.descricao" class="text-xs text-text-muted">{{ regra.descricao }}</p>
          </div>
          <input v-model="pontosRegras[regra.id]" type="number" min="0" class="!w-20" />
          <button
            type="button"
            @click="salvarRegra(regra.id)"
            class="shrink-0 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-bg transition hover:bg-primary-hover"
          >
            Salvar
          </button>
        </div>
      </div>
    </section>

    <!-- Resultados dos jogos -->
    <section v-if="tabAtiva === 'jogos'" class="rounded-2xl border border-border bg-bg-card p-6">
      <h2 class="text-lg font-bold mb-4">Resultados dos Jogos</h2>
      <div class="space-y-4">
        <div v-for="jogo in torneio.jogos" :key="jogo.id" class="rounded-xl bg-bg-input p-4">
          <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-primary">
            {{ jogo.fase.nome }}
          </p>
          <div class="flex items-center gap-3">
            <div class="flex flex-1 items-center justify-end gap-2 text-right">
              <span class="text-sm font-semibold">{{ jogo.selecao_mandante.sigla }}</span>
            </div>
            <input v-model="resultadosJogos[jogo.id].placar_mandante" type="number" min="0" class="!w-14" placeholder="-" />
            <span class="text-xs text-text-muted">x</span>
            <input v-model="resultadosJogos[jogo.id].placar_visitante" type="number" min="0" class="!w-14" placeholder="-" />
            <div class="flex flex-1 items-center gap-2">
              <span class="text-sm font-semibold">{{ jogo.selecao_visitante.sigla }}</span>
            </div>
          </div>
          <div class="mt-3 flex items-end gap-3">
            <label class="block flex-1">
              <span class="mb-1.5 block text-xs text-text-muted">Classificado (mata-mata)</span>
              <select v-model="resultadosJogos[jogo.id].selecao_classificada_id">
                <option value="">Nenhum</option>
                <option :value="String(jogo.selecao_mandante.id)">{{ jogo.selecao_mandante.nome }}</option>
                <option :value="String(jogo.selecao_visitante.id)">{{ jogo.selecao_visitante.nome }}</option>
              </select>
            </label>
            <button
              type="button"
              @click="salvarResultadoJogo(jogo.id)"
              class="shrink-0 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-bg transition hover:bg-primary-hover"
            >
              Salvar jogo
            </button>
          </div>
        </div>
      </div>
    </section>

    <!-- Resultado final do torneio -->
    <section v-if="tabAtiva === 'torneio'" class="rounded-2xl border border-border bg-bg-card p-6">
      <h2 class="text-lg font-bold mb-4">Resultado Final do Torneio</h2>
      <div class="space-y-3">
        <label class="block">
          <span class="mb-1.5 block text-xs text-text-muted">Campeao</span>
          <select v-model="resultadoTorneio.campeao_selecao_id">
            <option value="">Selecione</option>
            <option v-for="selecao in selecoes" :key="selecao.id" :value="String(selecao.id)">{{ selecao.nome }}</option>
          </select>
        </label>
        <label class="block">
          <span class="mb-1.5 block text-xs text-text-muted">Vice-campeao</span>
          <select v-model="resultadoTorneio.vice_campeao_selecao_id">
            <option value="">Selecione</option>
            <option v-for="selecao in selecoes" :key="selecao.id" :value="String(selecao.id)">{{ selecao.nome }}</option>
          </select>
        </label>
        <label class="block">
          <span class="mb-1.5 block text-xs text-text-muted">Terceiro colocado</span>
          <select v-model="resultadoTorneio.terceiro_colocado_selecao_id">
            <option value="">Selecione</option>
            <option v-for="selecao in selecoes" :key="selecao.id" :value="String(selecao.id)">{{ selecao.nome }}</option>
          </select>
        </label>
        <label class="block">
          <span class="mb-1.5 block text-xs text-text-muted">Artilheiro</span>
          <select v-model="resultadoTorneio.artilheiro_jogador_id">
            <option value="">Selecione</option>
            <option v-for="jogador in jogadores" :key="jogador.id" :value="String(jogador.id)">{{ jogador.nome }}</option>
          </select>
        </label>
      </div>
      <button
        type="button"
        @click="salvarResultadoTorneioFn"
        class="mt-5 w-full rounded-lg bg-primary py-2.5 text-sm font-medium text-bg transition hover:bg-primary-hover"
      >
        Salvar resultado final
      </button>
    </section>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { requisicaoApi } from '../services/api'
import type { Jogador, Selecao, Torneio } from '../tipos'

const torneio = ref<Torneio | null>(null)
const mensagem = ref('')
const erro = ref('')
const tabAtiva = ref('jogos')
const pontosRegras = ref<Record<number, string>>({})
const resultadosJogos = ref<
  Record<number, { placar_mandante: string; placar_visitante: string; selecao_classificada_id: string }>
>({})
const resultadoTorneio = ref({
  campeao_selecao_id: '',
  vice_campeao_selecao_id: '',
  terceiro_colocado_selecao_id: '',
  artilheiro_jogador_id: '',
})

const tabs = [
  { id: 'jogos', nome: 'Resultados dos Jogos' },
  { id: 'regras', nome: 'Regras' },
  { id: 'torneio', nome: 'Resultado Final' },
]

const selecoes = computed<Selecao[]>(() => torneio.value?.grupos.flatMap((g) => g.selecoes) ?? [])
const jogadores = computed<Jogador[]>(() => selecoes.value.flatMap((s) => s.jogadores ?? []))

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

  resultadoTorneio.value = {
    campeao_selecao_id: String(torneio.value.resultado_torneio?.campeao_selecao_id ?? ''),
    vice_campeao_selecao_id: String(torneio.value.resultado_torneio?.vice_campeao_selecao_id ?? ''),
    terceiro_colocado_selecao_id: String(torneio.value.resultado_torneio?.terceiro_colocado_selecao_id ?? ''),
    artilheiro_jogador_id: String(torneio.value.resultado_torneio?.artilheiro_jogador_id ?? ''),
  }
}

async function carregarDados() {
  const resposta = await requisicaoApi<{ torneio: Torneio }>('/admin/dados')
  torneio.value = resposta.torneio
  preencherFormulario()
}

async function executarAcao(acao: () => Promise<void>) {
  mensagem.value = ''
  erro.value = ''
  try {
    await acao()
    mensagem.value = 'Salvo com sucesso!'
    await carregarDados()
  } catch (error) {
    erro.value = error instanceof Error ? error.message : 'Falha ao salvar.'
  }
}

async function salvarRegra(regraId: number) {
  await executarAcao(async () => {
    await requisicaoApi(`/admin/regras-pontuacao/${regraId}`, {
      metodo: 'PUT',
      corpo: { pontos: Number(pontosRegras.value[regraId] || 0) },
    })
  })
}

async function salvarResultadoJogo(jogoId: number) {
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
  })
}

async function salvarResultadoTorneioFn() {
  if (!torneio.value) return
  await executarAcao(async () => {
    await requisicaoApi(`/admin/torneios/${torneio.value?.id}/resultado`, {
      metodo: 'PUT',
      corpo: {
        campeao_selecao_id: resultadoTorneio.value.campeao_selecao_id ? Number(resultadoTorneio.value.campeao_selecao_id) : null,
        vice_campeao_selecao_id: resultadoTorneio.value.vice_campeao_selecao_id ? Number(resultadoTorneio.value.vice_campeao_selecao_id) : null,
        terceiro_colocado_selecao_id: resultadoTorneio.value.terceiro_colocado_selecao_id ? Number(resultadoTorneio.value.terceiro_colocado_selecao_id) : null,
        artilheiro_jogador_id: resultadoTorneio.value.artilheiro_jogador_id ? Number(resultadoTorneio.value.artilheiro_jogador_id) : null,
      },
    })
  })
}

onMounted(() => {
  carregarDados()
})
</script>
