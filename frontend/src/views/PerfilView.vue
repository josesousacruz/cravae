<template>
  <div class="mx-auto max-w-2xl">
    <div class="mb-6">
      <p class="text-xs font-semibold uppercase tracking-wider text-primary">Minha conta</p>
      <h1 class="mt-2 text-2xl font-bold text-text">Perfil</h1>
      <p class="mt-2 text-sm text-text-secondary">
        Mantenha seus dados atualizados para gerar pagamentos Pix e liberar seus cupons.
      </p>
    </div>

    <div class="mb-6 flex items-center gap-4 rounded-2xl border border-border bg-bg-card p-6">
      <div class="relative h-20 w-20 shrink-0 overflow-hidden rounded-full border-2 border-primary/40">
        <img v-if="autenticacao.fotoUrl" :src="autenticacao.fotoUrl" alt="Foto de perfil" class="h-full w-full object-cover" />
        <div v-else class="flex h-full w-full items-center justify-center bg-bg-input text-2xl font-bold text-primary">
          {{ iniciais }}
        </div>
      </div>
      <div class="min-w-0 flex-1">
        <p class="text-sm font-semibold text-text">Foto de perfil</p>
        <p class="mt-0.5 text-xs text-text-muted">JPG, PNG ou WEBP, ate 4MB. Aparece no ranking.</p>
        <button
          type="button"
          :disabled="enviandoFoto"
          class="mt-2 inline-flex items-center justify-center rounded-lg border border-border px-3 py-1.5 text-xs font-semibold text-text-secondary transition-colors hover:bg-bg-card-hover hover:text-text disabled:cursor-not-allowed disabled:opacity-50"
          @click="inputFoto?.click()"
        >
          {{ enviandoFoto ? 'Enviando...' : 'Trocar foto' }}
        </button>
        <input ref="inputFoto" type="file" accept="image/png,image/jpeg,image/webp" class="hidden" @change="aoSelecionarFoto" />
      </div>
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
import { computed, onMounted, reactive, ref, watch } from 'vue'
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

const inputFoto = ref<HTMLInputElement | null>(null)
const enviandoFoto = ref(false)

const iniciais = computed(() => {
  const partes = (autenticacao.nome === 'Visitante' ? '' : autenticacao.nome).trim().split(/\s+/).filter(Boolean)
  if (!partes.length) return '?'
  if (partes.length === 1) return partes[0].substring(0, 2).toUpperCase()
  return (partes[0][0] + partes[partes.length - 1][0]).toUpperCase()
})

async function aoSelecionarFoto(evento: Event) {
  const alvo = evento.target as HTMLInputElement
  const arquivo = alvo.files?.[0]
  if (!arquivo) return

  enviandoFoto.value = true
  try {
    await autenticacao.atualizarFoto(arquivo)
    mostrar('sucesso', 'Foto atualizada com sucesso.')
  } catch (error) {
    mostrar('erro', error instanceof Error ? error.message : 'Nao foi possivel atualizar a foto.')
  } finally {
    enviandoFoto.value = false
    alvo.value = ''
  }
}

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
