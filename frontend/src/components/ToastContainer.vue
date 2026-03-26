<template>
  <div class="fixed top-4 right-4 z-[60] flex flex-col gap-2">
    <TransitionGroup
      enter-active-class="transition-all duration-300 ease-out"
      enter-from-class="translate-x-full opacity-0"
      enter-to-class="translate-x-0 opacity-100"
      leave-active-class="transition-all duration-200 ease-in"
      leave-from-class="translate-x-0 opacity-100"
      leave-to-class="opacity-0 translate-x-4"
    >
      <div
        v-for="toast in toastsVisiveis"
        :key="toast.id"
        :class="[
          'flex items-center gap-3 rounded-lg px-4 py-3 shadow-lg min-w-[280px] max-w-sm',
          toast.tipo === 'sucesso' ? 'bg-primary/20 text-primary' : 'bg-danger/20 text-danger',
        ]"
      >
        <svg v-if="toast.tipo === 'sucesso'" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
        </svg>
        <svg v-else class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
        </svg>

        <span class="flex-1 text-sm">{{ toast.mensagem }}</span>

        <button
          type="button"
          class="shrink-0 rounded p-0.5 opacity-70 hover:opacity-100 transition-opacity"
          @click="remover(toast.id)"
        >
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>
    </TransitionGroup>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useToast } from '../composables/useToast'

const { toasts, remover } = useToast()

const toastsVisiveis = computed(() => toasts.value.slice(-3))
</script>
