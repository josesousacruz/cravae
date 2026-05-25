<template>
  <div class="min-h-screen flex flex-col">
    <AppHeader
      v-if="!landingAtiva"
      @abrir-modal-auth="abrirModalAuth"
      @toggle-menu="menuAberto = !menuAberto"
    />
    <MobileMenu
      v-if="!landingAtiva"
      :aberto="menuAberto"
      @fechar="menuAberto = false"
      @abrir-modal-auth="abrirModalAuth"
    />
    <main :class="classeMain">
      <RouterView v-slot="{ Component }">
        <Transition name="fade" mode="out-in">
          <component :is="Component" @abrir-modal-auth="abrirModalAuth" />
        </Transition>
      </RouterView>
    </main>
    <ToastContainer />
    <ModalAuth
      v-if="modalAuthAberto"
      :tab-inicial="modalAuthTab"
      @fechar="modalAuthAberto = false"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterView, useRoute, useRouter } from 'vue-router'
import { usarAutenticacaoStore } from './stores/autenticacao'
import AppHeader from './components/AppHeader.vue'
import MobileMenu from './components/MobileMenu.vue'
import ToastContainer from './components/ToastContainer.vue'
import ModalAuth from './components/ModalAuth.vue'

const autenticacao = usarAutenticacaoStore()
const route = useRoute()
const router = useRouter()

const menuAberto = ref(false)
const modalAuthAberto = ref(false)
const modalAuthTab = ref<'entrar' | 'cadastro'>('entrar')
const landingAtiva = computed(() => route.name === 'inicio')
const classeMain = computed(() => {
  if (landingAtiva.value) return 'w-full flex-1'
  if (route.name === 'cupom') return 'mx-auto w-full max-w-none flex-1 px-2 py-6 sm:px-3 lg:px-4'
  return 'mx-auto w-full max-w-6xl flex-1 px-4 py-6 sm:px-6'
})

function abrirModalAuth(tab: 'entrar' | 'cadastro') {
  modalAuthTab.value = tab
  modalAuthAberto.value = true
}

// Watch for ?modal=entrar|cadastro query param (from /entrar and /cadastro redirects)
watch(
  () => route.query.modal,
  (modal) => {
    if (modal === 'entrar' || modal === 'cadastro') {
      abrirModalAuth(modal)
      // Clean query param without triggering navigation
      router.replace({ ...route, query: { ...route.query, modal: undefined } })
    }
  },
  { immediate: true },
)

onMounted(() => {
  autenticacao.carregarUsuario()
})
</script>

<style>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
