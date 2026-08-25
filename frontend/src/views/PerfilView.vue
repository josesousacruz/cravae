<template>
  <div class="mx-auto max-w-2xl">
    <div class="mb-6">
      <p class="text-xs font-semibold uppercase tracking-wider text-primary">Minha conta</p>
      <h1 class="mt-2 text-2xl font-bold text-text">Perfil</h1>
      <p class="mt-2 text-sm text-text-secondary">
        Sua assinatura, seus dados e suas temporadas anteriores.
      </p>
    </div>

    <!-- Assinatura -->
    <div class="mb-6 rounded-2xl border border-border bg-bg-card p-6">
      <p class="text-xs font-semibold uppercase tracking-wider text-primary">Assinatura</p>
      <div v-if="carregandoAssinatura" class="mt-3 h-10 animate-pulse rounded-lg bg-bg-input" />
      <template v-else-if="assinatura && assinatura.status !== 'cancelada'">
        <div class="mt-3 flex flex-wrap items-center gap-2">
          <span
            class="rounded-full px-3 py-1 text-xs font-bold"
            :class="assinatura.status === 'ativa' ? 'bg-primary-dim text-primary' : 'bg-amber-100 text-amber-700'"
          >
            {{ assinatura.status === 'ativa' ? 'Ativa' : 'Pagamento pendente' }}
          </span>
          <span v-if="assinatura.fundador" class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-bold text-amber-700">Fundador</span>
        </div>
        <p class="mt-3 text-2xl font-black text-text">
          {{ formatarCentavos(assinatura.preco_centavos_contratado) }}<span class="text-sm font-medium text-text-muted">/mês</span>
        </p>
        <RouterLink to="/assinatura" class="mt-3 inline-block text-sm font-semibold text-primary underline transition hover:text-primary-hover">
          Gerenciar
        </RouterLink>
      </template>
      <RouterLink
        v-else
        to="/assinatura"
        class="mt-3 block rounded-xl bg-primary px-4 py-3 text-center text-sm font-bold text-white transition hover:bg-primary-hover"
      >
        Assinar por R$ 9,90/mês — fundador R$ 4,95 vitalício
      </RouterLink>
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
        <p class="mt-0.5 text-xs text-text-muted">JPG, PNG ou WEBP, até 4MB. Aparece no ranking.</p>
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
            placeholder="Somente números"
            required
          />
          <span class="mt-1.5 block text-xs text-text-muted">O documento e usado para criar o cliente pagador no Asaas.</span>
        </label>
      </div>

      <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
        <RouterLink
          to="/palpites"
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

    <!-- Temporadas anteriores -->
    <div v-if="temporadasAnteriores.length" class="mt-6 rounded-2xl border border-border bg-bg-card p-6">
      <p class="text-xs font-semibold uppercase tracking-wider text-primary">Temporadas anteriores</p>
      <div class="mt-3 space-y-2">
        <RouterLink
          v-for="temporada in temporadasAnteriores"
          :key="temporada.cupomId"
          :to="`/cupons/${temporada.cupomId}`"
          class="flex items-center justify-between gap-3 rounded-xl bg-bg-input px-4 py-3 transition-colors hover:bg-bg-card-hover"
        >
          <span class="text-sm font-medium text-text">{{ temporada.nome }}</span>
          <span class="shrink-0 text-sm font-bold text-primary tabular-nums">{{ temporada.pontos }} pts</span>
        </RouterLink>
      </div>
    </div>

    <!-- Administração -->
    <div v-if="autenticacao.eAdministrador" class="mt-6">
      <RouterLink
        to="/admin"
        class="block rounded-2xl border border-border bg-bg-card px-6 py-4 text-sm font-semibold text-text-secondary transition-colors hover:bg-bg-card-hover hover:text-text"
      >
        Administração
      </RouterLink>
    </div>

    <!-- Sair -->
    <div class="mt-6">
      <button
        type="button"
        class="w-full rounded-2xl border border-danger/40 px-6 py-3 text-sm font-semibold text-danger transition-colors hover:bg-danger/10"
        @click="sair"
      >
        Sair
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { requisicaoApi } from '../services/api'
import { usarAutenticacaoStore } from '../stores/autenticacao'
import { useToast } from '../composables/useToast'
import type { Assinatura, Cupom, RespostaBoloes } from '../tipos'

const autenticacao = usarAutenticacaoStore()
const router = useRouter()
const { mostrar } = useToast()

const form = reactive({
  nome: '',
  telefone: '',
  cpf_cnpj: '',
})

const inputFoto = ref<HTMLInputElement | null>(null)
const enviandoFoto = ref(false)

const assinatura = ref<Assinatura | null>(null)
const carregandoAssinatura = ref(true)

const boloesEncerrados = ref<RespostaBoloes['encerrados']>([])
const cupons = ref<Cupom[]>([])

// Cupons do usuário em torneios já encerrados: "Nome do torneio · X pts".
const temporadasAnteriores = computed(() =>
  cupons.value
    .filter((cupom) => boloesEncerrados.value.some((bolao) => bolao.id === cupom.torneio_id))
    .map((cupom) => {
      const bolao = boloesEncerrados.value.find((item) => item.id === cupom.torneio_id)!
      return {
        cupomId: cupom.id,
        nome: `${bolao.nome} ${bolao.edicao}`,
        pontos: Math.round(Number(cupom.pontuacao?.pontuacao_total ?? 0)),
      }
    }),
)

const iniciais = computed(() => {
  const partes = (autenticacao.nome === 'Visitante' ? '' : autenticacao.nome).trim().split(/\s+/).filter(Boolean)
  if (!partes.length) return '?'
  if (partes.length === 1) return partes[0].substring(0, 2).toUpperCase()
  return (partes[0][0] + partes[partes.length - 1][0]).toUpperCase()
})

function formatarCentavos(centavos: number) {
  return (centavos / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}

async function aoSelecionarFoto(evento: Event) {
  const alvo = evento.target as HTMLInputElement
  const arquivo = alvo.files?.[0]
  if (!arquivo) return

  enviandoFoto.value = true
  try {
    await autenticacao.atualizarFoto(arquivo)
    mostrar('sucesso', 'Foto atualizada com sucesso.')
  } catch (error) {
    mostrar('erro', error instanceof Error ? error.message : 'Não foi possível atualizar a foto.')
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

async function carregarAssinatura() {
  carregandoAssinatura.value = true
  try {
    const resposta = await requisicaoApi<{ assinatura: Assinatura | null; elegivel_no_mes: boolean }>('/assinatura')
    assinatura.value = resposta.assinatura
  } catch {
    assinatura.value = null
  } finally {
    carregandoAssinatura.value = false
  }
}

async function carregarTemporadasAnteriores() {
  try {
    const [respostaBoloes, respostaCupons] = await Promise.all([
      requisicaoApi<RespostaBoloes>('/boloes'),
      requisicaoApi<{ cupons: Cupom[] }>('/cupons'),
    ])
    boloesEncerrados.value = respostaBoloes.encerrados
    cupons.value = respostaCupons.cupons
  } catch {
    boloesEncerrados.value = []
    cupons.value = []
  }
}

onMounted(() => {
  preencherFormulario()
  void carregarAssinatura()
  void carregarTemporadasAnteriores()
})

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
    mostrar('erro', error instanceof Error ? error.message : 'Não foi possível atualizar o perfil.')
  }
}

async function sair() {
  await autenticacao.sair()
  router.push('/')
}
</script>
