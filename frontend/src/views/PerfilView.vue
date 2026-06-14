<template>
  <div class="mx-auto max-w-2xl">
    <div class="mb-6">
      <p class="text-xs font-semibold uppercase tracking-wider text-primary">Minha conta</p>
      <h1 class="mt-2 text-2xl font-bold text-text">Perfil</h1>
      <p class="mt-2 text-sm text-text-secondary">
        Mantenha seus dados atualizados para gerar pagamentos Pix e liberar seus cupons.
      </p>
    </div>

    <form class="rounded-2xl border border-border bg-bg-card p-6 sm:p-8" @submit.prevent="salvar">
      <div class="grid gap-4 sm:grid-cols-2">
        <label class="block sm:col-span-2">
          <span class="mb-1.5 block text-sm font-medium text-text-secondary">Nome</span>
          <input v-model="form.nome" type="text" autocomplete="name" required />
        </label>

        <label class="block">
          <span class="mb-1.5 block text-sm font-medium text-text-secondary">E-mail</span>
          <input :value="autenticacao.email" type="email" disabled class="opacity-70" />
        </label>

        <label class="block">
          <span class="mb-1.5 block text-sm font-medium text-text-secondary">Telefone</span>
          <input v-model="form.telefone" type="tel" autocomplete="tel" required />
        </label>

        <label class="block sm:col-span-2">
          <span class="mb-1.5 block text-sm font-medium text-text-secondary">CPF/CNPJ</span>
          <input
            v-model="form.cpf_cnpj"
            type="text"
            inputmode="numeric"
            autocomplete="off"
            placeholder="Somente numeros"
            required
          />
          <span class="mt-1.5 block text-xs text-text-muted">O documento e usado para criar o cliente pagador no Asaas.</span>
        </label>
      </div>

      <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
        <RouterLink
          to="/painel"
          class="inline-flex items-center justify-center rounded-xl border border-border px-4 py-2.5 text-sm font-semibold text-text-secondary transition-colors hover:bg-bg-card-hover hover:text-text"
        >
          Cancelar
        </RouterLink>
        <button
          type="submit"
          :disabled="autenticacao.carregando"
          class="rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-bg transition-colors hover:bg-primary-hover disabled:cursor-not-allowed disabled:opacity-50"
        >
          {{ autenticacao.carregando ? 'Salvando...' : 'Salvar perfil' }}
        </button>
      </div>
    </form>
  </div>
</template>

<script setup lang="ts">
import { onMounted, reactive, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { usarAutenticacaoStore } from '../stores/autenticacao'
import { useToast } from '../composables/useToast'

const autenticacao = usarAutenticacaoStore()
const { mostrar } = useToast()

const form = reactive({
  nome: '',
  telefone: '',
  cpf_cnpj: '',
})

function preencherFormulario() {
  form.nome = autenticacao.nome === 'Visitante' ? '' : autenticacao.nome
  form.telefone = autenticacao.telefone ?? ''
  form.cpf_cnpj = autenticacao.cpfCnpj ?? ''
}

onMounted(preencherFormulario)

watch(() => [autenticacao.nome, autenticacao.telefone, autenticacao.cpfCnpj], preencherFormulario)

async function salvar() {
  try {
    await autenticacao.atualizarPerfil({
      nome: form.nome,
      telefone: form.telefone,
      cpf_cnpj: form.cpf_cnpj,
    })
    mostrar('sucesso', 'Perfil atualizado com sucesso.')
  } catch (error) {
    mostrar('erro', error instanceof Error ? error.message : 'Nao foi possivel atualizar o perfil.')
  }
}
</script>
