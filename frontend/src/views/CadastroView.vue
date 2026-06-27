<template>
  <div class="mx-auto max-w-md">
    <div class="rounded-2xl border border-border bg-bg-card p-6 sm:p-8">
      <div class="mb-6 text-center">
        <span class="inline-block rounded-full bg-primary/20 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-primary">
          Cadastro
        </span>
        <h1 class="mt-3 text-2xl font-bold">Criar conta</h1>
      </div>

      <form class="space-y-4" @submit.prevent="cadastrar">
        <label class="block">
          <span class="mb-1.5 block text-sm font-medium text-text-secondary">Nome</span>
          <input v-model="nome" type="text" placeholder="Seu nome" />
        </label>

        <label class="block">
          <span class="mb-1.5 block text-sm font-medium text-text-secondary">E-mail</span>
          <input v-model="email" type="email" placeholder="seu@email.com" />
        </label>

        <label class="block">
          <span class="mb-1.5 block text-sm font-medium text-text-secondary">Telefone</span>
          <input v-model="telefone" type="tel" placeholder="11999998888" />
        </label>

        <label class="block">
          <span class="mb-1.5 block text-sm font-medium text-text-secondary">CPF/CNPJ</span>
          <input v-model="cpfCnpj" type="text" inputmode="numeric" placeholder="Somente numeros" />
        </label>

        <label class="block">
          <span class="mb-1.5 block text-sm font-medium text-text-secondary">Senha</span>
          <input v-model="senha" type="password" placeholder="Minimo de 8 caracteres" />
        </label>

        <label class="block">
          <span class="mb-1.5 block text-sm font-medium text-text-secondary">Confirmar senha</span>
          <input v-model="confirmacao" type="password" placeholder="Repita a senha" />
        </label>

        <button
          type="submit"
          :disabled="autenticacao.carregando"
          class="w-full rounded-lg bg-primary py-2.5 text-sm font-semibold text-bg transition-colors hover:bg-primary-hover disabled:opacity-50"
        >
          {{ autenticacao.carregando ? 'Cadastrando...' : 'Cadastrar' }}
        </button>
      </form>

      <p v-if="autenticacao.erro" class="mt-4 rounded-lg bg-danger/10 px-3 py-2 text-sm text-danger">
        {{ autenticacao.erro }}
      </p>

      <p class="mt-4 text-center text-sm text-text-muted">
        Ja tem conta?
        <RouterLink to="/entrar" class="font-medium text-primary hover:underline">Entrar</RouterLink>
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { usarAutenticacaoStore } from '../stores/autenticacao'

const nome = ref('')
const email = ref('')
const telefone = ref('')
const cpfCnpj = ref('')
const senha = ref('')
const confirmacao = ref('')
const roteador = useRouter()
const autenticacao = usarAutenticacaoStore()

async function cadastrar() {
  try {
    await autenticacao.cadastrar(nome.value, email.value, telefone.value, cpfCnpj.value, senha.value, confirmacao.value)
    roteador.push('/boloes')
  } catch {
    // erro tratado na store
  }
}
</script>
