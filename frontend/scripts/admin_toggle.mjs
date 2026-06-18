import { chromium } from 'playwright'

const APP = 'http://localhost:5173'
const API = 'http://127.0.0.1:8888/api'
const OUT = 'C:/Projetos/Bolão Copa 2026/tools/mockup/raw'

const r = await fetch(`${API}/entrar`, {
  method: 'POST',
  headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
  body: JSON.stringify({ email: 'admin@interworldcup.local', password: '12345678' }),
})
const tok = (await r.json()).token

const browser = await chromium.launch({ channel: 'msedge' })
const ctx = await browser.newContext({ viewport: { width: 1100, height: 760 }, deviceScaleFactor: 1 })
await ctx.addInitScript(t => localStorage.setItem('token_acesso', t), tok)
const page = await ctx.newPage()
await page.goto(`${APP}/admin`, { waitUntil: 'networkidle' })
await page.addStyleTag({ content: '*,*::before,*::after{animation:none!important;transition:none!important}' })
await page.waitForTimeout(1200)
await page.screenshot({ path: `${OUT}/admin_antes.png`, clip: { x: 0, y: 0, width: 1100, height: 420 } })

await page.click('button[role="switch"]')
await page.waitForTimeout(1000)
await page.screenshot({ path: `${OUT}/admin_depois.png`, clip: { x: 0, y: 0, width: 1100, height: 420 } })

const estado = await (await fetch(`${API}/torneio`)).json()
console.log('compras_abertas apos toggle =', estado.torneio.compras_abertas)
await browser.close()
