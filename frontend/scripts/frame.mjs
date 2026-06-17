// Testa o compositor de moldura iPhone: transparência (omitBackground) e tilt 3D.
import { chromium } from 'playwright'
import { readFileSync } from 'node:fs'

const RAW = 'C:/Projetos/Bolão Copa 2026/tools/mockup/raw/_probe.png'
const OUT_DIR = 'C:/Projetos/Bolão Copa 2026/tools/mockup/raw'

const shotB64 = readFileSync(RAW).toString('base64')
const shotUrl = `data:image/png;base64,${shotB64}`

function html(tilt) {
  // moldura titânio esmeralda; tela = screenshot. Sem sombra (a LP aplica drop-shadow).
  return `<!doctype html><meta charset=utf8>
  <style>
    html,body{margin:0;background:transparent}
    .stage{padding:80px;display:inline-block}
    .device{
      width:360px; aspect-ratio:390/844; border-radius:62px; padding:12px;
      background:linear-gradient(150deg,#1f5a49,#0c1a16 38%,#0a1411 62%,#1c5343);
      box-shadow:inset 0 0 0 1.5px rgba(120,220,180,.35), inset 0 2px 6px rgba(255,255,255,.18), inset 0 -3px 8px rgba(0,0,0,.5);
      position:relative;
      ${tilt ? 'transform:perspective(2200px) rotateY(-23deg) rotateX(5deg) rotate(-2deg);' : ''}
    }
    .screen{position:relative;width:100%;height:100%;border-radius:50px;overflow:hidden;background:#070909;box-shadow:inset 0 0 0 2px #000}
    .screen img{width:100%;height:100%;object-fit:cover;display:block}
    .island{position:absolute;top:14px;left:50%;transform:translateX(-50%);width:34%;height:22px;background:#000;border-radius:14px;z-index:2}
  </style>
  <div class=stage><div class=device><div class=screen>
    <div class=island></div>
    <img src="${shotUrl}">
  </div></div></div>`
}

const browser = await chromium.launch({ channel: 'msedge' })
const ctx = await browser.newContext({ deviceScaleFactor: 3 })
const page = await ctx.newPage()

for (const [name, tilt] of [['_frame_upright', false], ['_frame_tilt', true]]) {
  await page.setContent(html(tilt))
  const el = await page.$('.stage')
  await el.screenshot({ path: `${OUT_DIR}/${name}.png`, omitBackground: true })
  console.log('saved', name)
}
await browser.close()
