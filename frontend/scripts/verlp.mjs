// Captura a LP por seções (legível) para verificação visual. Edge via Playwright.
import { chromium } from 'playwright'
const APP = 'http://localhost:5173/'
const OUT = 'C:/Projetos/Bolão Copa 2026/tools/mockup/raw'
const KILL = '*,*::before,*::after{animation:none!important;transition:none!important;scroll-behavior:auto!important}'

const browser = await chromium.launch({ channel: 'msedge' })

// Desktop por seções
{
  const page = await browser.newPage({ viewport: { width: 1366, height: 860 } })
  await page.goto(APP, { waitUntil: 'networkidle' })
  await page.addStyleTag({ content: KILL })
  await page.waitForTimeout(800)
  const sections = [['_lp_hero', null], ['_lp_steps', '#como-funciona'], ['_lp_vantagens', '#vantagens'], ['_lp_pontuacao', '#pontuacao'], ['_lp_faq', '#faq']]
  for (const [name, sel] of sections) {
    if (sel) await page.evaluate(s => document.querySelector(s)?.scrollIntoView(), sel)
    else await page.evaluate(() => scrollTo(0, 0))
    await page.waitForTimeout(400)
    await page.screenshot({ path: `${OUT}/${name}.png` })
    console.log('saved', name)
  }
  // footer
  await page.evaluate(() => scrollTo(0, document.body.scrollHeight))
  await page.waitForTimeout(400)
  await page.screenshot({ path: `${OUT}/_lp_footer.png` })
  console.log('saved _lp_footer')
  await page.close()
}

// Mobile hero
{
  const page = await browser.newPage({ viewport: { width: 390, height: 844 } })
  await page.goto(APP, { waitUntil: 'networkidle' })
  await page.addStyleTag({ content: KILL })
  await page.waitForTimeout(800)
  await page.screenshot({ path: `${OUT}/_lp_mobile.png` })
  console.log('saved _lp_mobile')
  await page.close()
}

await browser.close()
console.log('done')
