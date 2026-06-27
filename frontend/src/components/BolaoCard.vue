<template>
  <div class="flex flex-col overflow-hidden rounded-2xl border border-border bg-bg-card transition hover:border-primary/40">
    <!-- Capa: imagem do torneio (se a API fornecer) ou placeholder -->
    <div class="relative h-28 w-full overflow-hidden bg-gradient-to-br from-primary-dim/40 to-bg-input">
      <img
        v-if="bolao.imagem_url"
        :src="bolao.imagem_url"
        :alt="bolao.nome"
        loading="lazy"
        class="h-full w-full object-cover"
      />
      <div v-else class="flex h-full w-full items-center justify-center">
        <svg class="h-12 w-12 text-primary/40" fill="currentColor" viewBox="0 0 24 24">
          <path d="M5 3h14c.6 0 1 .4 1 1v2c0 2.2-1.8 4-4 4h-.7c-.4 1.3-1.3 2.4-2.3 3.1V16h2c1.1 0 2 .9 2 2v1c0 .6-.4 1-1 1H7c-.6 0-1-.4-1-1v-1c0-1.1.9-2 2-2h2v-2.9c-1-.7-1.9-1.8-2.3-3.1H7c-2.2 0-4-1.8-4-4V4c0-.6.4-1 1-1zm1 2v1c0 1.1.9 2 2 2h.2C8.1 7.1 8 6.1 8 5H6zm12 0h-2c0 1.1-.1 2.1-.2 3H16c1.1 0 2-.9 2-2V5z" />
        </svg>
      </div>

      <span
        class="absolute right-2 top-2 rounded-full px-2 py-0.5 text-[10px] font-medium backdrop-blur"
        :class="badgeClasse"
      >
        {{ badgeTexto }}
      </span>
    </div>

    <div class="flex flex-1 flex-col p-5">
      <div class="flex items-start justify-between gap-2">
        <div class="min-w-0">
          <h3 class="truncate text-base font-bold text-text">{{ bolao.nome }}</h3>
          <p class="text-xs text-text-muted">{{ tipoBolao }} · {{ bolao.edicao }}</p>
        </div>
      </div>

      <div class="mt-3 text-sm text-text-secondary">
        Cupom <span class="font-bold text-text">{{ valorFormatado }}</span>
      </div>

      <div class="mt-4 flex items-stretch gap-2">
        <!-- Ação principal: entrar/ver o bolão -->
        <button
          type="button"
          class="inline-flex flex-[2] items-center justify-center rounded-lg bg-primary px-3 py-2.5 text-sm font-bold text-bg transition hover:opacity-90"
          @click="verBolao"
        >
          Ver bolão
        </button>
        <!-- Ação secundária: comprar cupom -->
        <RouterLink
          v-if="bolao.status === 'publicado' && bolao.compras_abertas"
          :to="{ name: 'checkout', query: { torneio: bolao.id } }"
          class="inline-flex flex-1 items-center justify-center rounded-lg border border-border bg-bg-input px-3 py-2 text-center text-xs font-semibold text-text-secondary transition hover:border-primary/40 hover:text-text"
        >
          Comprar cupom
        </RouterLink>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { usarBolaoAtivoStore } from '../stores/bolaoAtivo'
import type { Bolao } from '../tipos'

const props = defineProps<{ bolao: Bolao }>()

const router = useRouter()
const bolaoAtivo = usarBolaoAtivoStore()

const ehMataMata = computed(
  () => /mata/i.test(props.bolao.nome) || props.bolao.edicao.toUpperCase().includes('MM'),
)
const tipoBolao = computed(() => (ehMataMata.value ? 'Só mata-mata' : 'Campeonato completo'))

const valorFormatado = computed(() => {
  const numero = Number(props.bolao.valor_cupom)
  return Number.isFinite(numero)
    ? new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(numero)
    : 'R$ 0,00'
})

const badgeTexto = computed(() => {
  if (props.bolao.status === 'encerrado') return 'Encerrado'
  return props.bolao.compras_abertas ? 'Compra aberta' : 'Compra fechada'
})

const badgeClasse = computed(() => {
  if (props.bolao.status === 'encerrado') return 'bg-bg-card/80 text-text-muted'
  return props.bolao.compras_abertas ? 'bg-primary/20 text-primary' : 'bg-warning/20 text-warning'
})

function verBolao() {
  bolaoAtivo.definirAtivo(props.bolao.id)
  router.push({ name: 'painel' })
}
</script>
