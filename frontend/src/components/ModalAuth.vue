<template>
  <Teleport to="body">
    <!-- Overlay -->
    <Transition
      enter-active-class="transition-opacity duration-200"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition-opacity duration-200"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="visivel"
        class="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm"
        @click="fechar"
      />
    </Transition>

    <!-- Modal -->
    <Transition
      enter-active-class="transition-all duration-300 ease-out"
      enter-from-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
      enter-to-class="opacity-100 translate-y-0 sm:scale-100"
      leave-active-class="transition-all duration-200 ease-in"
      leave-from-class="opacity-100 translate-y-0 sm:scale-100"
      leave-to-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
    >
      <div
        v-if="visivel"
        ref="modalRef"
        class="fixed z-50 sm:top-20 sm:left-1/2 sm:-translate-x-1/2 sm:w-full sm:max-w-md sm:rounded-2xl bottom-0 left-0 right-0 sm:bottom-auto rounded-t-2xl bg-bg-card p-6 sm:p-8 shadow-2xl"
        @keydown.escape="fechar"
      >
        <!-- Close button -->
        <button
          type="button"
          class="absolute top-4 right-4 rounded-lg p-1.5 text-text-muted transition-colors hover:bg-bg-card-hover hover:text-text"
          @click="fechar"
        >
          <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>

        <!-- Tabs -->
        <div class="mb-6 flex border-b border-border">
          <button
            type="button"
            class="flex-1 pb-3 text-sm font-medium transition-colors"
            :class="tabAtiva === 'entrar' ? 'border-b-2 border-primary text-primary' : 'text-text-muted hover:text-text'"
            @click="tabAtiva = 'entrar'"
          >
            Entrar
          </button>
          <button
            type="button"
            class="flex-1 pb-3 text-sm font-medium transition-colors"
            :class="tabAtiva === 'cadastro' ? 'border-b-2 border-primary text-primary' : 'text-text-muted hover:text-text'"
            @click="tabAtiva = 'cadastro'"
          >
            Criar conta
          </button>
        </div>

        <!-- Form: Entrar -->
        <form v-if="tabAtiva === 'entrar'" class="space-y-4" @submit.prevent="submeterEntrar">
          <div>
            <label for="login-email" class="mb-1.5 block text-sm font-medium text-text">Email</label>
            <input
              id="login-email"
              v-model="formEntrar.email"
              type="email"
              placeholder="seu@email.com"
              required
              autocomplete="email"
            />
          </div>
          <div>
            <label for="login-senha" class="mb-1.5 block text-sm font-medium text-text">Senha</label>
            <input
              id="login-senha"
              v-model="formEntrar.senha"
              type="password"
              placeholder="Sua senha"
              required
              autocomplete="current-password"
            />
          </div>
          <button
            type="submit"
            class="w-full rounded-lg bg-primary py-2.5 text-sm font-semibold text-bg transition-colors hover:bg-primary-hover disabled:opacity-50 disabled:cursor-not-allowed"
            :disabled="processando"
          >
            {{ processando ? 'Entrando...' : 'Entrar' }}
          </button>
        </form>

        <!-- Form: Cadastro -->
        <form v-else class="space-y-4" @submit.prevent="submeterCadastro">
          <div>
            <label for="cadastro-nome" class="mb-1.5 block text-sm font-medium text-text">Nome</label>
            <input
              id="cadastro-nome"
              v-model="formCadastro.nome"
              type="text"
              placeholder="Seu nome completo"
              required
              autocomplete="name"
            />
          </div>
          <div>
            <label for="cadastro-email" class="mb-1.5 block text-sm font-medium text-text">Email</label>
            <input
              id="cadastro-email"
              v-model="formCadastro.email"
              type="email"
              placeholder="seu@email.com"
              required
              autocomplete="email"
            />
          </div>
          <div>
            <label for="cadastro-telefone" class="mb-1.5 block text-sm font-medium text-text">Telefone</label>
            <input
              id="cadastro-telefone"
              v-model="formCadastro.telefone"
              type="tel"
              placeholder="(11) 99999-9999"
              autocomplete="tel"
            />
          </div>
          <div>
            <label for="cadastro-senha" class="mb-1.5 block text-sm font-medium text-text">Senha</label>
            <input
              id="cadastro-senha"
              v-model="formCadastro.senha"
              type="password"
              placeholder="Minimo 8 caracteres"
              required
              autocomplete="new-password"
            />
          </div>
          <div>
            <label for="cadastro-confirmar" class="mb-1.5 block text-sm font-medium text-text">Confirmar senha</label>
            <input
              id="cadastro-confirmar"
              v-model="formCadastro.confirmarSenha"
              type="password"
              placeholder="Repita a senha"
              required
              autocomplete="new-password"
            />
          </div>
          <button
            type="submit"
            class="w-full rounded-lg bg-primary py-2.5 text-sm font-semibold text-bg transition-colors hover:bg-primary-hover disabled:opacity-50 disabled:cursor-not-allowed"
            :disabled="processando"
          >
            {{ processando ? 'Criando conta...' : 'Criar conta' }}
          </button>
        </form>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
