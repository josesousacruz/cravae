<template>
  <div class="mx-auto flex min-h-[60vh] max-w-5xl items-center justify-center px-4 py-8">
    <div v-if="carregando" class="w-full max-w-md rounded-2xl border border-border bg-bg-card p-8">
      <div class="flex flex-col items-center space-y-4">
        <div class="h-12 w-12 animate-pulse rounded-full bg-bg-input" />
        <div class="h-6 w-48 animate-pulse rounded bg-bg-input" />
        <div class="h-4 w-64 animate-pulse rounded bg-bg-input" />
        <div class="my-6 h-10 w-32 animate-pulse rounded bg-bg-input" />
        <div class="h-10 w-full animate-pulse rounded-xl bg-bg-input" />
      </div>
    </div>

    <div v-else class="w-full" :class="asaasAtivo ? 'grid gap-5 lg:grid-cols-[0.9fr_1.1fr]' : 'mx-auto max-w-md'">
      <section class="rounded-2xl border border-border bg-bg-card p-6 sm:p-8">
        <p class="text-xs font-semibold uppercase tracking-wider text-primary">Pagamento Pix</p>
        <h1 class="mt-3 text-2xl font-bold text-text">Comprar cupom</h1>
        <p class="mt-2 text-sm text-text-secondary">Inter World Cup - Copa 2026</p>

        <p class="my-6 text-4xl font-bold text-primary">{{ valorCupomFormatado }}</p>

        <div v-if="asaasAtivo" class="space-y-3 rounded-xl border border-border bg-bg-input p-4 text-sm text-text-secondary">
          <div class="flex items-center justify-between gap-3">
            <span>Status</span>
            <strong class="text-text">{{ statusPagamento }}</strong>
          </div>
          <div v-if="pedido?.pix_expira_at" class="flex items-center justify-between gap-3">
            <span>Expira em</span>
            <strong class="text-text">{{ formatarData(pedido.pix_expira_at) }}</strong>
          </div>
        </div>

        <p v-if="asaasAtivo && sincronizacaoErro" class="mt-3 text-xs text-warning">
          Nao foi possivel confirmar no Asaas agora. Vamos tentar novamente automaticamente.
        </p>

        <button
          v-if="asaasAtivo && !pedido"
          type="button"
          class="mt-6 w-full rounded-xl bg-primary py-3 font-semibold text-bg transition-colors hover:bg-primary-hover disabled:cursor-not-allowed disabled:opacity-50"
          :disabled="processando"
          @click="gerarPagamento"
        >
          {{ processando ? 'Gerando Pix...' : 'Gerar Pix' }}
        </button>

        <button
          v-if="!pedido"
          type="button"
          class="w-full rounded-xl py-3 font-semibold transition-colors disabled:cursor-not-allowed disabled:opacity-50"
          :class="asaasAtivo
            ? 'mt-3 border border-primary/40 bg-primary/5 text-primary hover:bg-primary/10'
            : 'mt-6 bg-primary text-bg hover:bg-primary-hover'"
          :disabled="processandoDireto"
          @click="gerarPixDireto"
        >
          {{ processandoDireto ? 'Gerando...' : 'Pagar com Pix (confirmacao manual)' }}
        </button>

        <RouterLink
          v-if="asaasAtivo && !autenticacao.cpfCnpj"
          to="/perfil"
          class="mt-3 flex w-full items-center justify-center rounded-xl border border-warning/40 bg-warning/10 py-3 text-sm font-semibold text-warning transition-colors hover:bg-warning/15"
        >
          Preencher CPF/CNPJ
        </RouterLink>

        <RouterLink to="/painel" class="mt-4 inline-block text-sm text-text-muted transition-colors hover:text-danger">
          Voltar ao painel
        </RouterLink>
      </section>

      <section v-if="asaasAtivo" class="rounded-2xl border border-border bg-bg-card p-6 sm:p-8">
        <div v-if="pedido?.pix_qr_code_base64 && pedido.pix_copia_cola" class="grid gap-6 md:grid-cols-[220px_1fr]">
          <div class="rounded-xl bg-white p-3">
            <img :src="`data:image/png;base64,${pedido.pix_qr_code_base64}`" alt="QR Code Pix" class="h-full w-full" />
          </div>

          <div class="min-w-0">
            <p class="text-sm font-semibold text-text">Pix copia e cola</p>
            <textarea
              class="mt-3 h-32 w-full resize-none rounded-xl border border-border bg-bg-input p-3 font-mono text-xs text-text"
              readonly
              :value="pedido.pix_copia_cola"
            />
            <button
              type="button"
              class="mt-3 rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-bg transition-colors hover:bg-primary-hover"
              @click="copiarPix"
            >
              Copiar codigo Pix
            </button>
            <p class="mt-4 text-sm text-text-secondary">
              Apos o pagamento, o cupom sera liberado automaticamente quando o Asaas confirmar o recebimento. Se o webhook atrasar, vamos verificar periodicamente.
            </p>
          </div>
        </div>

        <div v-else class="flex min-h-64 flex-col items-center justify-center text-center">
          <div class="flex h-14 w-14 items-center justify-center rounded-full border border-border bg-bg-input text-primary">
            <span class="text-2xl font-bold">$</span>
          </div>
          <h2 class="mt-4 text-lg font-bold text-text">Pix ainda nao gerado</h2>
          <p class="mt-2 max-w-sm text-sm text-text-secondary">
            Gere o Pix para receber o QR Code e o codigo copia e cola do pagamento.
          </p>
        </div>
      </section>
    </div>

    <ModalPixPagamento
      :aberto="modalPixAberto"
      :cupom-codigo="cupomDireto?.codigo ?? ''"
      :valor="torneio?.valor_cupom ?? null"
      @fechar="fecharModalDireto"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { requisicaoApi } from '../services/api'
