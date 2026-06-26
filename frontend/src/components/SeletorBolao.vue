<template>
  <div v-if="store.lista.length > 1" class="relative">
    <button
      type="button"
      class="flex items-center gap-2 rounded-lg bg-bg-input px-3 py-1.5 text-sm font-medium text-text-secondary transition hover:text-text"
      @click="aberto = !aberto"
    >
      <span class="max-w-[10rem] truncate">{{ store.ativo()?.nome ?? 'Bolão' }}</span>
      <svg class="h-4 w-4 transition-transform" :class="{ 'rotate-180': aberto }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
      </svg>
    </button>

    <div v-if="aberto" class="absolute right-0 z-50 mt-1 w-56 rounded-xl border border-border bg-bg-card p-1 shadow-xl">
      <button
        v-for="b in store.lista"
        :key="b.id"
        type="button"
        class="block w-full rounded-lg px-3 py-2 text-left text-sm transition hover:bg-bg-card-hover"
        :class="b.id === store.ativoId ? 'text-primary' : 'text-text-secondary'"
        @click="selecionar(b.id)"
      >
        {{ b.nome }}
      </button>
    </div>
    <div v-if="aberto" class="fixed inset-0 z-40" @click="aberto = false" />
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { usarBolaoAtivoStore } from '../stores/bolaoAtivo'

const store = usarBolaoAtivoStore()
const aberto = ref(false)

onMounted(() => {
  if (store.lista.length === 0) store.carregar()
})

function selecionar(id: number) {
  store.definirAtivo(id)
  aberto.value = false
}
</script>
