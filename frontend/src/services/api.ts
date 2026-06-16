const hostname = typeof window !== 'undefined' ? window.location.hostname : '127.0.0.1'
const API_BASE_URL = import.meta.env.VITE_API_URL ?? `http://${hostname}:8888/api`

// Origem do backend (sem o sufixo /api) para servir assets como /storage/...
const ASSET_BASE_URL = API_BASE_URL.replace(/\/api\/?$/, '')

/** Resolve um caminho de asset relativo do backend (ex: /storage/avatares/x.jpg) para URL absoluta. */
export function urlAsset(caminho: string | null | undefined): string | null {
  if (!caminho) return null
  if (/^https?:\/\//.test(caminho)) return caminho
  return `${ASSET_BASE_URL}${caminho.startsWith('/') ? '' : '/'}${caminho}`
}

type MetodoHttp = 'GET' | 'POST' | 'PUT' | 'DELETE'

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

/** Envia um arquivo via multipart/form-data (sem definir Content-Type manualmente). */
export async function uploadArquivo<T>(
  caminho: string,
  campo: string,
  arquivo: File,
  opcoes: { token?: string | null } = {},
): Promise<T> {
  const token = opcoes.token ?? obterToken()

  const headers: Record<string, string> = {
    Accept: 'application/json',
  }

  if (token) {
    headers.Authorization = `Bearer ${token}`
  }

  const formData = new FormData()
  formData.append(campo, arquivo)

  const resposta = await fetch(`${API_BASE_URL}${caminho}`, {
    method: 'POST',
    headers,
    body: formData,
  })

  const dados = await resposta.json().catch(() => ({}))

  if (!resposta.ok) {
    const mensagensValidacao =
      dados && typeof dados === 'object' && 'errors' in dados && dados.errors
        ? Object.values(dados.errors as Record<string, string[]>).flat().join(' ')
        : null

    const mensagem =
      mensagensValidacao || (typeof dados?.message === 'string' ? dados.message : 'Falha no envio.')

    throw new Error(mensagem)
  }

  return dados as T
}
