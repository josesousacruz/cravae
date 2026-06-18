<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition-opacity duration-300"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition-opacity duration-200"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div v-if="aberto" class="fixed inset-0 z-50 bg-black/50" @click="$emit('fechar')" />
    </Transition>

    <Transition
      enter-active-class="transition-transform duration-300 ease-in-out"
      enter-from-class="translate-x-full"
      enter-to-class="translate-x-0"
      leave-active-class="transition-transform duration-300 ease-in-out"
      leave-from-class="translate-x-0"
      leave-to-class="translate-x-full"
    >
      <div
        v-if="aberto"
        class="fixed top-0 right-0 z-50 h-full w-[280px] border-l border-border bg-bg-card flex flex-col"
      >
        <!-- Header -->
        <div class="flex items-center justify-between border-b border-border px-4 py-4">
          <span class="text-sm font-semibold text-text">Menu</span>
          <button
            type="button"
            class="rounded-lg p-1.5 text-text-muted transition-colors hover:bg-bg-card-hover hover:text-text"
            @click="$emit('fechar')"
          >
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- User info -->
        <div v-if="autenticacao.estaAutenticado" class="border-b border-border px-4 py-4">
          <div class="flex items-center gap-3">
            <AvatarIniciais :nome="autenticacao.nome" :foto="autenticacao.fotoUrl" />
            <div class="min-w-0">
              <p class="truncate text-sm font-medium text-text">{{ autenticacao.nome }}</p>
              <p class="truncate text-xs text-text-muted">{{ autenticacao.email }}</p>
            </div>
          </div>
        </div>

        <!-- Nav links -->
        <nav class="flex-1 overflow-y-auto px-2 py-3">
          <RouterLink
            to="/boloes"
            class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-text-secondary transition-colors hover:bg-bg-card-hover hover:text-text"
            active-class="!bg-primary-dim !text-primary"
            @click="$emit('fechar')"
          >
            Bolões
          </RouterLink>
          <template v-if="autenticacao.estaAutenticado">
            <RouterLink
              to="/painel"
              class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-text-secondary transition-colors hover:bg-bg-card-hover hover:text-text"
              active-class="!bg-primary-dim !text-primary"
              @click="$emit('fechar')"
            >
              Painel
            </RouterLink>
            <RouterLink
              to="/ranking"
              class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-text-secondary transition-colors hover:bg-bg-card-hover hover:text-text"
              active-class="!bg-primary-dim !text-primary"
              @click="$emit('fechar')"
            >
              Ranking
            </RouterLink>
            <RouterLink
              to="/perfil"
              class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-text-secondary transition-colors hover:bg-bg-card-hover hover:text-text"
              active-class="!bg-primary-dim !text-primary"
              @click="$emit('fechar')"
            >
              Perfil
            </RouterLink>
            <RouterLink
              v-if="autenticacao.eAdministrador"
              to="/admin"
              class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-text-secondary transition-colors hover:bg-bg-card-hover hover:text-text"
              active-class="!bg-primary-dim !text-primary"
              @click="$emit('fechar')"
            >
              Admin
            </RouterLink>
          </template>

          <template v-else>
            <button
              type="button"
              class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-text-secondary transition-colors hover:bg-bg-card-hover hover:text-text"
              @click="$emit('fechar'); emitirAuth('entrar')"
            >
              Entrar
            </button>
            <button
              type="button"
              class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-primary transition-colors hover:bg-primary-dim"
              @click="$emit('fechar'); emitirAuth('cadastro')"
            >
              Criar conta
            </button>
          </template>
        </nav>

        <!-- Footer -->
        <div v-if="autenticacao.estaAutenticado" class="border-t border-border px-2 py-3">
          <button
            type="button"
            class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-text-muted transition-colors hover:bg-bg-card-hover hover:text-danger"
            @click="sair"
          >
            Sair
          </button>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
import { watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { usarAutenticacaoStore } from '../stores/autenticacao'
import AvatarIniciais from './AvatarIniciais.vue'

const props = defineProps<{
  aberto: boolean
}>()

const emit = defineEmits<{
  fechar: []
  abrirModalAuth: [tab: 'entrar' | 'cadastro']
}>()

const autenticacao = usarAutenticacaoStore()
const router = useRouter()
const route = useRoute()

function emitirAuth(tab: 'entrar' | 'cadastro') {
  emit('abrirModalAuth', tab)
}

async function sair() {
  emit('fechar')
  await autenticacao.sair()
  router.push('/')
}

// Close on route change
watch(() => route.path, () => {
  if (props.aberto) {
    emit('fechar')
  }
})

// Body scroll lock
watch(() => props.aberto, (aberto) => {
  document.body.style.overflow = aberto ? 'hidden' : ''
})
</script>
