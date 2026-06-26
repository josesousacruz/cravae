import { ref } from 'vue'
import { defineStore } from 'pinia'
import { requisicaoApi } from '../services/api'
import type { Bolao, RespostaBoloes } from '../tipos'

const CHAVE = 'bolao_ativo_id'

export const usarBolaoAtivoStore = defineStore('bolaoAtivo', () => {
  const lista = ref<Bolao[]>([])
  const ativoId = ref<number | null>(
    localStorage.getItem(CHAVE) ? Number(localStorage.getItem(CHAVE)) : null,
  )
  const carregando = ref(false)

  function definirAtivo(id: number | null) {
    ativoId.value = id
    if (id === null) localStorage.removeItem(CHAVE)
    else localStorage.setItem(CHAVE, String(id))
  }

  async function carregar() {
    carregando.value = true
    try {
      const resp = await requisicaoApi<RespostaBoloes>('/boloes')
      lista.value = [...resp.ativos, ...resp.encerrados]
      // Default: o bolão principal (id mais antigo entre os ativos) quando ainda
      // não houver seleção válida — assim quem já tem cupom no bolão completo cai
      // direto nele. A preferência do usuário (localStorage) sempre prevalece.
      const valido = lista.value.some((b) => b.id === ativoId.value)
      if (!valido) {
        const principal = [...resp.ativos].sort((a, b) => a.id - b.id)[0]
        definirAtivo(principal?.id ?? lista.value[0]?.id ?? null)
      }
    } finally {
      carregando.value = false
    }
  }

  function ativo(): Bolao | null {
    return lista.value.find((b) => b.id === ativoId.value) ?? null
  }

  return { lista, ativoId, ativo, carregando, carregar, definirAtivo }
})
