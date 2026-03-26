<template>
  <div
    :class="[
      'flex items-center justify-center rounded-full bg-primary text-bg font-bold',
      tamanho === 'sm' ? 'h-8 w-8 text-xs' : 'h-9 w-9 text-sm',
    ]"
  >
    {{ iniciais }}
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(defineProps<{
  nome: string
  tamanho?: 'sm' | 'md'
}>(), {
  tamanho: 'md',
})

const iniciais = computed(() => {
  const partes = props.nome.trim().split(/\s+/)
  if (partes.length === 0 || !partes[0]) return '??'
  if (partes.length === 1) {
    return partes[0].substring(0, 2).toUpperCase()
  }
  return (partes[0][0] + partes[partes.length - 1][0]).toUpperCase()
})
</script>
