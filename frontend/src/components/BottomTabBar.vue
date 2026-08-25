<template>
  <nav class="fixed bottom-0 inset-x-0 z-40 border-t border-border bg-bg-card pb-[env(safe-area-inset-bottom)] sm:hidden">
    <div class="grid grid-cols-5">
      <RouterLink
        v-for="item in itens"
        :key="item.rota"
        :to="item.rota"
        class="relative flex flex-col items-center justify-center gap-1 px-2 py-2 text-[11px] font-medium transition-colors"
        :class="estaAtivo(item.rota) ? 'text-primary' : 'text-text-muted'"
      >
        <span v-if="estaAtivo(item.rota)" class="absolute inset-x-6 top-0 h-0.5 rounded-b-full bg-primary" />
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
          <path stroke-linecap="round" stroke-linejoin="round" :d="item.icone" />
        </svg>
        <span>{{ item.nome }}</span>
      </RouterLink>
    </div>
  </nav>
</template>

<script setup lang="ts">
import { RouterLink, useRoute } from 'vue-router'

const rota = useRoute()

const itens = [
  {
    nome: 'Início',
    rota: '/home',
    icone: 'M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75',
  },
  {
    nome: 'Palpites',
    rota: '/palpites',
    icone: 'M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125',
  },
  {
    nome: 'Ranking',
    rota: '/ranking',
    icone: 'M16.5 18.75h-9m9 0a3 3 0 013 3h-15a3 3 0 013-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872',
  },
  {
    nome: 'Resultados',
    rota: '/resultados',
    icone: 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z',
  },
  {
    nome: 'Perfil',
    rota: '/perfil',
    icone: 'M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z',
  },
]

function estaAtivo(caminho: string) {
  return rota.path === caminho || rota.path.startsWith(`${caminho}/`)
}
</script>
