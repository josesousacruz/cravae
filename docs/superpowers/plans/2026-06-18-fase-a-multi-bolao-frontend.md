# Fase A — Multi-bolão (frontend) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Tornar o multi-bolão visível e usável no frontend: lista de bolões (ativos + encerrados), compra vinculada ao bolão escolhido, página do cupom carregando o torneio do próprio cupom, e seletor de bolão no painel admin.

**Architecture:** Consome a API da Fase A (`GET /api/boloes`, `GET /api/torneios/{id}`, `POST /api/pedidos-checkout` com `torneio_id`, `GET /api/admin/dados?torneio_id=`). O `torneio_id` passa a fluir explicitamente: lista de bolões → `/checkout?torneio=ID` → POST com `torneio_id`; o cupom carrega seu torneio por id; o admin escolhe o bolão num dropdown.

**Tech Stack:** Vue 3 + TypeScript (SFC `<script setup>`), Pinia, Vue Router, Tailwind (tema escuro via classes `text-text`, `bg-bg-card`, `border-border`, `text-primary`, etc.). Sem testes unitários — **gate por task = `npx vue-tsc --noEmit` (zero erros)**; verificação final via preview (Task 6).

**Convenções:** rodar de `frontend/`. Não usar `preview_screenshot` (trava neste projeto) — verificar via `preview_eval`/`preview_snapshot`. Domínio em português.

---

## Estrutura de arquivos

**Criar:**
- `frontend/src/components/BolaoCard.vue` — card de um bolão (nome, valor, status de compra, botão Comprar).
- `frontend/src/views/BoloesView.vue` — lista de bolões com abas Ativos/Encerrados.

**Modificar:**
- `frontend/src/tipos.ts` — `torneio_id` em `Cupom`; novos types `Bolao` e `RespostaBoloes`.
- `frontend/src/router/index.ts` — rota `/boloes`.
- `frontend/src/views/CheckoutView.vue` — envia `torneio_id` no POST; carrega o torneio escolhido (via `?torneio=ID`).
- `frontend/src/views/CupomView.vue` — carrega o torneio do cupom por id; ranking pelo torneio do cupom.
- `frontend/src/views/AdminPainelView.vue` — dropdown de bolão; `carregarDados` por `torneio_id`.
- `frontend/src/components/AppHeader.vue` — link de navegação "Bolões".

---

## Task 1: Types — `torneio_id` no Cupom + type `Bolao`

**Files:**
- Modify: `frontend/src/tipos.ts`

- [ ] **Step 1: Adicionar `torneio_id` ao type `Cupom`**

Localizar o `export type Cupom = { ... }` e adicionar `torneio_id` logo após `id`:

```typescript
export type Cupom = {
  id: number
  torneio_id?: number
  codigo: string
  status: string
  pedido_checkout_id: number | null
  pedido_checkout?: PedidoCheckout | null
  pontuacao?: PontuacaoCupom | null
  eventos_pontuacao?: EventoPontuacao[]
}
```

- [ ] **Step 2: Adicionar os types `Bolao` e `RespostaBoloes`**

No final do arquivo `frontend/src/tipos.ts`, adicionar:

```typescript
export type Bolao = {
  id: number
  nome: string
  edicao: string
  status: string
  valor_cupom: number
  compras_abertas: boolean
  data_inicio: string | null
  data_fim: string | null
}

export type RespostaBoloes = {
  ativos: Bolao[]
  encerrados: Bolao[]
}
```

- [ ] **Step 3: Typecheck**

Run: `cd frontend && npx vue-tsc --noEmit`
Expected: zero erros.

- [ ] **Step 4: Commit**

```bash
git add frontend/src/tipos.ts
git commit -m "feat(front): tipos Bolao e torneio_id no Cupom"
```

---

## Task 2: Lista de bolões — `BolaoCard.vue` + `BoloesView.vue` + rota + nav

**Files:**
- Create: `frontend/src/components/BolaoCard.vue`
- Create: `frontend/src/views/BoloesView.vue`
- Modify: `frontend/src/router/index.ts`
- Modify: `frontend/src/components/AppHeader.vue`

