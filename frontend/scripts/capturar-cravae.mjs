// Captura as telas reais do Cravaê (fase 09 — navegação Palpite Primeiro) para
// os mockups da landing. Saída: tools/mockup/raw do repo Bolão Copa 2026.
import { chromium } from 'playwright'
import { mkdirSync } from 'node:fs'

const APP = 'http://localhost:5174'
const API = 'http://127.0.0.1:8888/api'
const OUT = 'C:/Projetos/Bolão Copa 2026/tools/mockup/raw'
mkdirSync(OUT, { recursive: true })

const KILL_ANIM = '*,*::before,*::after{animation:none!important;transition:none!important;scroll-behavior:auto!important}'

async function token(email, senha) {
  const r = await fetch(`${API}/entrar`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({ email, password: senha }),
  })
  const j = await r.json()
  if (!j.token) throw new Error('login falhou: ' + JSON.stringify(j).slice(0, 200))
  return j.token
}

async function newPage(browser, tok) {
  const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 3 })
  if (tok) await ctx.addInitScript(t => localStorage.setItem('token_acesso', t), tok)
  const page = await ctx.newPage()
  page.on('pageerror', e => console.log('  pageerror:', String(e).split('\n')[0]))
  return { ctx, page }
}

async function settle(page, ms = 1500) {
  await page.addStyleTag({ content: KILL_ANIM }).catch(() => {})
  await page.waitForTimeout(ms)
}

async function irEEsperar(page, rota, seletor) {
  await page.goto(`${APP}${rota}`, { waitUntil: 'domcontentloaded' })
  await page.waitForSelector(seletor, { timeout: 30000 }).catch(() => {})
  await settle(page)
}

async function shot(page, name) {
  await page.screenshot({ path: `${OUT}/${name}.png` })
  console.log('  saved', name)
}

const browser = await chromium.launch({ channel: 'msedge' })
const tokAdmin = await token('admin@interworldcup.local', '12345678')
console.log('token ok')

// ── step-01: landing com modal de entrar (SEM token) ───────
{
  const { ctx, page } = await newPage(browser, null)
  await page.goto(APP, { waitUntil: 'networkidle' })
  await settle(page)
  await page.evaluate(() => {
    const els = [...document.querySelectorAll('button, a')]
    els.find(e => e.textContent.trim() === 'Entrar')?.click()
  })
  await settle(page, 1500)
  await shot(page, 'login')
  await ctx.close()
}

// ── home de palpites (Hero-center + feature-phone) ─────────
{
  const { ctx, page } = await newPage(browser, tokAdmin)
  await irEEsperar(page, '/palpites', 'text=Rodada')
  // preenche um placar para revelar o bloco de goleadores
  await page.evaluate(async () => {
    const espera = ms => new Promise(r => setTimeout(r, ms))
    const mais = [...document.querySelectorAll('button')].filter(b => b.innerText.trim() === '+' && b.offsetParent !== null)
    if (mais[0]) { mais[0].click(); await espera(350); mais[0].click() }
  })
  await settle(page, 1500)
  await shot(page, 'palpites_jogos')
  await shot(page, 'painel')

  // popover "Quem palpitou" (step-03)
  await page.evaluate(() => {
    const b = [...document.querySelectorAll('button')].find(x => x.innerText.includes('Quem palpitou'))
    b?.click()
  })
  await settle(page, 1200)
  await shot(page, 'quem_palpitou')
  await ctx.close()
}

// ── assinatura (step-02) ───────────────────────────────────
{
  const { ctx, page } = await newPage(browser, tokAdmin)
  await irEEsperar(page, '/assinatura', 'text=Assinatura')
  await shot(page, 'checkout')
  await ctx.close()
}

// ── resultados (step-04) e histórico (hero-left) ───────────
{
  const { ctx, page } = await newPage(browser, tokAdmin)
  await irEEsperar(page, '/resultados', 'text=Meus pontos')
  await shot(page, 'resultados')
  await page.evaluate(() => window.scrollBy(0, 600))
  await settle(page, 800)
  await shot(page, 'chaveamento')
  await ctx.close()
}

// ── ranking (Hero-right + step-05) ─────────────────────────
{
  const { ctx, page } = await newPage(browser, tokAdmin)
  await irEEsperar(page, '/ranking', 'text=Ranking')
  await shot(page, 'ranking_cupom')
  await shot(page, 'ranking_global')
  await ctx.close()
}

await browser.close()
console.log('done')
