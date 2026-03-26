<template>
  <div class="space-y-20">
    <!-- Hero -->
    <section class="rounded-3xl bg-gradient-to-br from-primary-dim/30 to-bg-card p-8 sm:p-16 text-center">
      <span class="inline-block text-xs font-semibold uppercase tracking-widest text-primary">
        BOLAO ONLINE
      </span>
      <h1 class="mx-auto mt-4 max-w-2xl text-4xl font-bold leading-tight sm:text-5xl">
        Bolao Copa 2026 <span class="text-primary">Online</span>
      </h1>
      <p class="mx-auto mt-4 max-w-xl text-sm text-text-secondary sm:text-base">
        Compre seus cupons, faca seus palpites e acompanhe o ranking em tempo real.
      </p>
      <div class="mt-8">
        <RouterLink
          v-if="autenticacao.estaAutenticado"
          to="/painel"
          class="inline-block rounded-xl bg-primary px-8 py-3 font-semibold text-bg transition-colors hover:bg-primary-hover"
        >
          Ir para Meus Cupons
        </RouterLink>
        <button
          v-else
          type="button"
          class="rounded-xl bg-primary px-8 py-3 font-semibold text-bg transition-colors hover:bg-primary-hover"
          @click="$emit('abrirModalAuth', 'cadastro')"
        >
          Comecar agora
        </button>
      </div>
    </section>

    <!-- Como Funciona -->
    <section>
      <h2 class="text-xl font-bold text-center sm:text-2xl mb-8">Como funciona</h2>
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div
          v-for="(passo, i) in passos"
          :key="i"
          class="rounded-2xl border border-border bg-bg-card p-6"
        >
          <span class="flex h-10 w-10 items-center justify-center rounded-full bg-primary/20 text-sm font-bold text-primary">
            {{ i + 1 }}
          </span>
          <h3 class="mt-4 text-sm font-semibold text-text">{{ passo.titulo }}</h3>
          <p class="mt-2 text-xs leading-relaxed text-text-secondary">{{ passo.descricao }}</p>
        </div>
      </div>
    </section>

    <!-- Regras de Pontuacao -->
    <section>
      <h2 class="text-xl font-bold text-center sm:text-2xl mb-8">Regras de pontuacao</h2>
      <div v-if="carregando" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div v-for="n in 6" :key="n" class="animate-pulse rounded-xl bg-bg-input h-24" />
      </div>
      <div v-else-if="regrasAtivas.length" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div
          v-for="regra in regrasAtivas"
          :key="regra.id"
          class="rounded-xl border border-border bg-bg-card p-5"
        >
          <p class="text-sm font-medium text-text">{{ regra.nome }}</p>
          <p class="mt-1 text-2xl font-bold text-primary">+{{ regra.pontos }} pts</p>
          <p v-if="regra.descricao" class="mt-1 text-xs text-text-muted">{{ regra.descricao }}</p>
        </div>
      </div>
    </section>

    <!-- Compre Seu Cupom -->
    <section class="rounded-3xl border border-border bg-gradient-to-br from-bg-card to-bg p-8 sm:p-12 text-center">
      <h2 class="text-xl font-bold sm:text-2xl">Compre seu cupom</h2>
      <p class="mx-auto mt-3 max-w-md text-text-secondary">
        Participe do bolao por apenas {{ valorCupomFormatado }}. Cada cupom e independente.
      </p>
      <div class="mt-8">
        <RouterLink
          v-if="autenticacao.estaAutenticado"
          to="/checkout"
          class="inline-block rounded-xl bg-primary px-8 py-3 font-semibold text-bg transition-colors hover:bg-primary-hover"
        >
          Comprar novo cupom
        </RouterLink>
        <button
          v-else
          type="button"
          class="rounded-xl bg-primary px-8 py-3 font-semibold text-bg transition-colors hover:bg-primary-hover"
          @click="$emit('abrirModalAuth', 'cadastro')"
        >
          Cadastre-se para comprar
        </button>
      </div>
    </section>

    <!-- FAQ -->
    <section>
      <h2 class="text-xl font-bold text-center sm:text-2xl mb-8">Perguntas frequentes</h2>
      <div class="mx-auto max-w-3xl">
        <div
          v-for="(item, i) in faq"
          :key="i"
          class="border-b border-border py-4"
        >
          <button
            type="button"
            class="flex w-full items-center justify-between text-left text-sm font-medium text-text transition-colors hover:text-primary"
            @click="faqAberto[i] = !faqAberto[i]"
          >
            <span>{{ item.pergunta }}</span>
            <svg
              class="h-5 w-5 shrink-0 text-text-muted transition-transform duration-200"
              :class="{ 'rotate-180': faqAberto[i] }"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
              stroke-width="2"
            >
              <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
            </svg>
          </button>
          <div
            v-show="faqAberto[i]"
            class="mt-3 text-sm leading-relaxed text-text-secondary"
          >
            {{ item.resposta }}
          </div>
        </div>
      </div>
    </section>

    <!-- Footer -->
    <footer class="-mx-4 sm:-mx-6 border-t border-border bg-bg-card">
      <div class="mx-auto max-w-6xl px-4 sm:px-6 py-8 text-center">
        <p class="text-sm text-text-muted">
          Inter World Cup &copy; {{ anoAtual }}
        </p>
      </div>
    </footer>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { requisicaoApi } from '../services/api'