- [ ] **Step 1: Criar `frontend/src/components/BolaoCard.vue`**

```vue
<template>
  <div class="flex flex-col rounded-2xl border border-border bg-bg-card p-5">
    <div class="flex items-start justify-between gap-3">
      <div class="min-w-0">
        <h3 class="truncate text-base font-bold text-text">{{ bolao.nome }}</h3>
        <p class="text-xs text-text-muted">{{ bolao.edicao }}</p>
      </div>
      <span
        v-if="bolao.status === 'encerrado'"
        class="shrink-0 rounded-full bg-text-muted/15 px-2 py-0.5 text-[10px] font-medium text-text-muted"
      >
        Encerrado
      </span>
      <span
        v-else-if="bolao.compras_abertas"
        class="shrink-0 rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-medium text-primary"
      >
        Compra aberta
      </span>
      <span
        v-else
        class="shrink-0 rounded-full bg-warning/10 px-2 py-0.5 text-[10px] font-medium text-warning"
      >
        Compra fechada
      </span>
    </div>

    <div class="mt-4 flex items-center justify-between gap-3">
      <span class="text-sm text-text-secondary">
        Cupom <span class="font-bold text-text">{{ valorFormatado }}</span>
      </span>
      <RouterLink
        v-if="bolao.status === 'publicado' && bolao.compras_abertas"
        :to="{ name: 'checkout', query: { torneio: bolao.id } }"
        class="inline-flex items-center rounded-lg bg-primary px-3 py-1.5 text-xs font-bold text-bg transition hover:opacity-90"
      >
        Comprar cupom
      </RouterLink>
      <span v-else class="text-xs text-text-muted">Indisponível</span>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import type { Bolao } from '../tipos'

const props = defineProps<{ bolao: Bolao }>()

const valorFormatado = computed(() => {
  const numero = Number(props.bolao.valor_cupom)
  return Number.isFinite(numero)
    ? new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(numero)
    : 'R$ 0,00'
})
</script>
```

> Nota: a classe `text-bg` é a cor de texto sobre o primary (botões). Se o projeto não tiver `text-bg`, usar `text-white`. Verifique em `frontend/src/style.css` (bloco `@theme`) qual token existe para texto sobre o primary e use o equivalente já usado em outros botões primários do projeto (procure por `bg-primary` em componentes existentes e copie a cor de texto que eles usam).

- [ ] **Step 2: Criar `frontend/src/views/BoloesView.vue`**

```vue
<template>
  <div class="mx-auto max-w-4xl px-4 py-8">
    <header class="mb-6">
      <h1 class="text-2xl font-black text-text">Bolões</h1>
      <p class="mt-1 text-sm text-text-muted">Escolha um bolão para participar.</p>
    </header>

    <div class="mb-5 flex gap-2">
      <button
        type="button"
        class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
        :class="aba === 'ativos' ? 'bg-primary/20 text-primary' : 'bg-bg-input text-text-muted hover:text-text-secondary'"
        @click="aba = 'ativos'"
      >
        Ativos ({{ boloes.ativos.length }})
      </button>
      <button
        type="button"
        class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
        :class="aba === 'encerrados' ? 'bg-primary/20 text-primary' : 'bg-bg-input text-text-muted hover:text-text-secondary'"
        @click="aba = 'encerrados'"
      >
        Encerrados ({{ boloes.encerrados.length }})
      </button>
    </div>

    <div v-if="carregando" class="py-12 text-center text-text-muted">Carregando bolões...</div>

    <template v-else>
      <div v-if="listaAtual.length" class="grid gap-4 sm:grid-cols-2">
        <BolaoCard v-for="b in listaAtual" :key="b.id" :bolao="b" />
      </div>
      <div v-else class="rounded-2xl border border-border bg-bg-card py-12 text-center text-text-muted">
        Nenhum bolão {{ aba === 'ativos' ? 'ativo' : 'encerrado' }} no momento.
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { requisicaoApi } from '../services/api'
import { useToast } from '../composables/useToast'
import type { RespostaBoloes } from '../tipos'
import BolaoCard from '../components/BolaoCard.vue'

const { mostrar } = useToast()

const boloes = ref<RespostaBoloes>({ ativos: [], encerrados: [] })
const carregando = ref(true)
const aba = ref<'ativos' | 'encerrados'>('ativos')

const listaAtual = computed(() => (aba.value === 'ativos' ? boloes.value.ativos : boloes.value.encerrados))

onMounted(async () => {
  try {
    boloes.value = await requisicaoApi<RespostaBoloes>('/boloes')
  } catch {
    mostrar('erro', 'Falha ao carregar bolões.')
  } finally {
    carregando.value = false
  }
})
</script>
```

