# LP Autoral + Regeneração de Assets — Plano de Implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deixar a landing page do Inter World Cup com identidade autoral, regenerando os 9 mockups de celular a partir de screenshots reais do app em molduras iPhone titânio esmeralda, trocando o troféu genérico pela taça da Copa do chaveamento e reescrevendo todo o conteúdo textual.

**Architecture:** Spike de de-risco define a técnica de composição → seed de demo local popula as telas → captura via ferramentas de preview em DPR alto → compositor canvas/DOM envolve cada screenshot numa moldura iPhone (transparente; tilt 3D só na feature-phone) → assets substituídos em `frontend/src/assets/` → restyle + reescrita de texto em `InicioView.vue`.

**Tech Stack:** Vue 3 + TypeScript + Vite, Tailwind, Laravel 13 + MySQL (seed), ferramentas de preview (preview_start/screenshot/resize), HTML Canvas para composição.

---

## Arquivos afetados

- Criar (temporário, captura): `backend/database/seeders/DemonstracaoSeeder.php`
- Criar (ferramenta, pode ser removida ao final): `tools/mockup/compositor.html`
- Substituir: `frontend/src/assets/{Hero-center,hero-left,Hero-right,step-01..05,feature-phone-tilted}.webp`
- Modificar: `frontend/src/views/InicioView.vue` (imports do troféu, restyle, texto)
- Manter intacto: rotas, API, lógica do app

---

## Task 1: Spike de de-risco (ambiente + técnica de composição)

**Objetivo:** Provar que (a) backend + frontend rodam localmente com dados, (b) consigo capturar um screenshot de tela mobile, (c) consigo compor esse screenshot numa moldura iPhone com **fundo transparente** e, separadamente, com **inclinação 3D**. Esta task trava a técnica usada nas Tasks 3–4.

**Files:**
- Create: `tools/mockup/compositor.html`

- [ ] **Step 1: Verificar runtime do backend**

Run: `cd backend && php --version && php artisan --version`
Expected: versões impressas sem erro. Se PHP/MySQL não estiverem disponíveis, PARAR e reportar — o approach de screenshot depende disso.

- [ ] **Step 2: Subir o banco seedado**

Run: `cd backend && php artisan migrate:fresh --seed`
Expected: migrations + seeders rodam; torneio "Inter World Cup" populado.

- [ ] **Step 3: Subir backend e frontend**

Run backend (background): `cd backend && php artisan serve --port=8888`
Iniciar preview do frontend com `preview_start` (Vite na 5173).
Expected: LP carrega no preview.

- [ ] **Step 4: Capturar 1 screenshot mobile de teste**

`preview_resize` para 390×844; navegar para uma tela com conteúdo; `preview_screenshot`.
Expected: PNG nítido da tela.

- [ ] **Step 5: Criar o compositor canvas e validar transparência**

Criar `tools/mockup/compositor.html` com um canvas que: desenha uma moldura iPhone (retângulo arredondado, borda esmeralda titânio, notch/ilha), recorta o screenshot na área de tela e exporta via `canvas.toDataURL('image/png')`.

```html
<!doctype html>
<meta charset="utf-8">
<body style="margin:0;background:#222">
<canvas id="c" width="900" height="1840"></canvas>
<script>
// frame: titânio esmeralda. screen area inset. Exporta PNG transparente.
function drawFrame(ctx, w, h){
  ctx.clearRect(0,0,w,h);              // fundo transparente
  const r = 120;                        // raio das bordas
  // corpo do aparelho (borda)
  roundRect(ctx, 0, 0, w, h, r); 
  const grd = ctx.createLinearGradient(0,0,w,0);
  grd.addColorStop(0,'#0c3b2f'); grd.addColorStop(.5,'#10241d'); grd.addColorStop(1,'#0c3b2f');
  ctx.fillStyle = grd; ctx.fill();
}
function roundRect(ctx,x,y,w,h,r){ctx.beginPath();ctx.moveTo(x+r,y);ctx.arcTo(x+w,y,x+w,y+h,r);ctx.arcTo(x+w,y+h,x,y+h,r);ctx.arcTo(x,y+h,x,y,r);ctx.arcTo(x,y,x+w,y,r);ctx.closePath();}
function compose(imgUrl){
  const c=document.getElementById('c'), ctx=c.getContext('2d');
  const img=new Image();
  img.onload=()=>{
    drawFrame(ctx,c.width,c.height);
    const bezel=28, top=bezel, sx=bezel, sw=c.width-bezel*2, sh=c.height-bezel*2;
    ctx.save(); roundRect(ctx,sx,top,sw,sh,r=96); ctx.clip();
    ctx.drawImage(img, sx, top, sw, sh); ctx.restore();
    window.__png = c.toDataURL('image/png');   // transparente fora da moldura
  };
  img.src=imgUrl;
}
</script>
```

