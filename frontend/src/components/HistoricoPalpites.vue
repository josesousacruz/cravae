<template>
  <div class="space-y-4">
    <div class="rounded-2xl border border-border bg-bg-card p-5">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 class="text-lg font-bold">Histórico de palpites</h2>
          <p class="mt-1 text-sm text-text-secondary">
            Tudo que você criou, editou ou removeu neste cupom, com o palpite antes e depois de cada mudança.
          </p>
        </div>
        <span class="rounded-full border border-primary/30 bg-primary/10 px-3 py-1 text-xs text-primary">
          {{ total }} registro{{ total === 1 ? '' : 's' }}
        </span>
      </div>

      <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <label class="block">
          <span class="mb-1 block text-xs text-text-muted">De (dia)</span>
          <input v-model="filtros.de" type="date" class="w-full [color-scheme:dark]" />
        </label>
        <label class="block">
          <span class="mb-1 block text-xs text-text-muted">Até (dia)</span>
          <input v-model="filtros.ate" type="date" class="w-full [color-scheme:dark]" />
        </label>
        <label class="block">
          <span class="mb-1 block text-xs text-text-muted">Ação</span>
          <select v-model="filtros.acao" class="w-full">
            <option value="">Todas</option>
            <option value="criada">Criada</option>
            <option value="editada">Editada</option>
            <option value="removida">Removida</option>
          </select>
        </label>
        <label class="block">
          <span class="mb-1 block text-xs text-text-muted">Escopo</span>
          <select v-model="filtros.escopo" class="w-full">
            <option value="todos">Todos</option>
            <option value="orfaos">Só palpites removidos</option>
            <option value="vinculados">Só vinculados a jogo</option>
          </select>
        </label>
      </div>

      <div class="mt-4 flex flex-wrap items-center gap-3">
        <label class="flex cursor-pointer items-center gap-2 text-sm text-text-secondary">
          <input v-model="filtros.apenas_mudancas" type="checkbox" class="!w-auto" />
          Somente mudanças reais
        </label>
        <button
          type="button"
          class="ml-auto rounded-lg bg-primary px-4 py-2 text-sm font-medium text-bg transition hover:bg-primary-hover disabled:opacity-50"
          :disabled="carregando"
          @click="buscar()"
        >
          {{ carregando ? 'Buscando...' : 'Buscar' }}
        </button>
      </div>
    </div>

    <p v-if="erro" class="rounded-lg bg-danger/10 px-3 py-2 text-sm text-danger">{{ erro }}</p>

    <div v-if="carregando" class="rounded-2xl border border-border bg-bg-card py-10 text-center text-sm text-text-muted">
      Carregando...
    </div>
    <div v-else-if="!logs.length" class="rounded-2xl border border-dashed border-border bg-bg-card py-10 text-center text-sm text-text-muted">
      Nenhum registro encontrado para os filtros.
    </div>
    <div v-else class="overflow-x-auto rounded-2xl border border-border bg-bg-card">
      <table class="w-full min-w-[640px] text-left text-sm">
        <thead>
          <tr class="border-b border-border text-xs uppercase tracking-wider text-text-muted">
            <th class="px-4 py-3">Quando (Brasília)</th>
            <th class="px-4 py-3">Jogo</th>
            <th class="px-4 py-3">Ação</th>
            <th class="px-4 py-3">Antes → Depois</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="log in logs" :key="log.id" class="border-b border-border/50 align-top" :class="log.mudou ? '' : 'opacity-50'">
            <td class="whitespace-nowrap px-4 py-3 font-mono text-xs">{{ log.quando_brt }}</td>
            <td class="px-4 py-3">
              <template v-if="log.jogo">
                <p>{{ log.jogo.nome }}</p>
                <p class="text-xs text-text-muted">{{ log.jogo.data_hora_inicio }}</p>
              </template>
              <span v-else-if="log.orfao" class="rounded-full bg-warning/15 px-2 py-0.5 text-[10px] font-medium text-warning">
                palpite removido
              </span>
              <span v-else class="text-xs text-text-muted">{{ log.tipo ?? '—' }}</span>
            </td>
            <td class="px-4 py-3">
              <span
                class="rounded-full px-2 py-0.5 text-[10px] font-medium"
                :class="log.acao === 'removida' ? 'bg-danger/15 text-danger' : log.acao === 'criada' ? 'bg-primary/15 text-primary' : 'bg-bg-input text-text-secondary'"
              >
                {{ log.acao }}
              </span>
              <p v-if="!log.mudou" class="mt-1 text-[10px] text-text-muted">sem mudança</p>
            </td>
            <td class="px-4 py-3 text-xs">
              <p class="text-text-muted">{{ log.resumo_antes ?? 'sem palpite' }}</p>
              <p class="mt-0.5 font-medium text-text">→ {{ log.resumo_depois ?? 'sem palpite' }}</p>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="totalPaginas > 1" class="flex items-center justify-center gap-3">
      <button
        type="button"
        class="rounded-lg border border-border px-4 py-2 text-sm text-text-secondary transition hover:text-text disabled:opacity-40"
        :disabled="pagina <= 1 || carregando"
        @click="buscar(pagina - 1)"
      >
        Anterior
      </button>
      <span class="text-sm text-text-muted">Página {{ pagina }} de {{ totalPaginas }}</span>
      <button
        type="button"
        class="rounded-lg border border-border px-4 py-2 text-sm text-text-secondary transition hover:text-text disabled:opacity-40"
        :disabled="pagina >= totalPaginas || carregando"
        @click="buscar(pagina + 1)"
      >
        Próxima
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { requisicaoApi } from '../services/api'

const props = defineProps<{ cupomId: number }>()

type LogPalpite = {
  id: number
  quando_brt: string
  acao: string
  orfao: boolean
  mudou: boolean
  tipo: string | null
  jogo: { id: number; nome: string; data_hora_inicio: string | null } | null
  resumo_antes: string | null
  resumo_depois: string | null
}
type RespostaHistorico = { logs: LogPalpite[]; pagina: number; total_paginas: number; total: number }

const logs = ref<LogPalpite[]>([])
const carregando = ref(false)
const erro = ref('')
const pagina = ref(1)
const totalPaginas = ref(1)
const total = ref(0)
const filtros = ref({ de: '', ate: '', acao: '', escopo: 'todos', apenas_mudancas: true })

async function buscar(paginaAlvo = 1) {
  carregando.value = true
  erro.value = ''
  try {
    const params = new URLSearchParams()
    if (filtros.value.de) params.set('de', filtros.value.de)
    if (filtros.value.ate) params.set('ate', filtros.value.ate)
    if (filtros.value.acao) params.set('acao', filtros.value.acao)
    if (filtros.value.escopo !== 'todos') params.set('escopo', filtros.value.escopo)
    if (filtros.value.apenas_mudancas) params.set('apenas_mudancas', '1')
    params.set('page', String(paginaAlvo))

    const resposta = await requisicaoApi<RespostaHistorico>(`/cupons/${props.cupomId}/logs-apostas?${params}`)
    logs.value = resposta.logs
    pagina.value = resposta.pagina
    totalPaginas.value = resposta.total_paginas
    total.value = resposta.total
  } catch (e) {
    erro.value = e instanceof Error ? e.message : 'Falha ao carregar o histórico.'
  } finally {
    carregando.value = false
  }
}

onMounted(() => {
  void buscar()
})
</script>