> Verifique a assinatura de `useToast`/`mostrar` em `frontend/src/composables/useToast.ts` (já usada em CupomView como `const { mostrar } = useToast()` e `mostrar('erro', '...')`). Se a assinatura diferir, ajustar a chamada para a forma existente.

- [ ] **Step 3: Registrar a rota em `frontend/src/router/index.ts`**

Adicionar o import no topo (junto aos outros imports de views):

```typescript
import BoloesView from '../views/BoloesView.vue'
```

E adicionar a rota dentro do array `routes` (após a rota `/`):

```typescript
    {
      path: '/boloes',
      name: 'boloes',
      component: BoloesView,
    },
```

- [ ] **Step 4: Adicionar link de navegação em `frontend/src/components/AppHeader.vue`**

Abrir `frontend/src/components/AppHeader.vue`, localizar os `RouterLink` de navegação existentes (ex.: o link para Ranking ou Início) e adicionar, seguindo exatamente o mesmo padrão de classes/estrutura dos links vizinhos, um link para os bolões:

```vue
<RouterLink :to="{ name: 'boloes' }" class="<MESMAS_CLASSES_DOS_OUTROS_LINKS>">Bolões</RouterLink>
```

Substituir `<MESMAS_CLASSES_DOS_OUTROS_LINKS>` pelas classes idênticas às do link de navegação vizinho (copiar do RouterLink de "Ranking" ou equivalente no mesmo header). Se houver versão mobile (menu) e desktop, adicionar nos dois locais, espelhando os links existentes.

- [ ] **Step 5: Typecheck**

Run: `cd frontend && npx vue-tsc --noEmit`
Expected: zero erros.

- [ ] **Step 6: Commit**

```bash
git add frontend/src/components/BolaoCard.vue frontend/src/views/BoloesView.vue frontend/src/router/index.ts frontend/src/components/AppHeader.vue
git commit -m "feat(front): lista de boloes (ativos/encerrados) com card e rota /boloes"
```

---

## Task 3: Checkout envia `torneio_id`

**Files:**
- Modify: `frontend/src/views/CheckoutView.vue`

- [ ] **Step 1: Computed do torneio selecionado (via `?torneio=ID`)**

Em `frontend/src/views/CheckoutView.vue`, localizar o computed `cupomId` (lê `route.query.cupom`):

```typescript
const cupomId = computed(() => {
  const id = Number(route.query.cupom)
  return Number.isFinite(id) && id > 0 ? id : null
})
```

Logo abaixo dele, adicionar o computed do torneio:

```typescript
const torneioIdSelecionado = computed(() => {
  const id = Number(route.query.torneio)
  return Number.isFinite(id) && id > 0 ? id : null
})
```

- [ ] **Step 2: Carregar o torneio escolhido (fallback ao último publicado)**

Localizar onde o torneio é carregado para exibir o valor:

```typescript
const resposta = await requisicaoApi<{ torneio: Torneio }>('/torneio')
torneio.value = resposta.torneio
```

Substituir por (carrega o torneio do `?torneio=ID` quando houver; senão, o último publicado):

```typescript
const caminhoTorneio = torneioIdSelecionado.value
  ? `/torneios/${torneioIdSelecionado.value}`
  : '/torneio'
const resposta = await requisicaoApi<{ torneio: Torneio }>(caminhoTorneio)
torneio.value = resposta.torneio
```

