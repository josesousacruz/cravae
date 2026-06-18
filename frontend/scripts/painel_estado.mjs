// Captura o PainelView logado como demo, no estado atual da flag compras_abertas.
import { chromium } from 'playwright'

const APP = 'http://localhost:5173'
const API = 'http://127.0.0.1:8888/api'
const OUT = 'C:/Projetos/Bolão Copa 2026/tools/mockup/raw'
const nome = process.argv[2] || 'painel_estado'

const r = await fetch(`${API}/entrar`, {
  method: 'POST',
  headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
  body: JSON.stringify({ email: 'larissa.demo@interworldcup.local', password: 'demo12345' }),
})
const tok = (await r.json()).token

const browser = await chromium.launch({ channel: 'msedge' })
const ctx = await browser.newContext({ viewport: { width: 1100, height: 800 }, deviceScaleFactor: 2 })
await ctx.addInitScript(t => localStorage.setItem('token_acesso', t), tok)
const page = await ctx.newPage()
await page.goto(`${APP}/painel`, { waitUntil: 'networkidle' })
await page.addStyleTag({ content: '*,*::before,*::after{animation:none!important;transition:none!important}' })
await page.waitForTimeout(1200)
await page.screenshot({ path: `${OUT}/${nome}.png` })
console.log('saved', nome)
await browser.close()