import { useToast } from '../composables/useToast'
import { usarAutenticacaoStore } from '../stores/autenticacao'
import ModalPixPagamento from '../components/ModalPixPagamento.vue'
import type { Cupom, PedidoCheckout, Torneio } from '../tipos'

type RespostaPedidoCheckout = {
  pedido: PedidoCheckout
  cupom?: Cupom
  sincronizacao_erro?: string | null
}

const router = useRouter()
const route = useRoute()
const { mostrar } = useToast()
const autenticacao = usarAutenticacaoStore()

const torneio = ref<Torneio | null>(null)
const pedido = ref<PedidoCheckout | null>(null)
// Asaas desativado: somente Pix com confirmacao manual fica ativo no checkout.
// Todo o fluxo Asaas (backend + frontend) segue intacto; para reativar, basta
// voltar esta flag para true.
const asaasAtivo = false

const carregando = ref(true)
const processando = ref(false)
const processandoDireto = ref(false)
const modalPixAberto = ref(false)
const cupomDireto = ref<Cupom | null>(null)
const sincronizacaoErro = ref<string | null>(null)
let intervaloConsulta: number | null = null

const cupomId = computed(() => {
  const id = Number(route.query.cupom)
  return Number.isFinite(id) && id > 0 ? id : null
})

const torneioIdSelecionado = computed(() => {
  const id = Number(route.query.torneio)
  return Number.isFinite(id) && id > 0 ? id : null
})

const valorCupomFormatado = computed(() => {
  const valor = pedido.value ? Number(pedido.value.valor) : torneio.value?.valor_cupom
  if (!valor) return 'R$ --'
  return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(valor)
})

const statusPagamento = computed(() => {
  if (!pedido.value) return 'Aguardando geracao'
  if (pedido.value.status === 'pago') return 'Pago'
  if (pedido.value.status === 'expirado') return 'Expirado'
  if (pedido.value.status === 'cancelado') return 'Cancelado'
  if (pedido.value.status === 'estornado') return 'Estornado'
  return 'Aguardando pagamento'
})

