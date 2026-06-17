// Compõe cada screenshot numa moldura iPhone titânio esmeralda e exporta .webp transparente.
import { chromium } from 'playwright'
import { readFileSync, writeFileSync } from 'node:fs'

const RAW = 'C:/Projetos/Bolão Copa 2026/tools/mockup/raw'
const ASSETS = 'C:/Projetos/Bolão Copa 2026/frontend/src/assets'

// raw -> asset (sem extensão). tilt = inclinação 3D embutida.
const MAP = [
  { raw: 'palpites_jogos', asset: 'Hero-center', tilt: false },
  { raw: 'chaveamento', asset: 'hero-left', tilt: false },
  { raw: 'ranking_cupom', asset: 'Hero-right', tilt: false },
  { raw: 'login', asset: 'step-01', tilt: false },
  { raw: 'checkout', asset: 'step-02', tilt: false },
  { raw: 'quem_palpitou', asset: 'step-03', tilt: false },
  { raw: 'resultados', asset: 'step-04', tilt: false },
  { raw: 'ranking_global', asset: 'step-05', tilt: false },
  { raw: 'painel', asset: 'feature-phone-tilted', tilt: true },
]

function html(shotUrl, tilt) {
  return `<!doctype html><meta charset=utf8>
  <style>
    html,body{margin:0;background:transparent}
    .stage{padding:${tilt ? 150 : 90}px;display:inline-block}
    .device{
      width:380px; aspect-ratio:390/844; border-radius:64px; padding:13px;
      background:linear-gradient(145deg,#2a6f59 0%,#0d1d18 30%,#0a1411 55%,#123b30 80%,#2a6f59 100%);
      box-shadow:
        inset 0 0 0 2px rgba(140,235,195,.40),
        inset 0 3px 7px rgba(255,255,255,.22),
        inset 0 -4px 9px rgba(0,0,0,.55);
      position:relative;
      ${tilt ? 'transform:perspective(2300px) rotateY(-24deg) rotateX(6deg) rotate(-2deg);' : ''}
    }
    .screen{position:relative;width:100%;height:100%;border-radius:51px;overflow:hidden;background:#070909;box-shadow:inset 0 0 0 2px #000}
    .screen img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;object-position:top center;display:block}
    .island{position:absolute;top:13px;left:50%;transform:translateX(-50%);width:26%;height:20px;background:#000;border-radius:12px;z-index:3;box-shadow:0 0 0 1px rgba(255,255,255,.04)}
  </style>
  <div class=stage><div class=device><div class=screen>
    <img src="${shotUrl}">
    <div class=island></div>
  </div></div></div>`
}

const browser = await chromium.launch({ channel: 'msedge' })
const ctx = await browser.newContext({ deviceScaleFactor: 3 })
const page = await ctx.newPage()
const conv = await ctx.newPage() // página auxiliar para converter PNG -> WebP

for (const { raw, asset, tilt } of MAP) {
  const shotUrl = `data:image/png;base64,${readFileSync(`${RAW}/${raw}.png`).toString('base64')}`
  await page.setContent(html(shotUrl, tilt))
  await page.waitForTimeout(150)
  const pngBuf = await (await page.$('.stage')).screenshot({ omitBackground: true })

  const webpDataUrl = await conv.evaluate(async (pngB64) => {
    const img = new Image()
    await new Promise((res, rej) => { img.onload = res; img.onerror = rej; img.src = 'data:image/png;base64,' + pngB64 })
    const c = document.createElement('canvas')
    c.width = img.naturalWidth; c.height = img.naturalHeight
    c.getContext('2d').drawImage(img, 0, 0)
    return c.toDataURL('image/webp', 0.92)
  }, pngBuf.toString('base64'))

  const b64 = webpDataUrl.split(',')[1]
  writeFileSync(`${ASSETS}/${asset}.webp`, Buffer.from(b64, 'base64'))
  console.log('composed', asset + '.webp', tilt ? '(tilt)' : '')
}

await browser.close()
console.log('done')