import { onMounted, onUnmounted, reactive, ref, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import { usarAutenticacaoStore } from '../stores/autenticacao'
import { useToast } from '../composables/useToast'

const props = defineProps<{
  tabInicial: 'entrar' | 'cadastro'
}>()

const emit = defineEmits<{
  fechar: []
}>()

const autenticacao = usarAutenticacaoStore()
const router = useRouter()
const { mostrar } = useToast()

const visivel = ref(false)
const tabAtiva = ref<'entrar' | 'cadastro'>(props.tabInicial)
const processando = ref(false)
const modalRef = ref<HTMLElement | null>(null)

const formEntrar = reactive({
  email: '',
  senha: '',
})

const formCadastro = reactive({
  nome: '',
  email: '',
  telefone: '',
  senha: '',
  confirmarSenha: '',
})

function fechar() {
  visivel.value = false
  setTimeout(() => emit('fechar'), 200)
}

async function submeterEntrar() {
  processando.value = true
  try {
    await autenticacao.entrar(formEntrar.email, formEntrar.senha)
    mostrar('sucesso', 'Login realizado com sucesso.')
    emit('fechar')
    router.push({ name: 'painel' })
  } catch {
    mostrar('erro', 'Email ou senha incorretos. Verifique seus dados e tente novamente.')
  } finally {
    processando.value = false
  }
}

async function submeterCadastro() {
  processando.value = true
  try {
    await autenticacao.cadastrar(
      formCadastro.nome,
      formCadastro.email,
      formCadastro.telefone,
      formCadastro.senha,
      formCadastro.confirmarSenha,
    )
    mostrar('sucesso', 'Conta criada! Bem-vindo ao Inter World Cup.')
    emit('fechar')
    router.push({ name: 'painel' })
  } catch {
    mostrar('erro', autenticacao.erro || 'Nao foi possivel criar a conta. Tente novamente.')
  } finally {
    processando.value = false
  }
}

// Focus trap
function handleKeydown(e: KeyboardEvent) {
  if (e.key === 'Escape') {
    fechar()
    return
  }

  if (e.key === 'Tab' && modalRef.value) {
    const focusableElements = modalRef.value.querySelectorAll<HTMLElement>(
      'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])',
    )
    const firstEl = focusableElements[0]
    const lastEl = focusableElements[focusableElements.length - 1]

    if (e.shiftKey) {
      if (document.activeElement === firstEl) {
        e.preventDefault()
        lastEl?.focus()
      }
    } else {
      if (document.activeElement === lastEl) {
        e.preventDefault()
        firstEl?.focus()
      }
    }
  }
}

// Body scroll lock
let previousOverflow = ''

onMounted(() => {
  previousOverflow = document.body.style.overflow
  document.body.style.overflow = 'hidden'
  document.addEventListener('keydown', handleKeydown)
  nextTick(() => {
    visivel.value = true
  })
})

onUnmounted(() => {
  document.body.style.overflow = previousOverflow
  document.removeEventListener('keydown', handleKeydown)
})
</script>
