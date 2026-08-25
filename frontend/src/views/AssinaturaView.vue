<template>
  <div class="mx-auto max-w-3xl space-y-6">
    <header>
      <h1 class="text-2xl font-black text-text">Assinatura</h1>
      <p class="mt-1 text-sm text-text-secondary">
        Jogar é grátis. Assinar coloca você na disputa pelos prêmios de cada mês.
      </p>
    </header>

    <div v-if="carregando" class="h-48 animate-pulse rounded-2xl border border-border bg-bg-card" />

    <template v-else>
      <!-- Estado atual -->
      <section v-if="assinatura" class="rounded-2xl border border-border bg-bg-card p-6 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-primary">Sua assinatura</p>
            <h2 class="mt-1 text-xl font-bold text-text">
              {{ assinatura.plano?.nome ?? 'Assinante' }}
              <span v-if="assinatura.fundador" class="ml-2 rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-bold text-amber-700">Fundador</span>
            </h2>
          </div>
          <span
            class="rounded-full px-3 py-1 text-xs font-bold"
            :class="{
              'bg-primary-dim text-primary': assinatura.status === 'ativa',
              'bg-amber-100 text-amber-700': assinatura.status === 'inadimplente',
              'bg-bg text-text-muted': assinatura.status === 'cancelada',
            }"
          >
            {{ rotuloStatus }}
          </span>
        </div>

        <p class="mt-4 text-3xl font-black text-text">
          {{ formatarPreco(assinatura.preco_centavos_contratado) }}<span class="text-sm font-medium text-text-muted">/mês</span>
        </p>

        <div class="mt-4 rounded-xl border border-border bg-bg p-4">
          <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">Ciclo de {{ competenciaCorrente }}</p>
          <p class="mt-1 text-sm font-medium" :class="elegivelNoMes ? 'text-primary' : 'text-warning'">
            {{ elegivelNoMes ? 'Pago — você está concorrendo aos prêmios deste mês.' : 'Pagamento pendente — você ainda não concorre neste mês.' }}
          </p>
          <button
            v-if="!elegivelNoMes && assinatura.status !== 'cancelada'"
            type="button"
            class="mt-3 rounded-xl bg-primary px-4 py-2 text-sm font-bold text-white transition hover:bg-primary-hover disabled:opacity-50"
            :disabled="processando"
            @click="confirmarCicloSandbox"
          >
            {{ processando ? 'Confirmando...' : 'Confirmar pagamento (sandbox)' }}
          </button>
        </div>

        <button
          v-if="assinatura.status !== 'cancelada'"
          type="button"
          class="mt-5 rounded-xl border border-danger/40 px-4 py-2 text-sm font-semibold text-danger transition hover:bg-danger/10 disabled:opacity-50"
          :disabled="processando"
          @click="cancelar"
        >
          Cancelar assinatura
        </button>
        <p v-if="assinatura.status !== 'cancelada' && assinatura.fundador" class="mt-2 text-xs text-text-muted">
          Cancelando, você perde o preço de fundador — a vaga volta para o contador.
        </p>
      </section>

      <!-- Planos -->
      <section v-if="!assinatura || assinatura.status === 'cancelada'" class="grid gap-4 sm:grid-cols-2">
        <article class="rounded-2xl border border-border bg-bg-card p-6 shadow-sm">
          <p class="text-xs font-semibold uppercase tracking-[0.24em] text-text-muted">Gratuito</p>
          <p class="mt-3 text-3xl font-black text-text">R$ 0</p>
          <ul class="mt-4 space-y-2 text-sm text-text-secondary">
            <li>✓ Palpites de todos os jogos</li>
            <li>✓ Pontuação e posição no ranking</li>
            <li class="text-text-muted">✗ Não concorre aos prêmios</li>
          </ul>
          <p class="mt-5 text-xs text-text-muted">Você já está no plano gratuito.</p>
        </article>

        <article
          v-for="plano in planos"
          :key="plano.id"
          class="relative rounded-2xl border-2 border-primary bg-bg-card p-6 shadow-lg shadow-primary/10"
        >
          <span
            v-if="plano.vagas_fundador_restantes > 0"
            class="absolute -top-3 left-6 rounded-full bg-gold px-3 py-1 text-xs font-black text-white"
          >
            50% OFF — restam {{ plano.vagas_fundador_restantes }} vagas
          </span>
          <p class="text-xs font-semibold uppercase tracking-[0.24em] text-primary">{{ plano.nome }}</p>
          <p class="mt-3 text-3xl font-black text-text">
            {{ formatarPreco(plano.vagas_fundador_restantes > 0 && plano.preco_fundador_centavos !== null ? plano.preco_fundador_centavos : plano.preco_centavos) }}<span class="text-sm font-medium text-text-muted">/mês</span>
          </p>
          <p v-if="plano.vagas_fundador_restantes > 0 && plano.preco_fundador_centavos !== null" class="text-xs text-text-muted">
            <s>{{ formatarPreco(plano.preco_centavos) }}</s> — preço de fundador, seu para sempre enquanto assinar
          </p>
          <ul class="mt-4 space-y-2 text-sm text-text-secondary">
            <li>✓ Tudo do gratuito</li>
            <li>✓ Concorre aos prêmios de cada mês</li>
            <li>✓ Selo de assinante no ranking</li>
          </ul>
          <button
            type="button"
            class="mt-5 w-full rounded-xl bg-primary py-3 text-sm font-black text-white transition hover:bg-primary-hover disabled:opacity-50"
            :disabled="processando"
            @click="assinar(plano)"
          >
            {{ processando ? 'Assinando...' : 'Assinar agora' }}
          </button>
        </article>
      </section>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { requisicaoApi } from '../services/api'
