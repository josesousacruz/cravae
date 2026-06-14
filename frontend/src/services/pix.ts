// Gera o payload "Pix Copia e Cola" (BR Code / EMV) para uma chave estatica.
// Referencia: especificacao EMV QRCPS-MPM do Banco Central.

function campo(id: string, valor: string): string {
  const tamanho = valor.length.toString().padStart(2, '0')
  return `${id}${tamanho}${valor}`
}

function crc16(payload: string): string {
  let crc = 0xffff
  for (let i = 0; i < payload.length; i++) {
    crc ^= payload.charCodeAt(i) << 8
    for (let bit = 0; bit < 8; bit++) {
      crc = (crc & 0x8000) !== 0 ? (crc << 1) ^ 0x1021 : crc << 1
      crc &= 0xffff
    }
  }
  return crc.toString(16).toUpperCase().padStart(4, '0')
}

// Remove acentos e caracteres invalidos para nome/cidade do BR Code.
function sanitizar(texto: string, limite: number): string {
  return texto
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .replace(/[^A-Za-z0-9 ]/g, '')
    .toUpperCase()
    .slice(0, limite)
    .trim()
}

export type OpcoesPix = {
  chave: string
  nome: string
  cidade: string
  valor?: number | null
  txid?: string
}

export function gerarPixCopiaECola({ chave, nome, cidade, valor, txid = '***' }: OpcoesPix): string {
  const contaRecebedor = campo('00', 'br.gov.bcb.pix') + campo('01', chave)

  const valorNumerico = valor != null && Number(valor) > 0 ? Number(valor) : null

  let payload =
    campo('00', '01') +
    campo('26', contaRecebedor) +
    campo('52', '0000') +
    campo('53', '986') +
    (valorNumerico != null ? campo('54', valorNumerico.toFixed(2)) : '') +
    campo('58', 'BR') +
    campo('59', sanitizar(nome, 25)) +
    campo('60', sanitizar(cidade, 15)) +
    campo('62', campo('05', txid))

  payload += '6304'
  return payload + crc16(payload)
}
