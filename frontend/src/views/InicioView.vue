<template>
  <div>
    <!-- ═══════════════════════════════════════════════════════════
         HERO
         ═══════════════════════════════════════════════════════════ -->
    <section class="relative overflow-hidden py-16 sm:py-24">
      <!-- Glow effect -->
      <div class="pointer-events-none absolute -top-40 left-1/2 -translate-x-1/2 w-[600px] h-[600px] rounded-full bg-primary/10 blur-[120px]" />

      <div class="relative mx-auto max-w-6xl px-4 sm:px-6">
        <div class="grid items-center gap-12 lg:grid-cols-2">
          <!-- Text -->
          <div>
            <h1 class="text-4xl font-extrabold leading-tight sm:text-5xl lg:text-6xl">
              Bolao de Futebol
              <span class="text-primary">Online</span>
            </h1>
            <p class="mt-4 max-w-lg text-text-secondary sm:text-lg">
              Compre seus cupons, faca seus palpites em segundos e acompanhe o ranking
              em tempo real &mdash; a Copa 2026 na ponta dos dedos.
            </p>

            <div class="mt-8 flex flex-wrap gap-3">
              <RouterLink
                v-if="autenticacao.estaAutenticado"
                to="/painel"
                class="inline-flex items-center gap-2 rounded-full bg-primary px-6 py-3 font-semibold text-bg transition-all hover:bg-primary-hover hover:shadow-lg hover:shadow-primary/25"
              >
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" /></svg>
                Meus Cupons
              </RouterLink>
              <template v-else>
                <button
                  type="button"
                  class="inline-flex items-center gap-2 rounded-full bg-primary px-6 py-3 font-semibold text-bg transition-all hover:bg-primary-hover hover:shadow-lg hover:shadow-primary/25"
                  @click="$emit('abrirModalAuth', 'cadastro')"
                >
                  <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" /></svg>
                  Comecar agora
                </button>
                <button
                  type="button"
                  class="inline-flex items-center gap-2 rounded-full border border-border px-6 py-3 font-semibold text-text transition-all hover:border-primary hover:text-primary"
                  @click="$emit('abrirModalAuth', 'entrar')"
                >
                  Ja tenho conta
                </button>
              </template>
            </div>

            <!-- Stats -->
            <div class="mt-10 flex gap-8">
              <div>
                <p class="text-3xl font-extrabold text-text">48</p>
                <p class="text-xs text-text-muted uppercase tracking-wider">Selecoes</p>
              </div>
              <div>
                <p class="text-3xl font-extrabold text-text">72</p>
                <p class="text-xs text-text-muted uppercase tracking-wider">Jogos</p>
              </div>
              <div>
                <p class="text-3xl font-extrabold text-text">12</p>
                <p class="text-xs text-text-muted uppercase tracking-wider">Grupos</p>
              </div>
            </div>
          </div>

          <!-- Mockup placeholder -->
          <div class="relative hidden lg:block">
            <div class="relative mx-auto w-72">
              <!-- Phone frame -->
              <div class="rounded-[2rem] border-2 border-border bg-bg-card p-3 shadow-2xl shadow-primary/10">
                <div class="rounded-[1.5rem] bg-bg overflow-hidden">
                  <div class="bg-gradient-to-b from-primary-dim/40 to-bg p-4 text-center">
                    <div class="mx-auto mt-4 h-4 w-20 rounded bg-primary/30" />
                    <p class="mt-3 text-xs text-text-muted">Rodada 1</p>
                    <div class="mt-4 space-y-2">
                      <div v-for="n in 3" :key="n" class="flex items-center justify-between rounded-lg bg-bg-input px-3 py-2">
                        <span class="text-xs font-semibold text-text-secondary">{{ ['MEX', 'BRA', 'ARG'][n-1] }}</span>
                        <span class="text-xs text-text-muted">vs</span>
                        <span class="text-xs font-semibold text-text-secondary">{{ ['RSA', 'MAR', 'ALG'][n-1] }}</span>
                      </div>
                    </div>
                    <div class="mt-4 rounded-xl bg-primary/20 p-3">
                      <p class="text-xs font-bold text-primary">RANKING AO VIVO</p>
                      <div class="mt-2 space-y-1">
                        <div v-for="n in 3" :key="n" class="flex items-center justify-between">
                          <span class="text-[10px] text-text-muted">{{ n }}o</span>
                          <div class="h-1.5 flex-1 mx-2 rounded bg-bg-input">
                            <div class="h-full rounded bg-primary" :style="{ width: `${100 - n * 25}%` }" />
                          </div>
                          <span class="text-[10px] font-bold text-primary">{{ [87, 65, 42][n-1] }}pts</span>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <!-- Glow behind phone -->
              <div class="pointer-events-none absolute -inset-8 -z-10 rounded-full bg-primary/5 blur-3xl" />
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ═══════════════════════════════════════════════════════════
         COMO FUNCIONA
         ═══════════════════════════════════════════════════════════ -->
    <section class="py-16 sm:py-20">
      <div class="mx-auto max-w-6xl px-4 sm:px-6 text-center">
        <h2 class="text-2xl font-bold sm:text-3xl">
          Como funciona o <span class="text-primary">Bolao</span>
        </h2>
        <p class="mt-3 text-sm text-text-secondary">Veja como e facil participar em poucos passos</p>

        <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
          <div
            v-for="(passo, i) in passos"
            :key="i"
            class="group relative rounded-2xl border border-border bg-bg-card p-6 transition-all hover:border-primary/50 hover:shadow-lg hover:shadow-primary/5"
          >
            <!-- Step number -->
            <div class="absolute -top-3 left-6 flex h-6 w-6 items-center justify-center rounded-full bg-primary text-xs font-bold text-bg">
              {{ i + 1 }}
            </div>
            <!-- Icon -->
            <div class="mx-auto mt-2 flex h-14 w-14 items-center justify-center rounded-xl bg-primary/10 text-primary transition-colors group-hover:bg-primary/20">
              <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" :d="passo.icone" />
              </svg>
            </div>
            <h3 class="mt-4 text-sm font-bold">{{ passo.titulo }}</h3>
            <p class="mt-2 text-xs text-text-muted leading-relaxed">{{ passo.descricao }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- ═══════════════════════════════════════════════════════════
         O JEITO INTELIGENTE
         ═══════════════════════════════════════════════════════════ -->
    <section class="py-16 sm:py-20">
      <div class="mx-auto max-w-6xl px-4 sm:px-6">
        <div class="grid items-center gap-12 lg:grid-cols-2">
          <!-- Image placeholder -->
          <div class="relative">
            <div class="rounded-2xl border border-border bg-bg-card p-6 shadow-xl shadow-primary/5">
              <div class="space-y-3">
                <div v-for="(feat, i) in featuresInteligente" :key="i" class="flex items-center gap-3 rounded-xl bg-bg-input p-3">
                  <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary/20">
                    <svg class="h-4 w-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                  </div>
                  <span class="text-sm text-text-secondary">{{ feat }}</span>
                </div>
              </div>
            </div>
            <div class="pointer-events-none absolute -inset-4 -z-10 rounded-3xl bg-primary/5 blur-2xl" />
          </div>

          <!-- Text -->
          <div>
            <h2 class="text-2xl font-bold sm:text-3xl">
              O jeito inteligente de<br />fazer <span class="text-primary">bolao</span>
            </h2>
            <p class="mt-4 text-sm text-text-secondary leading-relaxed">
              Esqueca planilhas e grupos de WhatsApp. O Inter World Cup faz tudo por voce:
              pontuacao automatica, ranking em tempo real e controle total dos seus palpites.
            </p>

            <ul class="mt-6 space-y-3">
              <li v-for="(item, i) in beneficios" :key="i" class="flex items-start gap-3">
                <svg class="mt-0.5 h-5 w-5 shrink-0 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="text-sm text-text-secondary">{{ item }}</span>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </section>

    <!-- ═══════════════════════════════════════════════════════════
         REGRAS DE PONTUACAO
         ═══════════════════════════════════════════════════════════ -->
    <section class="py-16 sm:py-20">
      <div class="mx-auto max-w-6xl px-4 sm:px-6 text-center">
        <h2 class="text-2xl font-bold sm:text-3xl">
          Defina o nivel da <span class="text-primary">disputa</span>
        </h2>
        <p class="mt-3 text-sm text-text-secondary">Pontuacao configurada para valorizar quem realmente entende de futebol</p>

        <div v-if="regras.length" class="mt-12 mx-auto max-w-2xl">
          <div class="rounded-2xl border border-border bg-bg-card overflow-hidden">
            <div class="bg-gradient-to-r from-primary-dim/40 to-bg-card px-6 py-4">
              <h3 class="text-sm font-bold text-primary uppercase tracking-wider">Tabela de Pontos</h3>
            </div>
            <div class="divide-y divide-border/50">
              <div
                v-for="regra in regras"
                :key="regra.id"
                class="flex items-center justify-between px-6 py-3 transition-colors hover:bg-bg-card-hover"
              >
                <div>
                  <p class="text-sm font-medium">{{ regra.nome }}</p>
                  <p v-if="regra.descricao" class="text-xs text-text-muted">{{ regra.descricao }}</p>
                </div>
                <span class="shrink-0 ml-4 rounded-full bg-primary/20 px-3 py-1 text-sm font-bold text-primary">
                  +{{ regra.pontos }} pts
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ═══════════════════════════════════════════════════════════
         COMPRE SEU CUPOM (CTA)
         ═══════════════════════════════════════════════════════════ -->
    <section class="py-16 sm:py-20">
      <div class="mx-auto max-w-6xl px-4 sm:px-6">
        <div class="relative overflow-hidden rounded-3xl border border-primary/30 bg-gradient-to-br from-primary-dim/30 via-bg-card to-bg-card p-8 sm:p-12 text-center">
          <!-- Glow -->
          <div class="pointer-events-none absolute top-0 left-1/2 -translate-x-1/2 w-96 h-96 rounded-full bg-primary/10 blur-[100px]" />

          <div class="relative">
            <span class="inline-block rounded-full border border-primary/30 bg-primary/10 px-4 py-1.5 text-xs font-semibold uppercase tracking-wider text-primary">
              Copa do Mundo 2026
            </span>
            <h2 class="mt-4 text-2xl font-bold sm:text-3xl">
              Garanta sua entrada no bolao
            </h2>
            <p class="mx-auto mt-3 max-w-lg text-sm text-text-secondary">
              Cada cupom e um conjunto independente de palpites. Compre quantos quiser
              e aumente suas chances de acertar.
            </p>

            <div class="mt-8 inline-flex flex-col items-center">
              <p class="text-4xl font-extrabold text-primary">R$ 10,00</p>
              <p class="mt-1 text-xs text-text-muted">por cupom</p>
            </div>

            <div class="mt-8">
              <RouterLink
                v-if="autenticacao.estaAutenticado"
                to="/checkout"
                class="inline-flex items-center gap-2 rounded-full bg-primary px-8 py-3 font-semibold text-bg transition-all hover:bg-primary-hover hover:shadow-lg hover:shadow-primary/25"
              >
                Comprar cupom
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" /></svg>
              </RouterLink>
              <button
                v-else
                type="button"
                class="inline-flex items-center gap-2 rounded-full bg-primary px-8 py-3 font-semibold text-bg transition-all hover:bg-primary-hover hover:shadow-lg hover:shadow-primary/25"
                @click="$emit('abrirModalAuth', 'cadastro')"
              >
                Criar conta e comprar
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" /></svg>
              </button>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ═══════════════════════════════════════════════════════════
         FAQ
         ═══════════════════════════════════════════════════════════ -->
    <section class="py-16 sm:py-20">
      <div class="mx-auto max-w-3xl px-4 sm:px-6 text-center">
        <h2 class="text-2xl font-bold sm:text-3xl">
          Perguntas frequentes sobre o<br /><span class="text-primary">Bolao</span>
        </h2>
        <p class="mt-3 text-sm text-text-secondary">Tudo o que voce precisa saber</p>

        <div class="mt-10 space-y-3 text-left">
          <div
            v-for="(item, i) in faq"
            :key="i"
            class="rounded-xl border border-border bg-bg-card overflow-hidden transition-all"
            :class="faqAberto === i ? 'border-primary/30' : ''"
          >
            <button
              type="button"
              class="flex w-full items-center justify-between px-5 py-4 text-left transition-colors hover:bg-bg-card-hover"
              @click="faqAberto = faqAberto === i ? -1 : i"
            >
              <span class="text-sm font-medium pr-4">{{ item.pergunta }}</span>
              <svg
                class="h-5 w-5 shrink-0 text-text-muted transition-transform duration-200"
                :class="faqAberto === i ? 'rotate-180' : ''"
                fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
              >
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
              </svg>
            </button>
            <div
              class="overflow-hidden transition-all duration-200"
              :class="faqAberto === i ? 'max-h-40 pb-4' : 'max-h-0'"
            >
              <p class="px-5 text-sm text-text-secondary leading-relaxed">{{ item.resposta }}</p>
            </div>
          </div>
        </div>

        <!-- CTA below FAQ -->
        <div class="mt-10 flex flex-wrap justify-center gap-3">
          <button
            v-if="!autenticacao.estaAutenticado"
            type="button"
            class="rounded-full bg-primary px-6 py-2.5 text-sm font-semibold text-bg transition-all hover:bg-primary-hover hover:shadow-lg hover:shadow-primary/25"
            @click="$emit('abrirModalAuth', 'cadastro')"
          >
            Criar minha conta
          </button>
          <RouterLink
            v-if="!autenticacao.estaAutenticado"
            to="/"
            class="rounded-full border border-border px-6 py-2.5 text-sm font-semibold text-text-secondary transition-all hover:border-primary hover:text-primary"
            @click.prevent="$emit('abrirModalAuth', 'entrar')"
          >
            Ja tenho conta
          </RouterLink>
        </div>
      </div>
    </section>

    <!-- ═══════════════════════════════════════════════════════════
         FOOTER
         ═══════════════════════════════════════════════════════════ -->
    <footer class="border-t border-border py-10">
      <div class="mx-auto max-w-6xl px-4 sm:px-6">
        <div class="flex flex-col items-center gap-6 sm:flex-row sm:justify-between">
          <div class="flex items-center gap-2">
            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary text-sm font-bold text-bg">IW</div>
            <span class="text-sm font-bold text-text-secondary">Inter World Cup</span>
          </div>
          <p class="text-xs text-text-muted">&copy; 2026 Inter World Cup. Todos os direitos reservados.</p>
        </div>
      </div>
    </footer>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { usarAutenticacaoStore } from '../stores/autenticacao'
import { requisicaoApi } from '../services/api'
import type { RegraPontuacao, Torneio } from '../tipos'

defineEmits<{ abrirModalAuth: [modo: 'entrar' | 'cadastro'] }>()

const autenticacao = usarAutenticacaoStore()
const regras = ref<RegraPontuacao[]>([])
const faqAberto = ref(-1)

const passos = [
  {
    titulo: 'Crie sua conta',
    descricao: 'Cadastre-se em segundos com nome, email e telefone.',
    icone: 'M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z',
  },
  {
    titulo: 'Compre um cupom',
    descricao: 'Cada cupom e uma entrada independente no bolao. Compre quantos quiser.',
    icone: 'M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z',
  },
  {
    titulo: 'Faca seus palpites',
    descricao: 'Preencha placares, classificados, artilheiro e campeao para cada cupom.',
    icone: 'M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10',
  },
  {
    titulo: 'Acompanhe o ranking',
    descricao: 'Veja sua posicao atualizada automaticamente apos cada rodada.',
    icone: 'M16.5 18.75h-9m9 0a3 3 0 013 3h-15a3 3 0 013-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 01-.982-3.172M9.497 14.25a7.454 7.454 0 00.981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 007.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M18.75 4.236c.982.143 1.954.317 2.916.52A6.003 6.003 0 0016.27 9.728M18.75 4.236V4.5c0 2.108-.966 3.99-2.48 5.228m0 0a6.023 6.023 0 01-2.27.308m4.75 0a6.023 6.023 0 002.27.308',
  },
]

const featuresInteligente = [
  'Ranking automatico em tempo real',
  'Palpites independentes por cupom',
  'Pontuacao configuravel e transparente',
  'Todas as 48 selecoes da Copa 2026',
  '72 jogos da fase de grupos',
  'Fase eliminatoria completa',
]

const beneficios = [
  'Pontuacao calculada automaticamente a cada resultado',
  'Ranking atualizado em tempo real para todos os cupons',
  'Regras claras e visiveis antes de apostar',
  'Sem planilhas, sem confusao — tudo no sistema',
  'Multiplos cupons para aumentar suas chances',
]

const faq = [
  { pergunta: 'O que e o Inter World Cup?', resposta: 'E um bolao online para a Copa do Mundo 2026. Voce compra cupons, faz palpites sobre os jogos e concorre no ranking geral.' },
  { pergunta: 'Como funciona a compra de cupom?', resposta: 'Cada cupom custa R$ 10,00 e representa uma entrada independente. Voce pode comprar quantos cupons quiser, cada um com seus proprios palpites.' },
  { pergunta: 'Quanto custa participar?', resposta: 'Cada cupom custa R$ 10,00. No MVP o pagamento e simulado, mas o fluxo e identico ao real.' },
  { pergunta: 'Preciso instalar algum aplicativo?', resposta: 'Nao! O sistema e 100% web e responsivo. Funciona no celular, tablet e computador sem instalar nada.' },
  { pergunta: 'Quais palpites posso fazer?', resposta: 'Placares de todos os 72 jogos da fase de grupos, classificados de cada grupo, jogos mata-mata, artilheiro, campeao, vice e terceiro colocado.' },
  { pergunta: 'Como funciona a pontuacao?', resposta: 'A pontuacao e configurada por regras no sistema. Placar exato vale mais, seguido de acertar o vencedor e gols parciais. Veja a tabela de pontos acima.' },
]

onMounted(async () => {
  try {
    const resposta = await requisicaoApi<{ torneio: Torneio }>('/torneio')
    regras.value = resposta.torneio.regras_pontuacao?.filter((r) => r.ativo) ?? []
  } catch {
    // Silencioso
  }
})
</script>
