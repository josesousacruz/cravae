<template>
  <div class="min-h-screen flex flex-col">
    <header class="sticky top-0 z-50 border-b border-border bg-bg/90 backdrop-blur-md">
      <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-3 sm:px-6">
        <RouterLink to="/" class="flex items-center gap-2.5">
          <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary text-sm font-bold text-bg">
            IW
          </span>
          <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-primary">Inter World Cup</p>
            <p class="text-[11px] text-text-muted">Copa 2026</p>
          </div>
        </RouterLink>

        <nav class="flex items-center gap-1">
          <RouterLink
            v-for="link in linksVisiveis"
            :key="link.to"
            :to="link.to"
            class="rounded-lg px-3 py-2 text-sm font-medium text-text-secondary transition-colors hover:bg-bg-card hover:text-text"
            active-class="!bg-primary-dim !text-primary"
          >
            {{ link.nome }}
          </RouterLink>

          <button
            v-if="autenticacao.estaAutenticado"
            class="ml-2 rounded-lg px-3 py-2 text-sm font-medium text-text-muted transition-colors hover:bg-bg-card hover:text-danger"
            type="button"
            @click="sair"
          >
            Sair
          </button>
        </nav>
      </div>
    </header>

    <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-6 sm:px-6">
      <RouterView />
    </main>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { RouterLink, RouterView, useRouter } from 'vue-router'
import { usarAutenticacaoStore } from './stores/autenticacao'

const autenticacao = usarAutenticacaoStore()
const router = useRouter()

const linksVisiveis = computed(() => {
  const links = [{ to: '/', nome: 'Inicio' }, { to: '/ranking', nome: 'Ranking' }]

  if (!autenticacao.estaAutenticado) {
    links.push({ to: '/entrar', nome: 'Entrar' })
    links.push({ to: '/cadastro', nome: 'Cadastro' })
  } else {
    links.push({ to: '/painel', nome: 'Meus Cupons' })
    if (autenticacao.eAdministrador) {
      links.push({ to: '/admin', nome: 'Admin' })
    }
  }

  return links
})

async function sair() {
  await autenticacao.sair()
  router.push('/')
}

onMounted(() => {
  autenticacao.carregarUsuario()
})
</script>
