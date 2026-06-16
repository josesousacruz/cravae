<template>
  <div
    :class="[
      'flex items-center justify-center overflow-hidden rounded-full bg-primary text-bg font-bold',
      tamanho === 'sm' ? 'h-8 w-8 text-xs' : 'h-9 w-9 text-sm',
    ]"
  >
    <img v-if="foto" :src="foto" :alt="nome" class="h-full w-full object-cover" />
    <span v-else>{{ iniciais }}</span>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(defineProps<{
  nome: string
  tamanho?: 'sm' | 'md'
  foto?: string | null
}>(), {
  tamanho: 'md',
  foto: null,
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