- [ ] **Step 3: Enviar `torneio_id` no POST**

Localizar o POST do pedido:

```typescript
const resposta = await requisicaoApi<RespostaPedidoCheckout>('/pedidos-checkout', {
  metodo: 'POST',
  corpo: cupomId.value ? { cupom_id: cupomId.value } : {},
})
```

Substituir por (sempre inclui `torneio_id`, derivado do torneio carregado):

```typescript
const torneioId = torneio.value?.id
if (!torneioId) {
  mostrar('erro', 'Bolão não encontrado para o checkout.')
  return
}
const corpo: Record<string, number> = { torneio_id: torneioId }
if (cupomId.value) {
  corpo.cupom_id = cupomId.value
}
const resposta = await requisicaoApi<RespostaPedidoCheckout>('/pedidos-checkout', {
  metodo: 'POST',
  corpo,
})
```

> Verifique que `mostrar` (toast) está disponível no `<script setup>` do CheckoutView; se o nome do toast/erro diferir, use o padrão já presente no arquivo para mensagens de erro. Verifique também que `torneio` é um `ref<Torneio | null>` já existente neste componente (é — usado em `valorCupomFormatado`).

- [ ] **Step 4: Typecheck**

Run: `cd frontend && npx vue-tsc --noEmit`
Expected: zero erros.

- [ ] **Step 5: Commit**

```bash
git add frontend/src/views/CheckoutView.vue
git commit -m "feat(front): checkout envia torneio_id do bolao escolhido"
```

---

## Task 4: CupomView carrega o torneio do próprio cupom

**Files:**
- Modify: `frontend/src/views/CupomView.vue`

- [ ] **Step 1: Carregar o cupom primeiro, depois o torneio por id**

Em `frontend/src/views/CupomView.vue`, localizar a função `carregarDados` (o `Promise.all` com `requisicaoApi<{ torneio: Torneio }>('/torneio')`). Substituir o bloco interno do `try` por:

```typescript
    const rC = await requisicaoApi<{ cupom: Cupom }>(`/cupons/${rota.params.id}`)
    cupom.value = rC.cupom

    const caminhoTorneio = rC.cupom.torneio_id ? `/torneios/${rC.cupom.torneio_id}` : '/torneio'
    const [rT, rA, rB] = await Promise.all([
      requisicaoApi<{ torneio: Torneio }>(caminhoTorneio),
      requisicaoApi<{ apostas: Aposta[] }>(`/cupons/${rota.params.id}/apostas`),
      requisicaoApi<{ bracket: BracketJogoCupom[]; resumo: ResumoBracketCupom }>(`/cupons/${rota.params.id}/bracket`),
    ])
    torneio.value = rT.torneio
    apostas.value = rA.apostas
    bracketCupom.value = rB.bracket
    resumoBracketIds.value = rB.resumo
    preencherFormulario('substituir')
```

(O `catch`/`finally` da função permanecem iguais.)

- [ ] **Step 2: Ranking pelo torneio do cupom (não pelo store do "último")**

Localizar `carregarRanking`:

```typescript
async function carregarRanking() {
  if (!torneioStore.torneio) return
  carregandoRanking.value = true
  try {
    const r = await requisicaoApi<{ ranking: RankingItem[] }>(`/torneios/${torneioStore.torneio.id}/ranking`)
    ranking.value = r.ranking
  } catch {} finally { carregandoRanking.value = false }
}
```

Substituir por (usa o `torneio.value` local, que agora é o torneio do cupom):

```typescript
async function carregarRanking() {
  if (!torneio.value) return
  carregandoRanking.value = true
  try {
    const r = await requisicaoApi<{ ranking: RankingItem[] }>(`/torneios/${torneio.value.id}/ranking`)
    ranking.value = r.ranking
  } catch {} finally { carregandoRanking.value = false }
}
```

- [ ] **Step 3: Ajustar o `onMounted`**

Localizar:

```typescript
onMounted(async () => {
  await Promise.all([carregarDados(), torneioStore.carregar()])
  carregarRanking()
})
```

