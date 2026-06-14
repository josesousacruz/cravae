import { ref } from 'vue'
import { requisicaoApi } from '../services/api'
import type { EventoPontuacao } from '../tipos'

// Busca/expande os eventos de pontuacao de um cupom (mesma visao de "Meus Resultados").
// Usado no ranking para abrir a linha de qualquer participante.
export function useEventosCupom() {
  const expandidoId = ref<number | null>(null)
  const cache = ref<Record<number, EventoPontuacao[]>>({})
  const carregandoId = ref<number | null>(null)

  async function alternar(cupomId: number) {
    if (expandidoId.value === cupomId) {
      expandidoId.value = null
      return
    }

    expandidoId.value = cupomId

    if (cache.value[cupomId]) return

    carregandoId.value = cupomId
    try {
      const resposta = await requisicaoApi<{ eventos_pontuacao: EventoPontuacao[] }>(
        `/ranking/cupons/${cupomId}/eventos`,
      )
      cache.value[cupomId] = resposta.eventos_pontuacao
    } catch {
      cache.value[cupomId] = []
    } finally {
      carregandoId.value = null
    }
  }

  function descricaoJogo(evento: EventoPontuacao): string {
    const jogo = evento.jogo
    if (!jogo) return ''
    const mandante = jogo.selecao_mandante?.nome ?? 'A definir'
    const visitante = jogo.selecao_visitante?.nome ?? 'A definir'
    const resultado = jogo.resultado
    if (resultado && resultado.placar_mandante !== null && resultado.placar_visitante !== null) {
      return `${mandante} ${resultado.placar_mandante} x ${resultado.placar_visitante} ${visitante}`
    }
    return `${mandante} x ${visitante}`
  }

  return { expandidoId, cache, carregandoId, alternar, descricaoJogo }
}
