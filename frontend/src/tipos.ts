export type PerfilUsuario = 'visitante' | 'usuario' | 'administrador'

export type UsuarioAutenticado = {
  id: number
  nome: string
  email: string
  telefone: string | null
  cpf_cnpj: string | null
  foto_url: string | null
  perfil: PerfilUsuario
}

export type PedidoCheckout = {
  id: number
  valor: string
  status: string
  forma_pagamento: string | null
  referencia_checkout: string | null
  asaas_pagamento_id: string | null
  asaas_status: string | null
  invoice_url: string | null
  pix_copia_cola: string | null
  pix_qr_code_base64: string | null
  pix_expira_at: string | null
  erro_pagamento: string | null
  pago_at: string | null
  cupons?: Cupom[]
}

export type PontuacaoCupom = {
  id: number
  pontuacao_total: string
  quantidade_placares_exatos: number
  quantidade_classificados_corretos: number
  quantidade_palpites_finais_corretos: number
  ultimo_recalculo_at: string | null
}

export type Cupom = {
  id: number
  codigo: string
  status: string
  pedido_checkout_id: number | null
  pedido_checkout?: PedidoCheckout | null
  pontuacao?: PontuacaoCupom | null
  eventos_pontuacao?: EventoPontuacao[]
}

export type Jogador = {
  id: number
  selecao_id: number
  nome: string
  apelido: string | null
}

export type Selecao = {
  id: number
  grupo_id: number | null
  nome: string
  sigla: string
  jogadores?: Jogador[]
}

export type Grupo = {
  id: number
  nome: string
  ordem: number
  selecoes: Selecao[]
}

export type Fase = {
  id: number
  nome: string
  slug: string
  ordem: number
  tipo: string
  data_fechamento: string | null
}

export type Rodada = {
  id: number
  nome: string
  ordem: number
  data_fechamento: string | null
}

export type ResultadoJogo = {
  id: number
  jogo_id: number
  placar_mandante: number | null
  placar_visitante: number | null
  selecao_classificada_id: number | null
}

export type Jogo = {
  id: number
  fase_id: number
  rodada_id: number | null
  grupo_id: number | null
  data_hora_inicio: string
  ordem_na_fase: number
  status: string
  fase: Fase
  rodada?: Rodada | null
  grupo?: Grupo | null
  selecao_mandante: Selecao | null
  selecao_visitante: Selecao | null
  participantes_admin?: Selecao[]
  resultado?: ResultadoJogo | null
}

export type BracketJogoCupom = {
  id: number
  jogo_base_id: number
  fase_id: number
  rodada_id: number | null
  grupo_id: number | null
  data_hora_inicio: string
  ordem_na_fase: number
  status: string
  fase: Fase
  rodada?: Rodada | null
  grupo?: Grupo | null
  selecao_mandante: Selecao | null
  selecao_visitante: Selecao | null
  resultado?: ResultadoJogo | null
  bloqueado: boolean
  motivo_bloqueio: string | null
}

export type ResumoBracketCupom = {
  campeao_selecao_id: number | null
  vice_campeao_selecao_id: number | null
  terceiro_colocado_selecao_id: number | null
}

export type RegraPontuacao = {
  id: number
  fase_id: number | null
  chave: string
  nome: string
  descricao: string | null
  pontos: number
  ativo: boolean
}

export type ResultadoTorneio = {
  campeao_selecao_id: number | null
  vice_campeao_selecao_id: number | null
  terceiro_colocado_selecao_id: number | null
  artilheiro_jogador_id: number | null
}

export type Torneio = {
  id: number
  nome: string
  edicao: string
  status: string
  data_inicio: string | null
  data_fim: string | null
  valor_cupom: number
  grupos: Grupo[]
  fases: Fase[]
  jogos: Jogo[]
  regras_pontuacao: RegraPontuacao[]
  resultado_torneio?: ResultadoTorneio | null
}

export type Aposta = {
  id: number
  tipo: string
  grupo_id: number | null
  jogo_id: number | null
  selecao_id: number | null
  jogador_id: number | null
  conteudo: Record<string, number | null>
}

export type EventoPontuacao = {
  id: number
  descricao: string
  pontos: number
  jogo_id?: number | null
  jogo?: {
    id: number
    selecao_mandante: Selecao | null
    selecao_visitante: Selecao | null
    resultado?: ResultadoJogo | null
  } | null
}

export type RankingItem = {
  id: number
  pontuacao_total: string
  quantidade_placares_exatos: number
  quantidade_classificados_corretos: number
  quantidade_palpites_finais_corretos: number
  cupom: {
    id: number
    codigo: string
    usuario: {
      nome: string
      foto_url: string | null
    }
  }
}

export type MinhaPosicao = {
  posicao: number
  item: RankingItem
}

export type RespostaRanking = {
  ranking: RankingItem[]
  minha_posicao: MinhaPosicao | null
  partidas: {
    finalizadas: number
    total: number
  }
}