Substituir por (o ranking depende do torneio carregado em `carregarDados`):

```typescript
onMounted(async () => {
  await carregarDados()
  carregarRanking()
})
```

> Após isso, `torneioStore` pode ficar sem uso neste arquivo. Se o `import` de `usarTorneioStore` e a linha `const torneioStore = usarTorneioStore()` ficarem **sem nenhuma outra referência**, removê-los para o `vue-tsc` não acusar variável não usada. Conferir com busca por `torneioStore` no arquivo antes de remover.

- [ ] **Step 4: Typecheck**

Run: `cd frontend && npx vue-tsc --noEmit`
Expected: zero erros. (Se acusar `torneioStore`/`usarTorneioStore` não usado, remover conforme o aviso do Step 3.)

- [ ] **Step 5: Commit**

```bash
git add frontend/src/views/CupomView.vue
git commit -m "feat(front): CupomView carrega o torneio do cupom e ranking do bolao"
```

---

## Task 5: Seletor de bolão no painel admin

**Files:**
- Modify: `frontend/src/views/AdminPainelView.vue`

- [ ] **Step 1: Carregar a lista de bolões e o id selecionado**

Em `frontend/src/views/AdminPainelView.vue`, no `<script setup>`, adicionar (perto das outras `ref`/imports). Garantir que `RespostaBoloes` e `Bolao` sejam importados de `../tipos` (somar aos imports de tipos já existentes):

```typescript
const boloes = ref<Bolao[]>([])
const torneioSelecionadoId = ref<number | null>(null)
```

- [ ] **Step 2: `carregarDados` aceita `torneio_id`**

Localizar:

```typescript
async function carregarDados() {
  const resposta = await requisicaoApi<{ torneio: Torneio; chaves_disponiveis: { chave: string; label: string }[] }>('/admin/dados')
  torneio.value = resposta.torneio
  chavesDisponiveis.value = resposta.chaves_disponiveis ?? []
  preencherFormulario()
}
```

Substituir por:

```typescript
async function carregarDados(torneioId?: number) {
  const caminho = torneioId ? `/admin/dados?torneio_id=${torneioId}` : '/admin/dados'
  const resposta = await requisicaoApi<{ torneio: Torneio; chaves_disponiveis: { chave: string; label: string }[] }>(caminho)
  torneio.value = resposta.torneio
  torneioSelecionadoId.value = resposta.torneio.id
  chavesDisponiveis.value = resposta.chaves_disponiveis ?? []
  preencherFormulario()
}

async function carregarBoloes() {
  try {
    const r = await requisicaoApi<{ ativos: Bolao[]; encerrados: Bolao[] }>('/boloes')
    boloes.value = [...r.ativos, ...r.encerrados]
  } catch {
    boloes.value = []
  }
}

async function trocarBolao(torneioId: number) {
  await carregarDados(torneioId)
}
```

- [ ] **Step 3: Chamar `carregarBoloes` na inicialização**

Localizar onde `carregarDados()` é chamado na inicialização (provável `onMounted`) e acrescentar `carregarBoloes()`. Exemplo do padrão a aplicar (adaptar ao `onMounted` existente):

```typescript
onMounted(async () => {
  await Promise.all([carregarDados(), carregarBoloes()])
})
```

Se já existir um `onMounted` chamando `carregarDados()`, apenas adicione a chamada `carregarBoloes()` em paralelo.

- [ ] **Step 4: Dropdown no header do admin**

Localizar o header do admin (o `<h1>` com `{{ torneio.nome }} {{ torneio.edicao }}`). Logo após esse bloco de título, inserir o seletor (renderiza só quando há mais de um bolão):

```vue
<div v-if="boloes.length > 1" class="mt-3">
  <label class="mb-1 block text-xs font-medium text-text-muted">Bolão</label>
  <select
    class="rounded-lg border border-border bg-bg-input px-3 py-2 text-sm text-text"
    :value="torneioSelecionadoId ?? ''"
    @change="trocarBolao(Number(($event.target as HTMLSelectElement).value))"
  >
    <option v-for="b in boloes" :key="b.id" :value="b.id">
      {{ b.nome }} {{ b.edicao }}{{ b.status === 'encerrado' ? ' (encerrado)' : '' }}
    </option>
  </select>
</div>
```

