<template>
  <header class="sticky top-0 z-50 border-b border-border bg-bg-card">
    <div class="mx-auto flex h-14 sm:h-16 max-w-6xl items-center justify-between px-4 sm:px-6">
      <!-- Logo -->
      <RouterLink to="/" class="flex items-center gap-2.5">
        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary text-sm font-bold text-bg">
          IW
        </span>
        <div>
          <p class="text-xs font-semibold uppercase tracking-widest text-primary">Inter World Cup</p>
          <p class="text-[11px] text-text-muted">Copa 2026</p>
        </div>
      </RouterLink>

      <!-- Desktop nav -->
      <nav class="hidden sm:flex items-center gap-1">
        <template v-if="autenticacao.estaAutenticado">
          <RouterLink
            to="/painel"
            class="rounded-lg px-3 py-2 text-sm font-medium text-text-secondary transition-colors hover:bg-bg-card-hover hover:text-text"
            active-class="!bg-primary-dim !text-primary"
          >
            Painel
          </RouterLink>
          <RouterLink
            to="/ranking"
            class="rounded-lg px-3 py-2 text-sm font-medium text-text-secondary transition-colors hover:bg-bg-card-hover hover:text-text"
            active-class="!bg-primary-dim !text-primary"
          >
            Ranking
          </RouterLink>
          <RouterLink
            v-if="autenticacao.eAdministrador"
            to="/admin"
            class="rounded-lg px-3 py-2 text-sm font-medium text-text-secondary transition-colors hover:bg-bg-card-hover hover:text-text"
            active-class="!bg-primary-dim !text-primary"
          >
            Admin
          </RouterLink>

          <!-- User dropdown -->
          <div class="relative ml-2">
            <button
              type="button"
              class="flex items-center gap-2 rounded-lg px-2 py-1.5 transition-colors hover:bg-bg-card-hover"
              @click="dropdownAberto = !dropdownAberto"
            >
              <AvatarIniciais :nome="autenticacao.nome" tamanho="sm" />
              <span class="text-sm font-medium text-text">{{ autenticacao.nome }}</span>
              <svg class="h-4 w-4 text-text-muted transition-transform" :class="{ 'rotate-180': dropdownAberto }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
              </svg>
            </button>

            <Transition
              enter-active-class="transition duration-150 ease-out"
              enter-from-class="opacity-0 scale-95"
              enter-to-class="opacity-100 scale-100"
              leave-active-class="transition duration-100 ease-in"
              leave-from-class="opacity-100 scale-100"
              leave-to-class="opacity-0 scale-95"
            >
              <div
                v-if="dropdownAberto"
                class="absolute right-0 top-full mt-1 w-64 rounded-xl border border-border bg-bg-card p-4 shadow-xl"
              >
                <div class="mb-3 border-b border-border pb-3">
                  <p class="text-sm font-medium text-text">{{ autenticacao.nome }}</p>
                  <p class="text-xs text-text-muted">{{ autenticacao.email }}</p>
                  <p class="mt-1 text-xs text-text-muted">{{ autenticacao.telefone || 'Nao informado' }}</p>
                  <p class="mt-1 text-xs" :class="autenticacao.cpfCnpj ? 'text-text-muted' : 'text-warning'">
                    {{ autenticacao.cpfCnpj ? 'CPF/CNPJ informado' : 'CPF/CNPJ pendente' }}
                  </p>
                </div>
                <RouterLink
                  to="/perfil"
                  class="mb-1 block w-full rounded-lg px-3 py-2 text-left text-sm font-medium text-text-secondary transition-colors hover:bg-bg-card-hover hover:text-text"
                  @click="dropdownAberto = false"
                >
                  Editar perfil
                </RouterLink>
                <button
                  type="button"
                  class="w-full rounded-lg px-3 py-2 text-left text-sm font-medium text-text-muted transition-colors hover:bg-bg-card-hover hover:text-danger"
                  @click="sair"
                >
                  Sair
                </button>
              </div>
            </Transition>
          </div>
        </template>

        <template v-else>
          <button
            type="button"
            class="rounded-lg px-3 py-2 text-sm font-medium text-text-secondary transition-colors hover:bg-bg-card-hover hover:text-text"
            @click="$emit('abrirModalAuth', 'entrar')"
          >
            Entrar
          </button>
          <button
            type="button"
            class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-bg transition-colors hover:bg-primary-hover"
            @click="$emit('abrirModalAuth', 'cadastro')"
          >
            Cadastrar
          </button>
        </template>
      </nav>

      <!-- Mobile right -->
      <div class="flex sm:hidden items-center gap-2">
        <AvatarIniciais v-if="autenticacao.estaAutenticado" :nome="autenticacao.nome" tamanho="sm" />
        <button
          type="button"
          class="rounded-lg p-2 text-text-secondary transition-colors hover:bg-bg-card-hover hover:text-text"
          @click="$emit('toggleMenu')"
        >
          <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
          </svg>
        </button>
      </div>
    </div>
  </header>

  <!-- Click outside to close dropdown -->
  <div v-if="dropdownAberto" class="fixed inset-0 z-40" @click="dropdownAberto = false" />
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { usarAutenticacaoStore } from '../stores/autenticacao'
import AvatarIniciais from './AvatarIniciais.vue'

defineEmits<{
  abrirModalAuth: [tab: 'entrar' | 'cadastro']
  toggleMenu: []
}>()

const autenticacao = usarAutenticacaoStore()
const router = useRouter()
const dropdownAberto = ref(false)

async function sair() {
  dropdownAberto.value = false
  await autenticacao.sair()
  router.push('/')
}
</script>
