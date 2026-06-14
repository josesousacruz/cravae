// Dados fixos de recebimento (Pix recebido por fora do sistema).
// A chave Pix e um telefone; o WhatsApp para envio do comprovante e o mesmo numero.
export const PAGAMENTO = {
  pixChave: '71997200967',
  // Formato exigido pelo BR Code para chave do tipo telefone.
  pixChaveBrCode: '+5571997200967',
  recebedorNome: 'BOLAO INTER',
  recebedorCidade: 'SALVADOR',
  whatsapp: '5571997200967',
} as const
