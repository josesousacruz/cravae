<template>
  <Teleport to="body">
    <div
      v-if="aberto"
      class="fixed inset-0 z-50 flex items-end justify-center bg-black/70 p-0 sm:items-center sm:p-4"
      @click.self="$emit('fechar')"
    >
      <div class="max-h-[92vh] w-full max-w-md overflow-y-auto rounded-t-3xl border border-border bg-bg-card p-6 shadow-2xl sm:rounded-3xl">
        <div class="mb-4 flex items-start justify-between gap-3">
          <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-primary">Pagamento via Pix</p>
            <h2 class="mt-1 text-lg font-bold text-text">Cupom {{ cupomCodigo }}</h2>
          </div>
          <button type="button" class="rounded-lg p-1.5 text-text-muted transition hover:bg-bg-input hover:text-text" @click="$emit('fechar')">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
          </button>
        </div>

        <div v-if="valorFormatado" class="mb-4 rounded-xl bg-primary/10 px-4 py-3 text-center">
          <p class="text-xs text-text-muted">Valor</p>
          <p class="text-2xl font-bold text-primary">{{ valorFormatado }}</p>
        </div>

        <!-- QR Code -->
        <div class="mx-auto mb-4 w-fit rounded-2xl bg-white p-3">
          <img v-if="qrCodeUrl" :src="qrCodeUrl" alt="QR Code Pix" class="h-52 w-52" />
          <div v-else class="flex h-52 w-52 items-center justify-center text-sm text-gray-400">Gerando QR...</div>
        </div>

        <!-- Chave -->
        <div class="mb-3 rounded-xl border border-border bg-bg-input p-3">
          <p class="text-[11px] uppercase tracking-wider text-text-muted">Chave Pix (telefone)</p>
          <div class="mt-1 flex items-center justify-between gap-2">
            <span class="font-mono text-sm font-bold text-text">{{ pixChave }}</span>
            <button type="button" class="shrink-0 rounded-lg bg-bg-card px-3 py-1.5 text-xs font-semibold text-primary transition hover:bg-primary/10" @click="copiar(pixChave, 'Chave Pix copiada.')">
              Copiar chave
            </button>
          </div>
        </div>

        <!-- Copia e cola -->
        <div class="mb-4">
          <button type="button" class="w-full rounded-xl bg-primary py-3 text-sm font-bold text-bg transition hover:bg-primary-hover" @click="copiar(copiaECola, 'Codigo Pix copiado.')">
            Copiar codigo Pix (copia e cola)
          </button>
        </div>

        <!-- Instrucoes + WhatsApp -->
        <div class="rounded-xl border border-primary/20 bg-primary/5 p-4">
          <p class="mb-2 text-sm font-semibold text-text">Como liberar seu cupom</p>
          <ol class="mb-3 list-decimal space-y-1 pl-4 text-xs text-text-secondary">
            <li>Pague {{ valorFormatado || 'o valor' }} usando o QR Code ou a chave Pix acima.</li>
            <li>Envie o comprovante no nosso WhatsApp.</li>
            <li>Seu cupom e liberado manualmente apos a confirmacao.</li>
          </ol>
          <a
            :href="whatsappUrl"
            target="_blank"
            rel="noopener"
            class="flex w-full items-center justify-center gap-2 rounded-xl bg-[#25D366] py-3 text-sm font-bold text-black transition hover:brightness-95"
          >
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M.057 24l1.687-6.163a11.867 11.867 0 01-1.587-5.945C.16 5.335 5.495 0 12.05 0a11.817 11.817 0 018.413 3.488 11.824 11.824 0 013.48 8.414c-.003 6.557-5.338 11.892-11.893 11.892a11.9 11.9 0 01-5.688-1.448L.057 24zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884a9.86 9.86 0 001.51 5.26l-.999 3.648 3.978-1.555zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.074-.149-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
            Enviar comprovante no WhatsApp
          </a>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import QRCode from 'qrcode'
import { PAGAMENTO } from '../config/pagamento'
import { gerarPixCopiaECola } from '../services/pix'
import { useToast } from '../composables/useToast'

const props = defineProps<{
  aberto: boolean
  cupomCodigo: string
  valor?: number | string | null
}>()

defineEmits<{ fechar: [] }>()

const { mostrar } = useToast()
const qrCodeUrl = ref('')
const pixChave = PAGAMENTO.pixChave

const valorNumerico = computed(() => {
  const n = Number(props.valor)
  return Number.isFinite(n) && n > 0 ? n : null
})

const valorFormatado = computed(() =>
  valorNumerico.value != null
    ? new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(valorNumerico.value)
    : '',
)

const copiaECola = computed(() =>
  gerarPixCopiaECola({
    chave: PAGAMENTO.pixChaveBrCode,
    nome: PAGAMENTO.recebedorNome,
    cidade: PAGAMENTO.recebedorCidade,
    valor: valorNumerico.value,
  }),
)

const whatsappUrl = computed(() => {
  const mensagem = `Ola! Segue o comprovante do Pix do cupom ${props.cupomCodigo} do Bolao Inter.`
  return `https://wa.me/${PAGAMENTO.whatsapp}?text=${encodeURIComponent(mensagem)}`
})

watch(
  () => [props.aberto, copiaECola.value] as const,
  async ([aberto, payload]) => {
    if (!aberto) return
    try {
      qrCodeUrl.value = await QRCode.toDataURL(payload, { width: 220, margin: 1 })
    } catch {
      qrCodeUrl.value = ''
    }
  },
  { immediate: true },
)

async function copiar(texto: string, sucesso: string) {
  try {
    await navigator.clipboard.writeText(texto)
    mostrar('sucesso', sucesso)
  } catch {
    mostrar('erro', 'Nao foi possivel copiar.')
  }
}
</script>
