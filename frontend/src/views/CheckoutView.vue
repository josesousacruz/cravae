<template>
  <div class="flex items-center justify-center min-h-[60vh]">
    <!-- Skeleton loading -->
    <div v-if="carregando" class="max-w-md w-full rounded-2xl border border-border bg-bg-card p-8">
      <div class="flex flex-col items-center space-y-4">
        <div class="h-12 w-12 animate-pulse rounded-full bg-bg-input" />
        <div class="h-6 w-48 animate-pulse rounded bg-bg-input" />
        <div class="h-4 w-64 animate-pulse rounded bg-bg-input" />
        <div class="h-10 w-32 animate-pulse rounded bg-bg-input my-6" />
        <div class="h-10 w-full animate-pulse rounded-xl bg-bg-input" />
      </div>
    </div>

    <!-- Checkout card -->
    <div v-else class="max-w-md w-full rounded-2xl border border-border bg-bg-card p-8">
      <div class="flex flex-col items-center text-center">
        <!-- Trophy icon -->
        <svg class="h-12 w-12 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9 0a3 3 0 013 3h-15a3 3 0 013-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 01-.982-3.172M9.497 14.25a7.454 7.454 0 00.981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 007.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M18.75 4.236c.982.143 1.954.317 2.916.52A6.003 6.003 0 0016.27 9.728M18.75 4.236V4.5c0 2.108-.966 3.99-2.48 5.228m0 0a6.023 6.023 0 01-2.27.308m4.75 0a6.023 6.023 0 002.27.308" />
        </svg>

        <h1 class="mt-4 text-xl font-bold text-text">Confirmar compra</h1>
        <p class="mt-2 text-sm text-text-secondary">
          Inter World Cup &mdash; Copa 2026
        </p>

        <p class="my-6 text-3xl font-bold text-primary">
          {{ valorCupomFormatado }}
        </p>

        <button
          type="button"
          class="w-full rounded-xl bg-primary py-3 font-semibold text-bg transition-colors hover:bg-primary-hover disabled:opacity-50 disabled:cursor-not-allowed"
          :disabled="processando"
          @click="confirmarPagamento"
        >
          {{ processando ? 'Processando...' : 'Confirmar pagamento' }}
        </button>

        <RouterLink
          to="/painel"
          class="mt-4 inline-block text-sm text-text-muted transition-colors hover:text-danger"
        >
          Cancelar compra
        </RouterLink>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { requisicaoApi } from '../services/api'
import { useToast } from '../composables/useToast'
import type { Torneio, PedidoCheckout, Cupom } from '../tipos'

const router = useRouter()
const { mostrar } = useToast()

const torneio = ref<Torneio | null>(null)
const carregando = ref(true)
const processando = ref(false)

const valorCupomFormatado = computed(() => {
  if (!torneio.value) return 'R$ --'
  return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(torneio.value.valor_cupom)
})

onMounted(async () => {
  try {
    const resposta = await requisicaoApi<{ torneio: Torneio }>('/torneio')
    torneio.value = resposta.torneio
  } catch {
    mostrar('erro', 'Nao foi possivel carregar dados do torneio.')
  } finally {
    carregando.value = false
  }
})

async function confirmarPagamento() {
  processando.value = true
  try {
    // 1. Create checkout order
    const pedido = await requisicaoApi<{ pedido_checkout: PedidoCheckout }>('/pedidos-checkout', {
      metodo: 'POST',
      corpo: {},
    })

    // 2. Simulate payment
    await requisicaoApi<{ cupom: Cupom }>(
      `/pedidos-checkout/${pedido.pedido_checkout.id}/simular-pagamento`,
      { metodo: 'POST', corpo: {} },
    )

    // 3. Success
    mostrar('sucesso', 'Cupom liberado com sucesso!')
    router.push({ name: 'painel' })
  } catch {
    mostrar('erro', 'Nao foi possivel concluir a compra. Tente novamente em alguns instantes.')
  } finally {
    processando.value = false
  }
}
</script>
