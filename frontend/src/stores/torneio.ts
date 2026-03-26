import { ref } from 'vue'
import { defineStore } from 'pinia'
import { requisicaoApi } from '../services/api'
import type { Torneio } from '../tipos'

export const usarTorneioStore = defineStore('torneio', () => {
  const torneio = ref<Torneio | null>(null)
  const carregando = ref(false)
  const erro = ref<string | null>(null)

  async function carregar() {
    if (torneio.value) return
    carregando.value = true
    erro.value = null
    try {
      const resp = await requisicaoApi<{ torneio: Torneio }>('/torneio')
      torneio.value = resp.torneio
    } catch (e) {
      erro.value = e instanceof Error ? e.message : 'Erro ao carregar torneio.'
    } finally {
      carregando.value = false
    }
  }

  async function recarregar() {
    torneio.value = null
    await carregar()
  }

  return { torneio, carregando, erro, carregar, recarregar }
})
