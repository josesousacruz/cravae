import { chromium } from 'playwright'

const OUT = 'C:/Projetos/Bolão Copa 2026/tools/mockup/raw/_probe.png'

async function launch() {
  for (const channel of ['msedge', 'chrome', undefined]) {
    try {
      const browser = await chromium.launch(channel ? { channel } : {})
      console.log('launched with', channel ?? 'bundled')
      return browser
    } catch (e) {
      console.log('failed channel', channel, '-', String(e.message).split('\n')[0])
    }
  }
  throw new Error('no browser available')
}

const browser = await launch()
const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 3 })
const page = await ctx.newPage()
await page.goto('http://localhost:5173/ranking', { waitUntil: 'networkidle', timeout: 30000 })
await page.addStyleTag({ content: '*,*::before,*::after{animation:none!important;transition:none!important}' })
await page.waitForTimeout(800)
await page.screenshot({ path: OUT, fullPage: false })
console.log('saved', OUT)
await browser.close()
