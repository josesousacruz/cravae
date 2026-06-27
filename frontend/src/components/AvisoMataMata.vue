<template>
  <Teleport to="body">
    <div
      v-if="aberto"
      class="fixed inset-0 z-[60] flex items-center justify-center bg-black/60 p-4"
      @click.self="fechar"
    >
      <div class="w-full max-w-md rounded-2xl border border-border bg-bg-card p-6 shadow-2xl">
        <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-amber-500/15 text-2xl">⚔️</div>
        <h2 class="text-lg font-black text-text">Mata-mata liberado!</h2>
        <p class="mt-2 text-sm text-text-secondary">
          Os confrontos do mata-mata já estão disponíveis. Criamos um
          <strong class="text-text">bolão exclusivo só do mata-mata</strong> — entre e faça seus palpites.
        </p>
        <p class="mt-2 text-sm text-text-secondary">
          E não esqueça: continue apostando no <strong class="text-text">bolão completo</strong> também!
        </p>
        <div class="mt-5 flex gap-2">
          <button
            type="button"
            class="flex-[2] rounded-xl bg-primary py-2.5 text-sm font-bold text-bg transition hover:opacity-90"
            @click="verBoloes"
          >
            Ver bolões
          </button>
          <button
            type="button"
            class="flex-1 rounded-xl border border-border bg-bg-input py-2.5 text-sm font-semibold text-text-secondary transition hover:text-text"
            @click="fechar"
          >
            Agora não
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { usarAutenticacaoStore } from '../stores/autenticacao'

// Versão do aviso — trocar o sufixo reexibe para todos num próximo anúncio.
const CHAVE = 'aviso_mata_mata_v1'

const router = useRouter()
const autenticacao = usarAutenticacaoStore()
const aberto = ref(false)

function avaliar() {
  if (autenticacao.estaAutenticado && !localStorage.getItem(CHAVE)) {
    aberto.value = true
  }
}

// Mostra uma vez quando o usuário fica autenticado (login ou sessão já ativa).
watch(() => autenticacao.estaAutenticado, avaliar, { immediate: true })

function fechar() {
  localStorage.setItem(CHAVE, '1')
  aberto.value = false
}

function verBoloes() {
  fechar()
  router.push({ name: 'boloes' })
}
</script>