> O `select` é controlado por `:value` + `@change` (não `v-model`) para disparar o recarregamento. Confirme que `torneio` (o `ref` usado no `<h1>`) já existe no componente — existe, é usado em todo o painel.

- [ ] **Step 5: Typecheck**

Run: `cd frontend && npx vue-tsc --noEmit`
Expected: zero erros.

- [ ] **Step 6: Commit**

```bash
git add frontend/src/views/AdminPainelView.vue
git commit -m "feat(front): seletor de bolao no painel admin"
```

---

## Task 6: Verificação via preview

**Files:** (nenhum)

- [ ] **Step 1: Typecheck geral**

Run: `cd frontend && npx vue-tsc --noEmit`
Expected: zero erros.

- [ ] **Step 2: Garantir backend + frontend rodando**

Backend na porta 8888 e frontend na 5173 (dev). Usar o servidor de preview já existente (`preview_list`). Se necessário, `migrate:fresh --seed` cria o torneio publicado "Inter World Cup".

- [ ] **Step 3: Verificar a lista de bolões**

Navegar para `http://localhost:5173/boloes` (via `preview_eval` `window.location.href=...`). Com `preview_eval`, confirmar:
- Existe pelo menos 1 card em "Ativos" com o nome do bolão e o valor formatado (`R$`).
- O botão "Comprar cupom" aparece quando `compras_abertas` e leva para `/checkout?torneio=<id>`.

Exemplo de checagem (preview_eval):
```js
(() => {
  const cards = document.querySelectorAll('a[href*="/checkout?torneio="]');
  return JSON.stringify({ qtdBotoesComprar: cards.length, primeiroHref: cards[0]?.getAttribute('href') });
})()
```

- [ ] **Step 4: Verificar o checkout com torneio_id**

Logar como usuário demo (`larissa.demo@interworldcup.local` / `demo12345`) via API e navegar para `/checkout?torneio=1`. Confirmar (preview_eval) que a tela carrega o valor do torneio e que, ao gerar o pedido, o POST inclui `torneio_id`. Alternativamente, inspecionar `preview_network` para confirmar o corpo do POST `/pedidos-checkout` contém `torneio_id`.

- [ ] **Step 5: Verificar CupomView carregando o torneio do cupom**

Navegar para `/cupons/1` (cupom da Larissa) e confirmar que os jogos/abas carregam normalmente (sem erro de console via `preview_console_logs` level error) e que o ranking aparece.

- [ ] **Step 6: Verificar console limpo**

`preview_console_logs` (level error) nas telas `/boloes`, `/checkout?torneio=1`, `/cupons/1`: sem erros.

- [ ] **Step 7: Commit (se houve ajustes)**

```bash
git add frontend/src
git commit -m "fix(front): ajustes de verificacao do multi-bolao"
```

---

## Self-review (cobertura)

- **Lista de bolões ativos + aba Encerrados** → Task 2 (`BoloesView` + `BolaoCard`). ✓
- **Compra vinculada ao bolão** → Task 2 (botão → `/checkout?torneio=ID`) + Task 3 (POST com `torneio_id`). ✓
- **CupomView carrega o torneio do cupom** → Task 4. ✓
- **Seletor de bolão no admin** → Task 5. ✓
- **`torneio_id` no type Cupom + type Bolao** → Task 1. ✓

**Fora desta fase (follow-ups):**
- `RankingView` standalone honrar `?torneio=ID` (hoje mostra o último publicado) — pequeno follow-up.
- Repontar os botões "Comprar cupom" de `PainelView`/`CupomCard` para `/boloes` (escolher bolão) — hoje caem em `/checkout` com fallback ao último publicado, que continua funcionando.
- Fase B (mata-mata pela realidade) e Fase C (seeder + NOT NULL em produção).
