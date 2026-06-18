import { chromium } from 'playwright'

const APP = 'http://localhost:5173'
const API = 'http://127.0.0.1:8888/api'
const OUT = 'C:/Projetos/Bolão Copa 2026/tools/mockup/raw'

const r = await fetch(`${API}/entrar`, {
  method: 'POST',
  headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
  body: JSON.stringify({ email: 'larissa.demo@interworldcup.local', password: 'demo12345' }),
})
const tok = (await r.json()).token
const cupons = await (await fetch(`${API}/cupons`, { headers: { Accept: 'application/json', Authorization: `Bearer ${tok}` } })).json()
const cupomId = cupons.cupons[0].id
console.log('cupom id:', cupomId)

const browser = await chromium.launch({ channel: 'msedge' })
const ctx = await browser.newContext({ viewport: { width: 400, height: 880 }, deviceScaleFactor: 2 })
await ctx.addInitScript(t => localStorage.setItem('token_acesso', t), tok)
const page = await ctx.newPage()
page.on('pageerror', e => console.log('pageerror:', String(e).split('\n')[0]))
await page.goto(`${APP}/cupons/${cupomId}`, { waitUntil: 'networkidle' })
await page.addStyleTag({ content: '*,*::before,*::after{animation:none!important;transition:none!important}' })
await page.waitForTimeout(1500)
await page.screenshot({ path: `${OUT}/cupom_dia1.png` })

// Avanca para a rodada 3 (dias futuros) pelo navegador de fase.
const proxima = 'button:has(path[d="M8.25 4.5l7.5 7.5-7.5 7.5"])'
await page.click(proxima)
await page.waitForTimeout(400)
await page.click(proxima)
await page.waitForTimeout(800)
// Primeiro dia da rodada futura ja vem selecionado.
await page.screenshot({ path: `${OUT}/cupom_diaN.png` })
const texto = await page.evaluate(() => document.body.innerText.match(/Fecha em[^\n]*|Fechado/g)?.slice(0, 3) ?? [])
console.log('titulo fase:', await page.evaluate(() => document.querySelector('h2')?.innerText))
console.log('textos de fechamento:', JSON.stringify(texto))
await browser.close()
