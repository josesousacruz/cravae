import { ref } from 'vue'

type Toast = { id: number; tipo: 'sucesso' | 'erro'; mensagem: string }

const toasts = ref<Toast[]>([])
let contadorId = 0

export function useToast() {
  function mostrar(tipo: Toast['tipo'], mensagem: string) {
    const id = ++contadorId
    toasts.value.push({ id, tipo, mensagem })
    setTimeout(() => remover(id), 4000)
  }

  function remover(id: number) {
    toasts.value = toasts.value.filter(t => t.id !== id)
  }

  return { toasts, mostrar, remover }
}
