<template>
  <div class="mx-auto max-w-4xl px-4 py-8">
    <header class="mb-6">
      <h1 class="text-2xl font-black text-text">Bolões</h1>
      <p class="mt-1 text-sm text-text-muted">Escolha um bolão para participar.</p>
    </header>

    <div class="mb-5 flex gap-2">
      <button
        type="button"
        class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
        :class="aba === 'ativos' ? 'bg-primary/20 text-primary' : 'bg-bg-input text-text-muted hover:text-text-secondary'"
        @click="aba = 'ativos'"
      >
        Ativos ({{ boloes.ativos.length }})
      </button>
      <button
        type="button"
        class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
        :class="aba === 'encerrados' ? 'bg-primary/20 text-primary' : 'bg-bg-input text-text-muted hover:text-text-secondary'"
        @click="aba = 'encerrados'"
      >
        Encerrados ({{ boloes.encerrados.length }})
      </button>
    </div>

    <div v-if="carregando" class="py-12 text-center text-text-muted">Carregando bolões...</div>

    <template v-else>
      <div v-if="listaAtual.length" class="grid gap-4 sm:grid-cols-2">
        <BolaoCard v-for="b in listaAtual" :key="b.id" :bolao="b" />
      </div>
      <div v-else class="rounded-2xl border border-border bg-bg-card py-12 text-center text-text-muted">
        Nenhum bolão {{ aba === 'ativos' ? 'ativo' : 'encerrado' }} no momento.
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { requisicaoApi } from '../services/api'
import { useToast } from '../composables/useToast'
import type { RespostaBoloes } from '../tipos'
import BolaoCard from '../components/BolaoCard.vue'

const { mostrar } = useToast()

const boloes = ref<RespostaBoloes>({ ativos: [], encerrados: [] })
const carregando = ref(true)
const aba = ref<'ativos' | 'encerrados'>('ativos')

const listaAtual = computed(() => (aba.value === 'ativos' ? boloes.value.ativos : boloes.value.encerrados))

onMounted(async () => {
  try {
    boloes.value = await requisicaoApi<RespostaBoloes>('/boloes')
  } catch {
    mostrar('erro', 'Falha ao carregar bolões.')
  } finally {
    carregando.value = false
  }
})
</script>