import { usarAutenticacaoStore } from '../stores/autenticacao'
import type { Torneio, RegraPontuacao } from '../tipos'

defineEmits<{
  abrirModalAuth: [tab: 'entrar' | 'cadastro']
}>()

const autenticacao = usarAutenticacaoStore()

const torneio = ref<Torneio | null>(null)
const carregando = ref(true)
const anoAtual = new Date().getFullYear()

const regrasAtivas = computed<RegraPontuacao[]>(() => {
  if (!torneio.value) return []
  return torneio.value.regras_pontuacao.filter(r => r.ativo)
})

const valorCupomFormatado = computed(() => {
  if (!torneio.value) return 'R$ --'
  return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(torneio.value.valor_cupom)
})

const passos = [
  {
    titulo: 'Cadastre-se',
    descricao: 'Crie sua conta em segundos e entre para o bolao.',
  },
  {
    titulo: 'Compre um cupom',
    descricao: 'Cada cupom representa uma entrada independente.',
  },
  {
    titulo: 'Faca seus palpites',
    descricao: 'Aposte nos placares, classificados, artilheiro e mais.',
  },
  {
    titulo: 'Acompanhe o ranking',
    descricao: 'Veja sua posicao em tempo real conforme os resultados saem.',
  },
]

const faq = [
  {
    pergunta: 'O que e um cupom?',
    resposta: 'Cada cupom e uma entrada independente no bolao. Voce pode comprar quantos quiser, e cada um tera seus proprios palpites e pontuacao.',
  },
  {
    pergunta: 'Como funciona a pontuacao?',
    resposta: 'Os pontos sao calculados com base nas regras configuradas para o torneio. Acertar o placar exato vale mais pontos.',
  },
  {
    pergunta: 'Posso ter mais de um cupom?',
    resposta: 'Sim! Cada cupom funciona de forma independente com seu proprio conjunto de apostas e posicao no ranking.',
  },
  {
    pergunta: 'Ate quando posso fazer meus palpites?',
    resposta: 'Cada fase e rodada tem um prazo de fechamento. Apos o prazo, nao e possivel criar ou editar apostas.',
  },
  {
    pergunta: 'Como funciona o ranking?',
    resposta: 'O ranking e por cupom. A posicao e determinada pela pontuacao total, com desempate por placares exatos acertados.',
  },
]

const faqAberto = reactive<Record<number, boolean>>({})

onMounted(async () => {
  try {
    const resposta = await requisicaoApi<{ torneio: Torneio }>('/torneio')
    torneio.value = resposta.torneio
  } catch {
    // torneio nao disponivel
  } finally {
    carregando.value = false
  }
})


</script>
