import { chromium } from 'playwright'
const APP = 'http://localhost:5173', API = 'http://127.0.0.1:8888/api'
const OUT = 'C:/Projetos/Bolão Copa 2026/tools/mockup/raw'
const tok = (await (await fetch(`${API}/entrar`, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify({ email: 'larissa.demo@interworldcup.local', password: 'demo12345' }) })).json()).token
const cupomId = (await (await fetch(`${API}/cupons`, { headers: { Accept: 'application/json', Authorization: `Bearer ${tok}` } })).json()).cupons[0].id
const browser = await chromium.launch({ channel: 'msedge' })
const ctx = await browser.newContext({ viewport: { width: 400, height: 880 }, deviceScaleFactor: 2 })
await ctx.addInitScript(t => localStorage.setItem('token_acesso', t), tok)
const page = await ctx.newPage()
await page.goto(`${APP}/cupons/${cupomId}`, { waitUntil: 'networkidle' })
await page.addStyleTag({ content: '*,*::before,*::after{animation:none!important;transition:none!important}' })
await page.waitForTimeout(1200)
// Avanca para a rodada 2.
await page.click('button:has(path[d="M8.25 4.5l7.5 7.5-7.5 7.5"])')
await page.waitForTimeout(700)
await page.screenshot({ path: `${OUT}/cupom_dia18.png` })
const info = await page.evaluate(() => ({
  fase: document.querySelector('h2')?.innerText,
  fechamentoHeader: document.body.innerText.match(/Fecha em[^\n]*|Fechado/)?.[0],
  steppersDesabilitados: document.querySelectorAll('section button[disabled]').length,
  steppersTotal: document.querySelectorAll('section button').length,
}))
console.log('cupom', cupomId, JSON.stringify(info))
await browser.close()
