<template>
  <div class="rounded-2xl border border-border bg-bg-card p-6 transition-colors duration-150 hover:border-border-light">
    <div class="flex items-start justify-between">
      <div>
        <p class="text-xs font-mono uppercase tracking-wider text-text-muted">
          {{ cupom.codigo }}
        </p>
      </div>
      <span
        class="rounded-full px-2 py-0.5 text-xs uppercase tracking-wider"
        :class="
          cupom.status === 'ativo'
            ? 'bg-primary/20 text-primary'
            : 'bg-warning/20 text-warning'
        "
      >
        {{ cupom.status }}
      </span>
    </div>

    <div class="mt-4 flex items-baseline gap-2">
      <span class="text-3xl font-bold text-primary">
        {{ cupom.pontuacao?.pontuacao_total ?? '0' }}
      </span>
      <span class="text-xs text-text-muted">pontos</span>
    </div>

    <p class="mt-2 text-sm text-text-secondary">
      Placares exatos: {{ cupom.pontuacao?.quantidade_placares_exatos ?? 0 }}
    </p>

    <RouterLink
      :to="`/cupons/${cupom.id}`"
      class="mt-4 block w-full rounded-lg bg-primary/10 px-4 py-2 text-center text-sm font-medium text-primary transition hover:bg-primary/20"
    >
      Fazer Palpites
    </RouterLink>
  </div>
</template>

<script setup lang="ts">
import { RouterLink } from 'vue-router'
import type { Cupom } from '../tipos'

defineProps<{
  cupom: Cupom
}>()
</script>
