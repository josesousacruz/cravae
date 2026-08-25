import { ref } from 'vue'
import { defineStore } from 'pinia'
import { requisicaoApi } from '../services/api'
import type { Cupom } from '../tipos'

/**
 * Fase 09 (Palpite Primeiro): o cupom único do usuário no torneio principal.
 * O backend garante a criação (firstOrCreate) — aqui só se resolve e cacheia.
 */
export const usarMeuCupomStore = defineStore('meuCupom', () => {
  const cupom = ref<Cupom | null>(null)
  const carregando = ref(false)
  const erro = ref<string | null>(null)

  async function carregar(forcar = false) {
    if (cupom.value && !forcar) return cupom.value
    carregando.value = true
    erro.value = null
    try {
      const resposta = await requisicaoApi<{ cupom: Cupom }>('/meu-cupom')
      cupom.value = resposta.cupom
      return cupom.value
    } catch (excecao) {
      erro.value = excecao instanceof Error ? excecao.message : 'Não foi possível carregar seu cupom.'
      return null
    } finally {
      carregando.value = false
    }
  }

  function limpar() {
    cupom.value = null
    erro.value = null
  }

  return { cupom, carregando, erro, carregar, limpar }
})
