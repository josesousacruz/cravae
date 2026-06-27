<template>
  <div class="rounded-2xl border border-border bg-bg-card p-6">
    <p class="text-xs uppercase tracking-wider text-text-muted mb-2">Torneio ativo</p>

    <template v-if="torneioStore.carregando">
      <div class="space-y-3">
        <div class="h-5 w-48 animate-pulse rounded bg-bg-input"></div>
        <div class="h-4 w-32 animate-pulse rounded bg-bg-input"></div>
        <div class="h-4 w-56 animate-pulse rounded bg-bg-input"></div>
      </div>
    </template>

    <template v-else-if="torneioStore.torneio">
      <h3 class="text-lg font-bold">
        {{ torneioStore.torneio.nome }} &mdash; {{ torneioStore.torneio.edicao }}
      </h3>

      <span
        class="mt-2 inline-block rounded-full px-2 py-0.5 text-xs uppercase tracking-wider"
        :class="
          torneioStore.torneio.status === 'publicado'
            ? 'bg-primary/20 text-primary'
            : 'bg-warning/20 text-warning'
        "
      >
        {{ torneioStore.torneio.status }}
      </span>

      <div class="mt-3 space-y-1 text-sm text-text-secondary">
        <p>{{ torneioStore.torneio.grupos.length }} grupos</p>
        <p>{{ torneioStore.torneio.fases.length }} fases</p>
        <p>{{ torneioStore.torneio.jogos.length }} jogos</p>
      </div>

      <p v-if="torneioStore.torneio.data_inicio" class="mt-2 text-sm text-text-secondary">
        Inicio: {{ new Date(torneioStore.torneio.data_inicio).toLocaleDateString('pt-BR', { timeZone: 'UTC' }) }}
      </p>
    </template>

    <template v-else-if="torneioStore.erro">
      <p class="text-sm text-danger">{{ torneioStore.erro }}</p>
    </template>

    <template v-else>
      <p class="text-sm text-text-muted">Nenhum torneio ativo no momento.</p>
    </template>
  </div>
</template>

<script setup lang="ts">
// InfoTorneio - card com dados do torneio ativo
import { usarTorneioStore } from '../stores/torneio'

const torneioStore = usarTorneioStore()
</script>
