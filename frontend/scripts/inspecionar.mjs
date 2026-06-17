import { chromium } from 'playwright'
import { readFileSync } from 'node:fs'

const ASSETS = 'C:/Projetos/Bolão Copa 2026/frontend/src/assets'
const OUT = 'C:/Projetos/Bolão Copa 2026/tools/mockup/raw/_contact.png'
const names = ['Hero-center', 'hero-left', 'Hero-right', 'step-01', 'step-02', 'step-03', 'step-04', 'step-05', 'feature-phone-tilted']

const imgs = names.map(n => {
  const b64 = readFileSync(`${ASSETS}/${n}.webp`).toString('base64')
  return `<figure><img src="data:image/webp;base64,${b64}"><figcaption>${n}</figcaption></figure>`
}).join('')

const html = `<!doctype html><meta charset=utf8>
<style>
 body{margin:0;background:#070909;font-family:system-ui;display:grid;grid-template-columns:repeat(5,1fr);gap:6px;padding:14px}
 figure{margin:0;text-align:center}
 img{width:100%;height:auto;display:block}
 figcaption{color:#34d399;font-size:11px;margin-top:2px}
</style>${imgs}`

const browser = await chromium.launch({ channel: 'msedge' })
const page = await browser.newPage({ viewport: { width: 1500, height: 900 } })
await page.setContent(html)
await page.waitForTimeout(300)
await page.screenshot({ path: OUT, fullPage: true })
await browser.close()
console.log('saved', OUT)
