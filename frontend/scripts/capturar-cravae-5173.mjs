// Captura as telas reais do Cravaê (viewport mobile, DPR alto) para os mockups
// da LP do repositório do bolão. Saída: tools/mockup/raw do repo Bolão Copa 2026.
import { chromium } from 'playwright'
import { mkdirSync } from 'node:fs'

const APP = 'http://localhost:5173'
const API = 'http://127.0.0.1:8888/api'
const OUT = 'C:/Projetos/Bolão Copa 2026/tools/mockup/raw'
const CUPOM_ID = 7
mkdirSync(OUT, { recursive: true })

const KILL_ANIM = '*,*::before,*::after{animation:none!important;transition:none!important;scroll-behavior:auto!important}'

async function token() {
  const r = await fetch(`${API}/entrar`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({ email: 'admin@interworldcup.local', password: '12345678' }),
  })
  const j = await r.json()
  if (!j.token) throw new Error('login falhou: ' + JSON.stringify(j).slice(0, 200))
  return j.token
}

async function newPage(browser, tok) {
  const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 3 })
  if (tok) await ctx.addInitScript(t => localStorage.setItem('token_acesso', t), tok)
  await ctx.addInitScript(() => {
    localStorage.setItem('aviso_mata_mata_v1', '1')
    localStorage.setItem('bolao_ativo_id', '3') // Brasileirão Série A
  })
  const page = await ctx.newPage()
  page.on('pageerror', e => console.log('  pageerror:', String(e).split('\n')[0]))
  return { ctx, page }
}

async function settle(page, ms = 1200) {
  await page.addStyleTag({ content: KILL_ANIM }).catch(() => {})
  await page.waitForTimeout(ms)
}

async function clickText(page, text) {
  return page.evaluate(t => {
    const els = [...document.querySelectorAll('button, a, [role=tab], [role=button]')]
    const el = els.find(e => e.offsetParent !== null && e.textContent.trim().replace(/\s+/g, ' ') === t)
      || els.find(e => e.offsetParent !== null && e.textContent.trim().includes(t))
    if (el) { el.click(); return true }
    return false
  }, text)
}

async function shot(page, name) {
  await page.screenshot({ path: `${OUT}/${name}.png` })
  console.log('  saved', name)
}

const browser = await chromium.launch({ channel: 'msedge' })
const tok = await token()
console.log('token ok')

// ── step-01: landing com modal de entrar (SEM token) ───────
{
  const { ctx, page } = await newPage(browser, null)
  await page.goto(APP, { waitUntil: 'networkidle' })
  await settle(page)
  await clickText(page, 'Entrar')
  await settle(page, 1500)
  await shot(page, 'login')
  await ctx.close()
}

// ── painel (feature-phone) ─────────────────────────────────
{
  const { ctx, page } = await newPage(browser, tok)
  await page.goto(`${APP}/painel`, { waitUntil: 'networkidle' })
  await settle(page, 1800)
  await shot(page, 'painel')
  await ctx.close()
}

// ── checkout (step-02) ─────────────────────────────────────
{
  const { ctx, page } = await newPage(browser, tok)
  await page.goto(`${APP}/checkout`, { waitUntil: 'networkidle' })
  await settle(page, 1500)
  await shot(page, 'checkout')
  await ctx.close()
}

// ── cupom: palpites com goleador (Hero-center) + variações ─
{
  const { ctx, page } = await newPage(browser, tok)
  await page.goto(`${APP}/cupons/${CUPOM_ID}`, { waitUntil: 'networkidle' })
  await settle(page, 2000)

  // Preenche placar 2x1 no 1º jogo e 1x0 no 2º para revelar o bloco de goleadores.
  await page.evaluate(async () => {
    const espera = ms => new Promise(r => setTimeout(r, ms))
    const mais = () => [...document.querySelectorAll('button')].filter(b => b.innerText.trim() === '+' && b.offsetParent !== null)
    let b = mais()
    if (b[0]) { b[0].click(); await espera(350); b[0].click(); await espera(350) } // mandante 2
    b = mais()
    if (b[1]) { b[1].click(); await espera(350) }                                  // visitante 1
    b = mais()
    if (b[2]) { b[2].click(); await espera(350) }                                  // 2º jogo mandante 1
  })
  await settle(page, 1500)
  await shot(page, 'palpites_jogos')

  // Popover "Quem palpitou" (step-03)
  await clickText(page, 'Quem palpitou')
  await settle(page, 1200)
  await shot(page, 'quem_palpitou')
  await page.keyboard.press('Escape').catch(() => {})
  await settle(page, 600)

  // Aba Meus Resultados (step-04)
  await clickText(page, 'Meus Resultados')
  await settle(page, 1500)
  await shot(page, 'resultados')

  // Aba Ranking do cupom (Hero-right)
  await clickText(page, 'Ranking')
  await settle(page, 1500)
  await shot(page, 'ranking_cupom')

  // Aba Histórico (hero-left)
  await clickText(page, 'Histórico')
  await settle(page, 1500)
  await shot(page, 'chaveamento')

  await ctx.close()
}

// ── ranking global (step-05) ───────────────────────────────
{
  const { ctx, page } = await newPage(browser, tok)
  await page.goto(`${APP}/ranking`, { waitUntil: 'networkidle' })
  await settle(page, 1800)
  await shot(page, 'ranking_global')
  await ctx.close()
}

await browser.close()
console.log('done')