Expected: PNG exportado tem pixels transparentes fora da moldura (validar abrindo o dataURL).

- [ ] **Step 6: Validar a técnica de inclinação 3D (feature-phone)**

Testar abordagem primária: um `<div>` com a moldura+screenshot e `transform: perspective(1600px) rotateY(-22deg) rotateX(3deg)` numa página de fundo transparente, capturado via `preview_screenshot`. Se a captura não preservar transparência, registrar o fallback escolhido (mapeamento de perspectiva no canvas via 4 cantos, ou fundo `#070909` embutido só nesse asset).

Expected: técnica de tilt definida e anotada no topo do compositor.

- [ ] **Step 7: Commit**

```bash
git add tools/mockup/compositor.html
git commit -m "chore: compositor de mockups e spike de captura (de-risco)"
```

---

## Task 2: Seed de demonstração local

**Objetivo:** Popular telas (cupom ativo, palpites preenchidos, ranking com vários participantes) para captura.

**Files:**
- Create: `backend/database/seeders/DemonstracaoSeeder.php`

- [ ] **Step 1: Inspecionar models para campos exatos**

Run: ler `app/Models/{Usuario,Cupom,Palpite,Pedido}.php` (nomes reais) e o `CheckoutService`/`ApostasService` para reproduzir a criação de cupom+palpite de forma consistente com as regras do app.
Expected: nomes de tabelas/campos confirmados.

- [ ] **Step 2: Escrever o `DemonstracaoSeeder`**

Criar seeder que gera: 1 usuário de demo (`demo@interworldcup.local`), 1 cupom **ativo** desse usuário com ~8 palpites preenchidos na rodada 1 (ex.: BRA 2x1 MAR, ARG 3x0 ALG), e ~6 cupons de outros participantes com pontuação distribuída para o ranking. Reutilizar os services do app quando existirem (não duplicar regra de negócio).

```php
<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
// usar os models/services reais confirmados no Step 1
class DemonstracaoSeeder extends Seeder { public function run(): void { /* cria usuario+cupom+palpites+participantes */ } }
```

- [ ] **Step 3: Rodar e verificar**

Run: `cd backend && php artisan migrate:fresh --seed && php artisan db:seed --class=Database\\Seeders\\DemonstracaoSeeder`
Expected: sem erro; login do usuário de demo possível; ranking populado via API `/torneio`/ranking.

- [ ] **Step 4: Commit**

```bash
git add backend/database/seeders/DemonstracaoSeeder.php
git commit -m "chore: seeder de demonstracao para captura de mockups (local)"
```

---

## Task 3: Capturar as telas reais

**Objetivo:** Gerar os 9 screenshots-fonte em DPR alto.

- [ ] **Step 1: Ambiente em pé**

Backend (8888) + preview do frontend rodando com banco seedado + demo.

- [ ] **Step 2: Login como demo e capturar cada tela**

Para cada item do mapeamento (abaixo): navegar (`preview_eval`/`preview_click`), `preview_resize` 390×844, `preview_screenshot`. Salvar como `tools/mockup/raw/<nome>.png`.

Mapeamento:
- `Hero-center` → Palpites/Fase de Grupos
- `hero-left` → Chaveamento
- `Hero-right` → Ranking
- `step-01` → Login/cadastro
- `step-02` → Checkout/compra de cupom
- `step-03` → Palpites fase de grupos
- `step-04` → Meus Resultados/pontuação
- `step-05` → Ranking
- `feature-phone-tilted` → Detalhe de partida (Brasil x Argentina ou similar)

Expected: 9 PNGs nítidos, sem "Bolão AI", com conteúdo InterWorldCup.

- [ ] **Step 3: Conferência de qualidade**

Revisar cada PNG: dados populados, bandeiras/siglas legíveis, nenhuma área vazia constrangedora. Re-capturar o que precisar.

- [ ] **Step 4: Commit dos raws**

```bash
git add tools/mockup/raw
git commit -m "chore: screenshots-fonte das telas reais para mockups"
```

---

## Task 4: Compor os 9 assets emoldurados

**Objetivo:** Produzir os `.webp` finais com a moldura iPhone esmeralda.

**Files:**
- Substituir: `frontend/src/assets/{Hero-center,hero-left,Hero-right,step-01..05,feature-phone-tilted}.webp`

- [ ] **Step 1: Compor as 8 molduras retas**

