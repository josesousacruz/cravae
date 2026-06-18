<template>
  <div class="flex flex-col rounded-2xl border border-border bg-bg-card p-5">
    <div class="flex items-start justify-between gap-3">
      <div class="min-w-0">
        <h3 class="truncate text-base font-bold text-text">{{ bolao.nome }}</h3>
        <p class="text-xs text-text-muted">{{ bolao.edicao }}</p>
      </div>
      <span
        v-if="bolao.status === 'encerrado'"
        class="shrink-0 rounded-full bg-text-muted/15 px-2 py-0.5 text-[10px] font-medium text-text-muted"
      >
        Encerrado
      </span>
      <span
        v-else-if="bolao.compras_abertas"
        class="shrink-0 rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-medium text-primary"
      >
        Compra aberta
      </span>
      <span
        v-else
        class="shrink-0 rounded-full bg-warning/10 px-2 py-0.5 text-[10px] font-medium text-warning"
      >
        Compra fechada
      </span>
    </div>

    <div class="mt-4 flex items-center justify-between gap-3">
      <span class="text-sm text-text-secondary">
        Cupom <span class="font-bold text-text">{{ valorFormatado }}</span>
      </span>
      <RouterLink
        v-if="bolao.status === 'publicado' && bolao.compras_abertas"
        :to="{ name: 'checkout', query: { torneio: bolao.id } }"
        class="inline-flex items-center rounded-lg bg-primary px-3 py-1.5 text-xs font-bold text-bg transition hover:opacity-90"
      >
        Comprar cupom
      </RouterLink>
      <span v-else class="text-xs text-text-muted">Indisponível</span>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import type { Bolao } from '../tipos'

const props = defineProps<{ bolao: Bolao }>()

const valorFormatado = computed(() => {
  const numero = Number(props.bolao.valor_cupom)
  return Number.isFinite(numero)
    ? new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(numero)
    : 'R$ 0,00'
})
</script>