onMounted(async () => {
  try {
    const caminhoTorneio = torneioIdSelecionado.value
      ? `/torneios/${torneioIdSelecionado.value}`
      : '/torneio'
    const resposta = await requisicaoApi<{ torneio: Torneio }>(caminhoTorneio)
    torneio.value = resposta.torneio
  } catch {
    mostrar('erro', 'Nao foi possivel carregar dados do torneio.')
  } finally {
    carregando.value = false
  }
})

onUnmounted(() => pararConsulta())

async function gerarPagamento() {
  processando.value = true
  try {
    const torneioId = torneio.value?.id
    if (!torneioId) {
      mostrar('erro', 'Bolão não encontrado para o checkout.')
      return
    }
    const corpo: Record<string, number> = { torneio_id: torneioId }
    if (cupomId.value) {
      corpo.cupom_id = cupomId.value
    }
    const resposta = await requisicaoApi<RespostaPedidoCheckout>('/pedidos-checkout', {
      metodo: 'POST',
      corpo,
    })

    pedido.value = resposta.pedido
    sincronizacaoErro.value = null
    mostrar('sucesso', 'Pix gerado com sucesso.')
    iniciarConsulta()
  } catch (error) {
    mostrar('erro', error instanceof Error ? error.message : 'Nao foi possivel gerar o Pix.')
  } finally {
    processando.value = false
  }
}

async function gerarPixDireto() {
  processandoDireto.value = true
  try {
    const torneioId = torneio.value?.id
    if (!torneioId) {
      mostrar('erro', 'Bolão não encontrado para o checkout.')
      return
    }
    const resposta = await requisicaoApi<RespostaPedidoCheckout>('/pedidos-checkout', {
      metodo: 'POST',
      corpo: { torneio_id: torneioId, forma_pagamento: 'pix_direto' },
    })
    cupomDireto.value = resposta.cupom ?? null
    modalPixAberto.value = true
  } catch (error) {
    mostrar('erro', error instanceof Error ? error.message : 'Nao foi possivel gerar o Pix direto.')
  } finally {
    processandoDireto.value = false
  }
}

function fecharModalDireto() {
  modalPixAberto.value = false
  mostrar('sucesso', 'Cupom criado. Ele sera liberado apos a confirmacao do pagamento.')
  router.push({ name: 'painel' })
}

function iniciarConsulta() {
  pararConsulta()
  intervaloConsulta = window.setInterval(consultarPedido, 10000)
}

function pararConsulta() {
  if (intervaloConsulta) {
    window.clearInterval(intervaloConsulta)
    intervaloConsulta = null
  }
}

async function consultarPedido() {
  if (!pedido.value) return

  try {
    const resposta = await requisicaoApi<RespostaPedidoCheckout>(`/pedidos-checkout/${pedido.value.id}?sincronizar=1`)
    pedido.value = resposta.pedido
    sincronizacaoErro.value = resposta.sincronizacao_erro ?? null

    if (resposta.pedido.status === 'pago') {
      pararConsulta()
      mostrar('sucesso', 'Cupom liberado com sucesso!')
      router.push({ name: 'painel' })
      return
    }

    if (['expirado', 'cancelado', 'estornado'].includes(resposta.pedido.status)) {
      pararConsulta()
      mostrar('erro', mensagemStatusFinal(resposta.pedido.status))
    }
  } catch {
    sincronizacaoErro.value = 'Nao foi possivel consultar o pagamento no Asaas agora.'
  }
}

function mensagemStatusFinal(status: string) {
  if (status === 'expirado') return 'Pagamento expirado. Gere um novo Pix para continuar.'
  if (status === 'cancelado') return 'Pagamento cancelado no Asaas.'
  if (status === 'estornado') return 'Pagamento estornado no Asaas.'
  return 'Pagamento nao liberado.'
}

async function copiarPix() {
  if (!pedido.value?.pix_copia_cola) return
  await navigator.clipboard.writeText(pedido.value.pix_copia_cola)
  mostrar('sucesso', 'Codigo Pix copiado.')
}

function formatarData(valor: string) {
  return new Intl.DateTimeFormat('pt-BR', {
    dateStyle: 'short',
    timeStyle: 'short',
  }).format(new Date(valor))
}
</script>