Para cada screenshot upright, rodar pelo compositor (Task 1) → exportar PNG transparente. Converter para `.webp` (via canvas `toDataURL('image/webp', .92)` ou ferramenta de imagem).

- [ ] **Step 2: Compor a feature-phone com tilt 3D**

Aplicar a técnica de tilt travada na Task 1, Step 6. Exportar.

- [ ] **Step 3: Substituir os arquivos em `src/assets/`**

Manter os mesmos nomes de arquivo para não mexer nos imports. Se a extensão mudar (ex.: `.png`), atualizar os imports correspondentes em `InicioView.vue`.

- [ ] **Step 4: Verificar na LP**

Recarregar o preview da LP. Conferir hero (3 phones), grade "Como funciona" (5 phones) e seção vantagens (feature-phone tilt). Sem distorção, sem fundo indesejado, nitidez ok.

- [ ] **Step 5: Commit**

```bash
git add frontend/src/assets
git commit -m "feat: mockups de celular regenerados com telas reais do InterWorldCup"
```

---

## Task 5: Trocar o troféu decorativo

**Files:**
- Modify: `frontend/src/views/InicioView.vue`

- [ ] **Step 1: Trocar o import e os usos**

Em `InicioView.vue`: substituir `import trophyAsset from '../assets/trophy.webp'` por `import trophyAsset from '../assets/taca-copa-transparente.png'`. As 3 `<img :src="trophyAsset">` (hero linha ~52, vantagens ~157, footer ~351) passam a usar a taça da Copa. Ajustar `w-*`/`opacity-*` para a proporção vertical da taça ficar elegante.

```ts
import trophyAsset from '../assets/taca-copa-transparente.png'
```

- [ ] **Step 2: Verificar**

Recarregar preview; conferir a taça da Copa nos 3 pontos com brilho/opacidade coerentes.

- [ ] **Step 3: Commit**

```bash
git add frontend/src/views/InicioView.vue
git commit -m "feat: taca da Copa (chaveamento) substitui trofeu generico na LP"
```

---

## Task 6: Restyle autoral + reescrita do texto

**Files:**
- Modify: `frontend/src/views/InicioView.vue`

- [ ] **Step 1: Reescrever todo o conteúdo textual**

Revisar headline do hero, subtítulo, prova social, os 5 `passos`, `beneficios`, `perfisUso`, `faq`, textos das seções e footer. Foco no que o sistema é: cupons independentes, palpites por fase, chaveamento visual, pontuação configurável/auditável, ranking por cupom, painel admin. Remover qualquer linguagem genérica de referência.

- [ ] **Step 2: Trabalhar identidade visual própria (sem reestruturar)**

Refinar tratamento de marca IW (logo/badge/tipografia/detalhes esmeralda), espaçamentos e acentos visuais que diferenciem da referência. Manter a ordem das seções.

- [ ] **Step 3: Verificar desktop + mobile**

`preview_resize` desktop e mobile; `preview_snapshot` para conferir conteúdo; `preview_screenshot` para prova visual. Conferir ausência total de "Bolão AI".

- [ ] **Step 4: Commit**

```bash
git add frontend/src/views/InicioView.vue
git commit -m "feat: restyle autoral da LP e reescrita de conteudo InterWorldCup"
```

---

## Task 7: Verificação final

- [ ] **Step 1: Build**

Run: `cd frontend && npm run build`
Expected: build sem erros.

- [ ] **Step 2: Revisão visual final**

Preview desktop + mobile da LP inteira. Checklist: 9 mockups InterWorldCup, taça da Copa decorativa, texto autoral, zero vestígio da referência.

- [ ] **Step 3: Limpeza opcional**

Decidir com o usuário se remove `tools/mockup/` e o `DemonstracaoSeeder` (artefatos de produção) ou mantém versionados. `trophy.webp` antigo pode ser removido se não referenciado.

- [ ] **Step 4: Commit final**

```bash
git add -A
git commit -m "chore: verificacao final da LP autoral"
```

---

## Self-Review

- **Cobertura do spec:** mockups (Tasks 1,3,4) ✓; troféu (Task 5) ✓; restyle (Task 6) ✓; reescrita de texto (Task 6) ✓; seed local (Task 2) ✓; verificação/build (Task 7) ✓.
- **De-risco:** transparência + tilt 3D travados na Task 1 antes de produção em massa.
- **Dependência crítica:** PHP/MySQL no Step 1 da Task 1 — gate explícito; se faltar, reportar antes de prosseguir.
- **Imports:** nomes de arquivo preservados na Task 4 para não quebrar imports, com ressalva caso a extensão mude.
