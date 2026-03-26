<template>
  <div class="space-y-6">
    <!-- Header do painel -->
    <section class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-primary-dim to-bg-card p-6 sm:p-8">
      <div class="relative z-10">
        <span class="inline-block rounded-full bg-primary/20 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-primary">
          Meus Cupons
        </span>
        <h1 class="mt-3 text-2xl font-bold">Ola, {{ autenticacao.nome }}</h1>
        <p class="mt-1 text-text-secondary">
          Cada cupom representa um conjunto independente de apostas.
        </p>

        <button
          type="button"
          @click="comprarCupom"
          :disabled="carregandoCompra"
          class="mt-5 rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-bg transition-colors hover:bg-primary-hover disabled:opacity-50"
        >
          {{ carregandoCompra ? 'Processando...' : 'Comprar novo cupom' }}
        </button>

        <p v-if="mensagem" class="mt-3 text-sm text-primary">{{ mensagem }}</p>
        <p v-if="erro" class="mt-3 text-sm text-danger">{{ erro }}</p>
      </div>
    </section>

    <!-- Lista de cupons -->
    <section>
      <h2 class="mb-4 text-lg font-bold">Cupons ativos</h2>

      <p v-if="!cupons.length" class="rounded-2xl border border-border bg-bg-card px-5 py-10 text-center text-text-muted">
        Voce ainda nao possui cupons. Compre seu primeiro cupom para comecar a apostar.
      </p>

      <div v-else class="grid gap-4 sm:grid-cols-2">
        <div
          v-for="cupom in cupons"
          :key="cupom.id"
          class="rounded-2xl border border-border bg-bg-card p-5 transition-colors hover:border-border-light"
        >
          <div class="flex items-start justify-between">
            <div>
              <p class="text-xs font-semibold uppercase tracking-wider text-primary">Inter World Cup</p>
              <p class="mt-1 text-lg font-bold">{{ cupom.codigo }}</p>
            </div>
            <span
              class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
              :class="cupom.status === 'ativo' ? 'bg-primary/20 text-primary' : 'bg-warning/20 text-warning'"
            >
              {{ cupom.status }}
            </span>
          </div>

          <div class="mt-4 flex items-center gap-4 rounded-lg bg-bg-input px-3.5 py-2.5">
            <div class="text-center">
              <p class="text-lg font-bold text-primary">{{ cupom.pontuacao?.pontuacao_total ?? '0' }}</p>
              <p class="text-[11px] text-text-muted">PONTOS</p>
            </div>
            <div class="h-8 w-px bg-border" />
            <div class="text-center">
              <p class="text-lg font-bold">{{ cupom.pontuacao?.quantidade_placares_exatos ?? 0 }}</p>
              <p class="text-[11px] text-text-muted">EXATOS</p>
            </div>
          </div>

          <RouterLink
            :to="`/cupons/${cupom.id}`"
            class="mt-4 flex w-full items-center justify-center rounded-lg border border-primary bg-primary/10 py-2.5 text-sm font-semibold text-primary transition-colors hover:bg-primary hover:text-bg"
          >
            Fazer Palpites
          </RouterLink>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { requisicaoApi } from '../services/api'
import { usarAutenticacaoStore } from '../stores/autenticacao'
import type { Cupom, PedidoCheckout } from '../tipos'

const autenticacao = usarAutenticacaoStore()
const cupons = ref<Cupom[]>([])
const carregandoCompra = ref(false)
const mensagem = ref('')
const erro = ref('')

async function carregarCupons() {
  const resposta = await requisicaoApi<{ cupons: Cupom[] }>('/cupons')
  cupons.value = resposta.cupons
}

async function comprarCupom() {
  carregandoCompra.value = true
  mensagem.value = ''
  erro.value = ''

  try {
    const pedido = await requisicaoApi<{ pedido: PedidoCheckout }>('/pedidos-checkout', {
      metodo: 'POST',
      corpo: {},
    })

    await requisicaoApi(`/pedidos-checkout/${pedido.pedido.id}/simular-pagamento`, {
      metodo: 'POST',
      corpo: {},
    })

    mensagem.value = 'Cupom liberado com sucesso!'
    await carregarCupons()
  } catch (error) {
    erro.value = error instanceof Error ? error.message : 'Nao foi possivel concluir a compra.'
  } finally {
    carregandoCompra.value = false
  }
}

onMounted(() => {
  carregarCupons()
})
</script>
