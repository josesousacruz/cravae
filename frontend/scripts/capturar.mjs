// Captura as telas reais do app (viewport mobile, DPR alto) para os mockups da LP.
import { chromium } from 'playwright'
import { mkdirSync } from 'node:fs'

const APP = 'http://localhost:5173'
const API = 'http://127.0.0.1:8888/api'
const OUT = 'C:/Projetos/Bolão Copa 2026/tools/mockup/raw'
const CUPOM_ID = 10
mkdirSync(OUT, { recursive: true })

const KILL_ANIM = '*,*::before,*::after{animation:none!important;transition:none!important;scroll-behavior:auto!important}'

async function token() {
  const r = await fetch(`${API}/entrar`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({ email: 'larissa.demo@interworldcup.local', password: 'demo12345' }),
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

async function settle(page, ms = 1000) {
  await page.addStyleTag({ content: KILL_ANIM }).catch(() => {})
  await page.waitForTimeout(ms)
}

async function clickText(page, text) {
  const ok = await page.evaluate(t => {
    const els = [...document.querySelectorAll('button, a, [role=tab], [role=button]')]
    const el = els.find(e => e.offsetParent !== null && e.textContent.trim().replace(/\s+/g, ' ') === t)
    if (el) { el.click(); return true }
    const partial = els.find(e => e.offsetParent !== null && e.textContent.trim().includes(t))
    if (partial) { partial.click(); return true }
    return false
  }, text)
  return ok
}

async function shot(page, name) {
  await page.screenshot({ path: `${OUT}/${name}.png` })
  console.log('  saved', name)
}

const browser = await chromium.launch({ channel: 'msedge' })
const tok = await token()
console.log('token ok')

// ── step-01: login (SEM token) ─────────────────────────────
{
  const { ctx, page } = await newPage(browser, null)
  await page.goto(`${APP}/?modal=entrar`, { waitUntil: 'networkidle' })
  await settle(page)
  await shot(page, 'login')
  await ctx.close()
}

// ── telas autenticadas ─────────────────────────────────────
{
  const { ctx, page } = await newPage(browser, tok)

  // painel: meus cupons
  await page.goto(`${APP}/painel`, { waitUntil: 'networkidle' })
  await settle(page, 1200)
  await shot(page, 'painel')

  // step-02: checkout (comprar cupom)
  await page.goto(`${APP}/checkout`, { waitUntil: 'networkidle' })
  await settle(page)
  await shot(page, 'checkout')

  // hero-center / step-03: palpites (jogos)
  await page.goto(`${APP}/cupons/${CUPOM_ID}`, { waitUntil: 'networkidle' })
  await settle(page, 1500)
  await shot(page, 'palpites_jogos')

  // feature-phone: "Quem palpitou" aberto numa partida (espera carregar a lista)
  await clickText(page, 'Quem palpitou')
  await page.waitForFunction(() => !document.body.innerText.includes('Carregando'), null, { timeout: 5000 }).catch(() => {})
  await settle(page, 800)
  await shot(page, 'quem_palpitou')

  // hero-left: chaveamento
  await page.goto(`${APP}/cupons/${CUPOM_ID}`, { waitUntil: 'networkidle' })
  await settle(page, 1200)
  await clickText(page, 'Chaveamento')
  await settle(page, 1200)
  await shot(page, 'chaveamento')

  // step-04: meus resultados
  await page.goto(`${APP}/cupons/${CUPOM_ID}`, { waitUntil: 'networkidle' })
  await settle(page, 1200)
  await clickText(page, 'Resultados')
  await settle(page, 1000)
  await shot(page, 'resultados')

  // hero-right: ranking do cupom
  await clickText(page, 'Ranking')
  await settle(page, 1000)
  await shot(page, 'ranking_cupom')

  await ctx.close()
}

// step-05: ranking global público
{
  const { ctx, page } = await newPage(browser, tok)
  await page.goto(`${APP}/ranking`, { waitUntil: 'networkidle' })
  await settle(page, 1200)
  await shot(page, 'ranking_global')
  await ctx.close()
}

await browser.close()
console.log('done')
