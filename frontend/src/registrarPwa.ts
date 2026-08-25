/**
 * Registra o service worker do PWA.
 * - Só registra em build de produção (evita cache atrapalhando o dev).
 * - Verifica atualizações periodicamente e ao voltar o foco da aba.
 */
export function registrarPwa(): void {
  if (!import.meta.env.PROD) return
  if (typeof navigator === 'undefined' || !('serviceWorker' in navigator)) return

  window.addEventListener('load', () => {
    navigator.serviceWorker
      .register('/sw.js')
      .then((registro) => {
        // Verifica atualização periodicamente (a cada 1h) e ao focar a aba.
        const verificar = () => registro.update().catch(() => undefined)
        setInterval(verificar, 60 * 60 * 1000)
        document.addEventListener('visibilitychange', () => {
          if (document.visibilityState === 'visible') verificar()
        })
      })
      .catch(() => {
        // Falha ao registrar não deve quebrar o app.
      })
  })
}
