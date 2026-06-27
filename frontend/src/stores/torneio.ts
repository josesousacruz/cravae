import { ref } from 'vue'
import { defineStore } from 'pinia'
import { requisicaoApi } from '../services/api'
import type { Torneio } from '../tipos'

export const usarTorneioStore = defineStore('torneio', () => {
  const torneio = ref<Torneio | null>(null)
  const carregando = ref(false)
  const erro = ref<string | null>(null)
  const idCarregado = ref<number | null>(null)

  // Carrega o torneio por id (ex.: bolão ativo). Sem id, usa o /torneio público
  // (mais recente). Recarrega quando o id pedido muda (troca de bolão).
  async function carregar(id?: number | null) {
    const alvo = id ?? null
    if (torneio.value && idCarregado.value === alvo) return
    carregando.value = true
    erro.value = null
    try {
      const resp = await requisicaoApi<{ torneio: Torneio }>(alvo ? `/torneios/${alvo}` : '/torneio')
      torneio.value = resp.torneio
      idCarregado.value = alvo
    } catch (e) {
      erro.value = e instanceof Error ? e.message : 'Erro ao carregar torneio.'
    } finally {
      carregando.value = false
    }
  }

  async function recarregar(id?: number | null) {
    torneio.value = null
    idCarregado.value = null
    await carregar(id)
  }

  return { torneio, carregando, erro, carregar, recarregar }
})
