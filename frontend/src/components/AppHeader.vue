<template>
  <header class="sticky top-0 z-50 bg-gradient-to-r from-topo to-topo-claro shadow-md">
    <div class="mx-auto flex h-14 sm:h-16 max-w-6xl items-center justify-between px-4 sm:px-6">
      <!-- Logo -->
      <RouterLink to="/" class="flex items-center gap-2.5">
        <img src="/marca/cravae-simbolo-branco.svg" alt="Cravaê" class="h-9 w-9" />
        <div>
          <p class="text-xs font-semibold uppercase tracking-widest text-white">Cravaê</p>
          <p class="text-[11px] text-emerald-200/80">Brasileirão</p>
        </div>
      </RouterLink>

      <!-- Desktop nav -->
      <nav class="hidden sm:flex items-center gap-1">
        <template v-if="autenticacao.estaAutenticado">
          <RouterLink
            to="/palpites"
            class="rounded-lg px-3 py-2 text-sm font-medium text-emerald-100/80 transition-colors hover:bg-white/10 hover:text-white"
            active-class="!bg-white/15 !text-white"
          >
            Palpites
          </RouterLink>
          <RouterLink
            to="/ranking"
            class="rounded-lg px-3 py-2 text-sm font-medium text-emerald-100/80 transition-colors hover:bg-white/10 hover:text-white"
            active-class="!bg-white/15 !text-white"
          >
            Ranking
          </RouterLink>
          <RouterLink
            to="/resultados"
            class="rounded-lg px-3 py-2 text-sm font-medium text-emerald-100/80 transition-colors hover:bg-white/10 hover:text-white"
            active-class="!bg-white/15 !text-white"
          >
            Resultados
          </RouterLink>
          <RouterLink
            to="/perfil"
            class="rounded-lg px-3 py-2 text-sm font-medium text-emerald-100/80 transition-colors hover:bg-white/10 hover:text-white"
            active-class="!bg-white/15 !text-white"
          >
            Perfil
          </RouterLink>

          <!-- Pill de assinatura -->
          <span
            v-if="elegivelNoMes"
            class="ml-2 rounded-full bg-ambar px-3 py-1.5 text-xs font-bold text-[#17301f]"
          >
            ★ Assinante
          </span>
          <RouterLink
            v-else-if="assinaturaCarregada"
            to="/assinatura"
            class="ml-2 rounded-full bg-ambar px-3 py-1.5 text-xs font-bold text-[#17301f] transition hover:brightness-105"
          >
            Assinar
          </RouterLink>
        </template>

        <template v-else>
          <button
            type="button"
            class="rounded-lg px-3 py-2 text-sm font-medium text-emerald-100/80 transition-colors hover:bg-white/10 hover:text-white"
            @click="$emit('abrirModalAuth', 'entrar')"
          >
            Entrar
          </button>
          <button
            type="button"
            class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-primary-hover"
            @click="$emit('abrirModalAuth', 'cadastro')"
          >
            Cadastrar
          </button>
        </template>
      </nav>

      <!-- Mobile right -->
      <div class="flex sm:hidden items-center gap-2">
        <RouterLink v-if="autenticacao.estaAutenticado" to="/perfil">
          <AvatarIniciais :nome="autenticacao.nome" :foto="autenticacao.fotoUrl" tamanho="sm" />
        </RouterLink>
        <template v-else>
          <button
            type="button"
            class="rounded-lg px-3 py-2 text-sm font-medium text-emerald-100/80 transition-colors hover:bg-white/10 hover:text-white"
            @click="$emit('abrirModalAuth', 'entrar')"
          >
            Entrar
          </button>
          <button
            type="button"
            class="rounded-lg bg-primary px-3 py-2 text-sm font-semibold text-white transition-colors hover:bg-primary-hover"
            @click="$emit('abrirModalAuth', 'cadastro')"
          >
            Cadastrar
          </button>
        </template>
      </div>
    </div>
  </header>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { requisicaoApi } from '../services/api'
import { usarAutenticacaoStore } from '../stores/autenticacao'
import type { Assinatura } from '../tipos'
import AvatarIniciais from './AvatarIniciais.vue'

defineEmits<{
  abrirModalAuth: [tab: 'entrar' | 'cadastro']
}>()

const autenticacao = usarAutenticacaoStore()
const elegivelNoMes = ref(false)
const assinaturaCarregada = ref(false)

// Busca a assinatura uma única vez assim que houver usuário autenticado
// (cobre tanto o mount já logado quanto o login via modal).
watch(
  () => autenticacao.estaAutenticado,
  async (logado) => {
    if (!logado) {
      elegivelNoMes.value = false
      assinaturaCarregada.value = false
      return
    }
    if (assinaturaCarregada.value) return
    try {
      const resposta = await requisicaoApi<{ assinatura: Assinatura | null; elegivel_no_mes: boolean }>('/assinatura')
      elegivelNoMes.value = resposta.elegivel_no_mes
    } catch {
      elegivelNoMes.value = false
    } finally {
      assinaturaCarregada.value = true
    }
  },
  { immediate: true },
)
</script>
