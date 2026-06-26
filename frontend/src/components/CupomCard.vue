<template>
  <div class="rounded-2xl border border-border bg-[#1a1f1a] p-5 transition-all hover:border-primary/40">
    <!-- Top row: trophy image + info + badge -->
    <div class="flex items-start gap-4">
      <!-- Trophy placeholder -->
      <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-[#2a2a1a] to-[#1a1a0a] border border-border">
        <svg class="h-10 w-10 text-warning" fill="currentColor" viewBox="0 0 24 24">
          <path d="M5 3h14c.6 0 1 .4 1 1v2c0 2.2-1.8 4-4 4h-.7c-.4 1.3-1.3 2.4-2.3 3.1V16h2c1.1 0 2 .9 2 2v1c0 .6-.4 1-1 1H7c-.6 0-1-.4-1-1v-1c0-1.1.9-2 2-2h2v-2.9c-1-.7-1.9-1.8-2.3-3.1H7c-2.2 0-4-1.8-4-4V4c0-.6.4-1 1-1zm1 2v1c0 1.1.9 2 2 2h.2C8.1 7.1 8 6.1 8 5H6zm12 0h-2c0 1.1-.1 2.1-.2 3H16c1.1 0 2-.9 2-2V5z"/>
        </svg>
      </div>

      <div class="min-w-0 flex-1">
        <div class="flex items-start justify-between gap-2">
          <div class="min-w-0">
            <h3 class="line-clamp-2 text-base font-bold leading-tight text-text">{{ nomeBolao }}</h3>
            <p class="mt-0.5 text-xs text-text-muted">Copa do Mundo 2026 · Pontuacao padrao</p>
          </div>
          <button
            v-if="cupom.status !== 'ativo'"
            type="button"
            class="shrink-0 rounded-full bg-warning/20 px-3 py-1 text-xs font-semibold text-warning transition hover:bg-warning/30"
            title="Pagar via Pix"
            @click="modalAberto = true"
          >
            {{ rotuloStatus }}
          </button>
          <span v-else class="shrink-0 rounded-full bg-primary px-3 py-1 text-xs font-semibold text-bg">
            {{ rotuloStatus }}
          </span>
        </div>
      </div>
    </div>

    <!-- Stats row -->
    <div class="mt-4 flex items-center gap-3">
      <div class="flex-1 rounded-lg bg-bg-input px-3 py-2.5 text-center">
        <p class="text-lg font-bold text-text">{{ cupom.pontuacao?.pontuacao_total ?? '0' }}</p>
        <p class="text-[10px] uppercase tracking-wider text-text-muted">Pontos</p>
      </div>
      <div class="flex-1 rounded-lg bg-bg-input px-3 py-2.5 text-center">
        <p class="text-lg font-bold text-primary">{{ valorBolao }}</p>
        <p class="text-[10px] uppercase tracking-wider text-text-muted">Por pessoa</p>
      </div>
      <div class="flex-1 rounded-lg bg-bg-input px-3 py-2.5 text-center">
        <p class="text-lg font-bold text-primary">{{ cupom.pontuacao?.quantidade_placares_exatos ?? 0 }}</p>
        <p class="text-[10px] uppercase tracking-wider text-text-muted">Exatos</p>
      </div>
    </div>

    <!-- Progress bar -->
    <div class="mt-3 h-1 w-full rounded-full bg-border">
      <div class="h-full rounded-full bg-primary" style="width: 5%"></div>
    </div>

    <!-- Bottom row -->
    <div class="mt-3 flex items-center justify-between">
      <div class="flex items-center gap-1.5 text-xs font-semibold" :class="ehMataMata ? 'text-amber-400' : 'text-primary'">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9 0a3 3 0 013 3h-15a3 3 0 013-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 01-.982-3.172M9.497 14.25a7.454 7.454 0 00.981-3.172" />
        </svg>
        <span>{{ tipoBolao }}</span>
      </div>
      <span class="text-xs font-mono text-text-muted">{{ cupom.codigo }}</span>
    </div>

    <!-- CTA — cupom pendente: pagamento em destaque; palpites como acao secundaria -->
    <template v-if="cupom.status !== 'ativo'">
      <button
        type="button"
        class="group/pix mt-4 flex w-full items-center justify-center gap-2.5 rounded-xl bg-[#32BCAD] py-3.5 text-sm font-bold text-black shadow-lg shadow-[#32BCAD]/25 transition-all hover:bg-[#2aa99b] hover:shadow-[#32BCAD]/40"
        @click="modalAberto = true"
      >
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h3c.621 0 1.125.504 1.125 1.125v3c0 .621-.504 1.125-1.125 1.125h-3A1.125 1.125 0 013.75 7.875v-3zM3.75 16.125c0-.621.504-1.125 1.125-1.125h3c.621 0 1.125.504 1.125 1.125v3c0 .621-.504 1.125-1.125 1.125h-3a1.125 1.125 0 01-1.125-1.125v-3zM15 4.875c0-.621.504-1.125 1.125-1.125h3c.621 0 1.125.504 1.125 1.125v3c0 .621-.504 1.125-1.125 1.125h-3A1.125 1.125 0 0115 7.875v-3zM13.5 13.5h3m-3 3h.008v.008H13.5V16.5zm3 3h.008v.008H16.5V19.5zm3-3h.008v.008H19.5V16.5zm0 3h.008v.008H19.5V19.5z" /></svg>
        <span>Pagar via Pix{{ valorFormatado ? ` · ${valorFormatado}` : '' }}</span>
      </button>
      <RouterLink
        :to="`/cupons/${cupom.id}`"
        class="mt-2 flex w-full items-center justify-center rounded-xl border border-border bg-bg-input py-2.5 text-sm font-semibold text-text-secondary transition hover:border-primary/40 hover:text-text"
      >
        Ver Palpites
      </RouterLink>
    </template>

    <RouterLink
      v-else
      :to="`/cupons/${cupom.id}`"
      class="mt-4 flex w-full items-center justify-center rounded-xl bg-primary py-3 text-sm font-bold text-bg transition-all hover:bg-primary-hover hover:shadow-lg hover:shadow-primary/20"
    >
      Ver Palpites
    </RouterLink>

    <ModalPixPagamento
      :aberto="modalAberto"
      :cupom-codigo="cupom.codigo"
      :valor="cupom.pedido_checkout?.valor"
      @fechar="modalAberto = false"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { RouterLink } from 'vue-router'
import type { Cupom } from '../tipos'
import ModalPixPagamento from './ModalPixPagamento.vue'

const props = defineProps<{
  cupom: Cupom
}>()

const modalAberto = ref(false)

const nomeBolao = computed(() => props.cupom.torneio?.nome ?? 'Inter World Cup')

const ehMataMata = computed(() => {
  const t = props.cupom.torneio
  return /mata/i.test(t?.nome ?? '') || (t?.edicao ?? '').toUpperCase().includes('MM')
})

const tipoBolao = computed(() => (ehMataMata.value ? 'Só mata-mata' : 'Campeonato completo'))

const valorBolao = computed(() => {
  const v = Number(props.cupom.torneio?.valor_cupom ?? props.cupom.pedido_checkout?.valor)
  return Number.isFinite(v) && v > 0
    ? new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v)
    : 'R$ 10,00'
})

const valorFormatado = computed(() => {
  const numero = Number(props.cupom.pedido_checkout?.valor)
  return Number.isFinite(numero) && numero > 0
    ? new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(numero)
    : ''
})

const rotuloStatus = computed(() => {
  if (props.cupom.status === 'ativo') return 'Cupom ativo'
  if (props.cupom.status === 'aguardando_pagamento') return 'Aguardando pagamento'
  return props.cupom.status
})
</script>
