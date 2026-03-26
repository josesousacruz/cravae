<template>
  <div class="min-h-screen flex flex-col">
    <AppHeader
      @abrir-modal-auth="abrirModalAuth"
      @toggle-menu="menuAberto = !menuAberto"
    />
    <MobileMenu
      :aberto="menuAberto"
      @fechar="menuAberto = false"
      @abrir-modal-auth="abrirModalAuth"
    />
    <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-6 sm:px-6">
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
import { onMounted, ref } from 'vue'
import { RouterView } from 'vue-router'
import { usarAutenticacaoStore } from './stores/autenticacao'
import AppHeader from './components/AppHeader.vue'
import MobileMenu from './components/MobileMenu.vue'
import ToastContainer from './components/ToastContainer.vue'
import ModalAuth from './components/ModalAuth.vue'

const autenticacao = usarAutenticacaoStore()

const menuAberto = ref(false)
const modalAuthAberto = ref(false)
const modalAuthTab = ref<'entrar' | 'cadastro'>('entrar')

function abrirModalAuth(tab: 'entrar' | 'cadastro') {
  modalAuthTab.value = tab
  modalAuthAberto.value = true
}

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
