<template>
  <div class="mx-auto max-w-md">
    <div class="rounded-2xl border border-border bg-bg-card p-6 sm:p-8">
      <div class="mb-6 text-center">
        <span class="inline-block rounded-full bg-primary/20 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-primary">
          Autenticacao
        </span>
        <h1 class="mt-3 text-2xl font-bold">Entrar</h1>
      </div>

      <form class="space-y-4" @submit.prevent="entrar">
        <label class="block">
          <span class="mb-1.5 block text-sm font-medium text-text-secondary">E-mail</span>
          <input v-model="email" type="email" placeholder="seu@email.com" />
        </label>

        <label class="block">
          <span class="mb-1.5 block text-sm font-medium text-text-secondary">Senha</span>
          <input v-model="senha" type="password" placeholder="Digite sua senha" />
        </label>

        <button
          type="submit"
          :disabled="autenticacao.carregando"
          class="w-full rounded-lg bg-primary py-2.5 text-sm font-semibold text-bg transition-colors hover:bg-primary-hover disabled:opacity-50"
        >
          {{ autenticacao.carregando ? 'Entrando...' : 'Entrar' }}
        </button>
      </form>

      <p v-if="autenticacao.erro" class="mt-4 rounded-lg bg-danger/10 px-3 py-2 text-sm text-danger">
        {{ autenticacao.erro }}
      </p>

      <div class="mt-6 rounded-lg bg-bg-input px-4 py-3 text-center">
        <p class="text-xs text-text-muted">Acesso de teste</p>
        <p class="mt-0.5 text-sm font-medium text-text-secondary">admin@interworldcup.local / 12345678</p>
      </div>

      <p class="mt-4 text-center text-sm text-text-muted">
        Nao tem conta?
        <RouterLink to="/cadastro" class="font-medium text-primary hover:underline">Cadastre-se</RouterLink>
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { usarAutenticacaoStore } from '../stores/autenticacao'

const email = ref('')
const senha = ref('')
const roteador = useRouter()
const autenticacao = usarAutenticacaoStore()

async function entrar() {
  if (!email.value || !senha.value) return

  try {
    await autenticacao.entrar(email.value, senha.value)
    roteador.push('/painel')
  } catch {
    // erro tratado na store
  }
}
</script>