import { useToast } from '../composables/useToast'
import type { Assinatura, Plano } from '../tipos'

const planos = ref<Plano[]>([])
const assinatura = ref<Assinatura | null>(null)
const elegivelNoMes = ref(false)
const carregando = ref(true)
const processando = ref(false)
const { mostrar } = useToast()

const competenciaCorrente = new Date().toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' })

const rotuloStatus = computed(() => {
  const rotulos: Record<string, string> = {
    ativa: 'Ativa',
    inadimplente: 'Pagamento pendente',
    cancelada: 'Cancelada',
  }
  const status = assinatura.value?.status ?? ''
  return rotulos[status] ?? status
})

function formatarPreco(centavos: number) {
  return (centavos / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}

async function carregar() {
  carregando.value = true
  try {
    const [respostaPlanos, respostaAssinatura] = await Promise.all([
      requisicaoApi<{ planos: Plano[] }>('/planos'),
      requisicaoApi<{ assinatura: Assinatura | null; elegivel_no_mes: boolean }>('/assinatura'),
    ])
    planos.value = respostaPlanos.planos
    assinatura.value = respostaAssinatura.assinatura
    elegivelNoMes.value = respostaAssinatura.elegivel_no_mes
  } catch {
    mostrar('erro', 'Não foi possível carregar sua assinatura.')
  } finally {
    carregando.value = false
  }
}

async function assinar(plano: Plano) {
  processando.value = true
  try {
    await requisicaoApi('/assinaturas', { metodo: 'POST', corpo: { plano_id: plano.id } })
    mostrar('sucesso', 'Assinatura criada! Confirme o pagamento para concorrer.')
    await carregar()
  } catch (erro) {
    mostrar('erro', erro instanceof Error ? erro.message : 'Não foi possível assinar.')
  } finally {
    processando.value = false
  }
}

async function cancelar() {
  processando.value = true
  try {
    await requisicaoApi('/assinatura/cancelar', { metodo: 'POST' })
    mostrar('sucesso', 'Assinatura cancelada. Seus palpites continuam valendo no plano gratuito.')
    await carregar()
  } catch (erro) {
    mostrar('erro', erro instanceof Error ? erro.message : 'Não foi possível cancelar.')
  } finally {
    processando.value = false
  }
}

async function confirmarCicloSandbox() {
  processando.value = true
  try {
    await requisicaoApi('/assinatura/confirmar-ciclo-sandbox', { metodo: 'POST' })
    mostrar('sucesso', 'Pagamento confirmado — você está concorrendo este mês!')
    await carregar()
  } catch (erro) {
    mostrar('erro', erro instanceof Error ? erro.message : 'Não foi possível confirmar.')
  } finally {
    processando.value = false
  }
}

onMounted(carregar)
</script>
