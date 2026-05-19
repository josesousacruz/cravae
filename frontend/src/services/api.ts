const hostname = typeof window !== 'undefined' ? window.location.hostname : '127.0.0.1'
const API_BASE_URL = import.meta.env.VITE_API_URL ?? `http://${hostname}:8888/api`

type MetodoHttp = 'GET' | 'POST' | 'PUT'

type OpcoesRequisicao = {
  metodo?: MetodoHttp
  corpo?: Record<string, unknown>
  token?: string | null
}

function obterToken(): string | null {
  return localStorage.getItem('token_acesso')
}

export async function requisicaoApi<T>(caminho: string, opcoes: OpcoesRequisicao = {}): Promise<T> {
  const token = opcoes.token ?? obterToken()

  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  }

  if (token) {
    headers.Authorization = `Bearer ${token}`
  }

  const resposta = await fetch(`${API_BASE_URL}${caminho}`, {
    method: opcoes.metodo ?? 'GET',
    headers,
    body: opcoes.corpo ? JSON.stringify(opcoes.corpo) : undefined,
  })

  const dados = await resposta.json().catch(() => ({}))

  if (!resposta.ok) {
    const mensagensValidacao =
      dados && typeof dados === 'object' && 'errors' in dados && dados.errors
        ? Object.values(dados.errors as Record<string, string[]>).flat().join(' ')
        : null

    const mensagem =
      mensagensValidacao || (typeof dados?.message === 'string' ? dados.message : 'Falha na requisicao.')

    throw new Error(mensagem)
  }

  return dados as T
}
