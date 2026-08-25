/* Service Worker do Inter World Cup (PWA).
 * Estratégia:
 *  - Navegação (páginas): network-first com fallback para o app shell em cache
 *    (garante atualização imediata quando online e funcionamento offline / deep links SPA).
 *  - Assets estáticos com hash (/assets/...): stale-while-revalidate.
 *  - Chamadas de API (outra origem, ex: :8888) NÃO são interceptadas.
 * Ao publicar uma nova versão, altere CACHE para invalidar o cache antigo.
 */
const CACHE = 'iwc-v1'

const APP_SHELL = [
  '/',
  '/manifest.webmanifest',
  '/favicon.svg',
  '/apple-touch-icon.png',
  '/pwa-192.png',
  '/pwa-512.png',
]

self.addEventListener('install', (event) => {
  event.waitUntil(
    (async () => {
      const cache = await caches.open(CACHE)
      // Adiciona item a item para que uma URL ausente não impeça o restante.
      await Promise.all(
        APP_SHELL.map((url) => cache.add(url).catch(() => undefined)),
      )
      await self.skipWaiting()
    })(),
  )
})

self.addEventListener('activate', (event) => {
  event.waitUntil(
    (async () => {
      const chaves = await caches.keys()
      await Promise.all(
        chaves.filter((k) => k !== CACHE).map((k) => caches.delete(k)),
      )
      await self.clients.claim()
    })(),
  )
})

self.addEventListener('message', (event) => {
  if (event.data === 'SKIP_WAITING') self.skipWaiting()
})

self.addEventListener('fetch', (event) => {
  const req = event.request
  if (req.method !== 'GET') return

  const url = new URL(req.url)
  // Só lida com a própria origem (nunca com a API em outra porta/host).
  if (url.origin !== self.location.origin) return

  // Navegações: network-first, cai para o app shell quando offline.
  if (req.mode === 'navigate') {
    event.respondWith(
      (async () => {
        try {
          const fresh = await fetch(req)
          const cache = await caches.open(CACHE)
          cache.put('/', fresh.clone())
          return fresh
        } catch {
          const cache = await caches.open(CACHE)
          return (
            (await cache.match(req)) ||
            (await cache.match('/')) ||
            Response.error()
          )
        }
      })(),
    )
    return
  }

  // Demais GETs da mesma origem (JS/CSS/imagens com hash): stale-while-revalidate.
  event.respondWith(
    (async () => {
      const cache = await caches.open(CACHE)
      const cached = await cache.match(req)
      const rede = fetch(req)
        .then((res) => {
          if (res && res.status === 200 && res.type === 'basic') {
            cache.put(req, res.clone())
          }
          return res
        })
        .catch(() => null)
      return cached || (await rede) || Response.error()
    })(),
  )
})
