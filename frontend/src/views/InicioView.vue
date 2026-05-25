<template>
  <div class="min-h-screen bg-[#070909] text-white">
    <header class="sticky top-0 z-50 border-b border-emerald-400/15 bg-[#070909]/90 backdrop-blur-xl">
      <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:h-20 sm:px-6 lg:px-8">
        <RouterLink to="/" class="flex items-center gap-3">
          <div class="flex h-11 w-11 items-center justify-center rounded-2xl border border-emerald-400/30 bg-emerald-400/10 text-sm font-black tracking-[0.2em] text-emerald-300">
            IW
          </div>
          <div>
            <p class="text-sm font-bold tracking-[0.18em] text-white">INTER WORLD CUP</p>
            <p class="text-[11px] uppercase tracking-[0.3em] text-emerald-300/80">Bolao 2026</p>
          </div>
        </RouterLink>

        <nav class="hidden items-center gap-7 text-sm text-zinc-300 lg:flex">
          <a href="#como-funciona" class="transition hover:text-white">Como funciona</a>
          <a href="#vantagens" class="transition hover:text-white">Vantagens</a>
          <a href="#pontuacao" class="transition hover:text-white">Pontuacao</a>
          <a href="#faq" class="transition hover:text-white">FAQ</a>
        </nav>

        <div class="flex items-center gap-3">
          <RouterLink
            v-if="autenticacao.estaAutenticado"
            to="/painel"
            class="hidden rounded-full border border-emerald-400/30 px-4 py-2 text-sm font-semibold text-emerald-300 transition hover:border-emerald-300 hover:text-white sm:inline-flex"
          >
            Meus Cupons
          </RouterLink>
          <template v-else>
            <button
              type="button"
              class="hidden rounded-full px-4 py-2 text-sm font-medium text-zinc-300 transition hover:text-white sm:inline-flex"
              @click="$emit('abrirModalAuth', 'entrar')"
            >
              Entrar
            </button>
            <button
              type="button"
              class="rounded-full bg-emerald-400 px-4 py-2 text-sm font-bold text-[#04110c] shadow-[0_0_24px_rgba(52,211,153,0.28)] transition hover:scale-[1.02] hover:bg-emerald-300"
              @click="$emit('abrirModalAuth', autenticacao.estaAutenticado ? 'entrar' : 'cadastro')"
            >
              {{ autenticacao.estaAutenticado ? 'Abrir painel' : 'Criar conta' }}
            </button>
          </template>
        </div>
      </div>
    </header>

    <section class="relative overflow-hidden border-b border-white/5">
      <div class="absolute inset-0 bg-[radial-gradient(circle_at_20%_10%,rgba(52,211,153,0.08),transparent_28%),radial-gradient(circle_at_65%_25%,rgba(16,185,129,0.18),transparent_30%),radial-gradient(circle_at_50%_100%,rgba(16,185,129,0.08),transparent_40%)]" />
      <img :src="trophyAsset" alt="" class="pointer-events-none absolute bottom-8 right-[-6rem] hidden w-60 opacity-30 xl:block">

      <div class="relative mx-auto grid max-w-7xl gap-16 px-4 py-14 sm:px-6 lg:grid-cols-[1.02fr_1.1fr] lg:px-8 lg:py-24">
        <div class="max-w-xl">
          <div class="inline-flex items-center gap-2 rounded-full border border-emerald-400/20 bg-emerald-400/10 px-4 py-1.5 text-xs font-semibold uppercase tracking-[0.28em] text-emerald-300">
            Copa do Mundo 2026
          </div>
          <h1 class="mt-8 text-5xl font-black leading-[0.95] tracking-[-0.04em] text-white sm:text-6xl lg:text-7xl">
            Compre seu cupom,
            <span class="block text-emerald-400">faca seus palpites</span>
            <span class="block">e dispute o premio</span>
          </h1>
          <p class="mt-6 max-w-lg text-base leading-8 text-zinc-300 sm:text-xl">
            Cada cupom e uma entrada independente no bolao da Copa 2026. Acompanhe sua pontuacao, suba no ranking e veja quem leva o premio no fim da competicao.
          </p>

          <div class="mt-10 flex flex-col gap-4 sm:flex-row">
            <RouterLink
              v-if="autenticacao.estaAutenticado"
              to="/painel"
              class="inline-flex items-center justify-center gap-2 rounded-full bg-emerald-400 px-7 py-4 text-sm font-black text-[#04110c] shadow-[0_0_32px_rgba(52,211,153,0.32)] transition hover:scale-[1.02] hover:bg-emerald-300"
            >
              Abrir meus cupons
              <span aria-hidden="true">-></span>
            </RouterLink>
            <template v-else>
              <button
                type="button"
                class="inline-flex items-center justify-center gap-2 rounded-full bg-emerald-400 px-7 py-4 text-sm font-black text-[#04110c] shadow-[0_0_32px_rgba(52,211,153,0.32)] transition hover:scale-[1.02] hover:bg-emerald-300"
                @click="$emit('abrirModalAuth', 'cadastro')"
              >
                Criar conta e entrar
                <span aria-hidden="true">-></span>
              </button>
              <button
                type="button"
                class="inline-flex items-center justify-center gap-2 rounded-full border border-emerald-400/40 bg-white/[0.02] px-7 py-4 text-sm font-bold text-white transition hover:border-emerald-300 hover:bg-emerald-400/10"
                @click="$emit('abrirModalAuth', 'entrar')"
              >
                Ja tenho conta
              </button>
            </template>
          </div>

          <p class="mt-8 flex items-center gap-2 text-sm text-zinc-400">
            <span class="h-2 w-2 rounded-full bg-emerald-400 shadow-[0_0_16px_rgba(52,211,153,0.8)]" />
            {{ provaSocial }}
          </p>

          <div class="mt-10 grid max-w-md grid-cols-3 gap-4">
            <article
              v-for="item in estatisticasHero"
              :key="item.label"
              class="rounded-[1.75rem] border border-white/8 bg-white/[0.03] px-4 py-5 shadow-[inset_0_1px_0_rgba(255,255,255,0.05)]"
            >
              <p class="text-[11px] uppercase tracking-[0.24em] text-emerald-300">{{ item.label }}</p>
              <p class="mt-3 text-3xl font-black tracking-[-0.04em] text-white">{{ item.value }}</p>
              <p class="mt-1 text-xs text-zinc-500">{{ item.caption }}</p>
            </article>
          </div>
        </div>

        <div class="relative min-h-[24rem] lg:min-h-[40rem]">
          <div class="absolute inset-x-10 top-16 h-72 rounded-full bg-emerald-400/20 blur-[110px] sm:h-96" />
          <img :src="heroLeftAsset" alt="Tela lateral esquerda do app" class="absolute left-[2%] top-[18%] hidden w-[28%] rotate-[-16deg] opacity-70 drop-shadow-[0_24px_60px_rgba(7,12,10,0.6)] md:block">
          <img :src="heroCenterAsset" alt="Tela principal do app" class="relative z-10 mx-auto w-[54%] min-w-[18rem] drop-shadow-[0_32px_80px_rgba(0,0,0,0.65)] sm:w-[49%]">
          <img :src="heroRightAsset" alt="Tela lateral direita do app" class="absolute right-[1%] top-[26%] hidden w-[29%] rotate-[17deg] opacity-85 drop-shadow-[0_24px_60px_rgba(7,12,10,0.6)] md:block">
        </div>
      </div>
    </section>

    <section id="como-funciona" class="relative overflow-hidden border-b border-white/5 py-20 sm:py-24">
      <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="text-center">
          <p class="text-xs font-semibold uppercase tracking-[0.3em] text-emerald-300">Passo a passo</p>
          <h2 class="mt-4 text-3xl font-black tracking-[-0.04em] text-white sm:text-5xl">
            Como funciona o bolao
          </h2>
          <p class="mx-auto mt-4 max-w-2xl text-base leading-7 text-zinc-400">
            Um fluxo simples para comprar o cupom, registrar os palpites, somar pontos e disputar o premio ate o fim da competicao.
          </p>
        </div>

        <div class="mt-14 grid gap-6 md:grid-cols-2 xl:grid-cols-5">
          <article
            v-for="passo in passos"
            :key="passo.titulo"
            class="group rounded-[2rem] border border-white/7 bg-[linear-gradient(180deg,rgba(16,185,129,0.06),rgba(255,255,255,0.02))] p-4 shadow-[inset_0_1px_0_rgba(255,255,255,0.05)] transition duration-300 hover:-translate-y-1 hover:border-emerald-400/30"
          >
            <div class="rounded-[1.6rem] border border-emerald-400/10 bg-[#0d1311] p-3">
              <img :src="passo.imagem" :alt="passo.titulo" class="mx-auto w-full rounded-[1.2rem]">
            </div>
            <div class="mt-5 flex items-center gap-3">
              <div class="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-400/15 text-xs font-black text-emerald-300">
                {{ passo.numero }}
              </div>
              <h3 class="text-base font-bold text-white">{{ passo.titulo }}</h3>
            </div>
            <p class="mt-3 text-sm leading-6 text-zinc-400">{{ passo.descricao }}</p>
          </article>
        </div>
      </div>
    </section>

    <section id="vantagens" class="relative overflow-hidden border-b border-white/5 py-20 sm:py-24">
      <img :src="trophyAsset" alt="" class="pointer-events-none absolute bottom-10 right-[-7rem] hidden w-72 opacity-25 xl:block">
      <div class="absolute left-[18%] top-16 h-64 w-64 rounded-full bg-emerald-400/8 blur-[120px]" />
      <div class="relative mx-auto grid max-w-7xl items-center gap-14 px-4 sm:px-6 lg:grid-cols-[0.95fr_1.05fr] lg:px-8">
        <div class="max-w-2xl">
          <p class="text-xs font-semibold uppercase tracking-[0.3em] text-emerald-300">Vantagens</p>
          <h2 class="mt-4 text-4xl font-black leading-tight tracking-[-0.04em] text-white sm:text-6xl">
            O jeito mais claro de fazer bolao
          </h2>
          <p class="mt-6 max-w-xl text-lg leading-8 text-zinc-400">
            O produto concentra o essencial da Copa 2026 em um unico fluxo: cupons independentes, palpites completos, calculo auditavel e ranking atualizado conforme os resultados sao lancados.
          </p>

          <ul class="mt-8 space-y-4">
            <li v-for="item in beneficios" :key="item" class="flex items-start gap-3 text-base leading-7 text-zinc-200">
              <span class="mt-1 flex h-6 w-6 shrink-0 items-center justify-center rounded-full border border-emerald-400/30 bg-emerald-400/10 text-xs font-black text-emerald-300">
                ✓
              </span>
              <span>{{ item }}</span>
            </li>
          </ul>

          <p class="mt-10 text-sm text-zinc-500">
            Tudo pensado para reduzir confusao no palpite, na pontuacao e na operacao do torneio.
          </p>
        </div>

        <div class="relative flex justify-center lg:justify-end">
          <div class="absolute h-72 w-72 rounded-full bg-emerald-400/15 blur-[120px]" />
          <img :src="featurePhoneAsset" alt="Tela inclinada do produto" class="relative z-10 w-[20rem] max-w-full drop-shadow-[0_32px_90px_rgba(0,0,0,0.7)] sm:w-[24rem]">
        </div>
      </div>
    </section>

    <section id="pontuacao" class="border-b border-white/5 py-20 sm:py-24">
      <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="grid gap-10 lg:grid-cols-[0.95fr_1.05fr] lg:items-start">
          <div class="max-w-2xl">
            <p class="text-xs font-semibold uppercase tracking-[0.3em] text-emerald-300">Pontuacao</p>
            <h2 class="mt-4 text-4xl font-black tracking-[-0.04em] text-white sm:text-5xl">
            Defina o nivel da disputa
            </h2>
            <p class="mt-5 text-lg leading-8 text-zinc-400">
              O administrador define as regras do torneio e o sistema recalcula a pontuacao dos cupons quando os resultados entram. Assim, o participante entende com clareza por que subiu ou caiu no ranking.
            </p>

            <ul class="mt-8 space-y-4">
              <li class="flex items-start gap-3 text-base leading-7 text-zinc-200">
                <span class="mt-1 flex h-6 w-6 shrink-0 items-center justify-center rounded-full border border-emerald-400/25 bg-emerald-400/10 text-[11px] font-black text-emerald-300">✓</span>
                <span>Regras configuraveis por torneio para refletir o modelo de disputa desejado.</span>
              </li>
              <li class="flex items-start gap-3 text-base leading-7 text-zinc-200">
                <span class="mt-1 flex h-6 w-6 shrink-0 items-center justify-center rounded-full border border-emerald-400/25 bg-emerald-400/10 text-[11px] font-black text-emerald-300">✓</span>
                <span>Eventos de pontuacao por cupom para auditar de onde veio cada acerto.</span>
              </li>
              <li class="flex items-start gap-3 text-base leading-7 text-zinc-200">
                <span class="mt-1 flex h-6 w-6 shrink-0 items-center justify-center rounded-full border border-emerald-400/25 bg-emerald-400/10 text-[11px] font-black text-emerald-300">✓</span>
                <span>Ranking consolidado com desempate por desempenho, sem depender de leitura manual.</span>
              </li>
            </ul>
          </div>

          <div class="grid gap-4 sm:grid-cols-2">
            <article
              v-for="regra in regrasExibidas"
              :key="regra.id"
              class="rounded-[1.8rem] border border-white/8 bg-white/[0.03] p-6"
            >
              <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-emerald-300">Regra ativa</p>
              <h3 class="mt-4 text-lg font-bold text-white">{{ regra.nome }}</h3>
              <p class="mt-3 text-sm leading-6 text-zinc-400">{{ regra.descricao || 'Pontuacao configurada para o torneio atual.' }}</p>
              <p class="mt-5 text-3xl font-black tracking-[-0.04em] text-white">+{{ regra.pontos }}</p>
            </article>
          </div>
        </div>
      </div>
    </section>

    <section class="border-b border-white/5 py-20 sm:py-24">
      <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="text-center">
          <p class="text-xs font-semibold uppercase tracking-[0.3em] text-emerald-300">Perfis de uso</p>
          <h2 class="mt-4 text-3xl font-black tracking-[-0.04em] text-white sm:text-5xl">
            O sistema atende quem joga e quem administra
          </h2>
        </div>

        <div class="mt-14 grid gap-6 lg:grid-cols-3">
          <article
            v-for="perfil in perfisUso"
            :key="perfil.nome"
            class="rounded-[2rem] border border-white/7 bg-white/[0.03] p-7 shadow-[inset_0_1px_0_rgba(255,255,255,0.04)]"
          >
            <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-emerald-300">{{ perfil.badge }}</p>
            <h3 class="mt-4 text-2xl font-black text-white">{{ perfil.nome }}</h3>
            <p class="mt-4 text-sm leading-7 text-zinc-400">{{ perfil.descricao }}</p>
            <ul class="mt-6 space-y-3">
              <li v-for="item in perfil.itens" :key="item" class="flex items-start gap-3 text-sm leading-6 text-zinc-200">
                <span class="mt-1 text-emerald-300">•</span>
                <span>{{ item }}</span>
              </li>
            </ul>
          </article>
        </div>
      </div>
    </section>

    <section class="border-b border-white/5 py-20 sm:py-24">
      <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="text-center">
          <p class="text-xs font-semibold uppercase tracking-[0.3em] text-emerald-300">Cobertura</p>
          <h2 class="mt-4 text-3xl font-black tracking-[-0.04em] text-white sm:text-5xl">
            Base pronta da Copa 2026
          </h2>
          <p class="mx-auto mt-4 max-w-2xl text-base leading-7 text-zinc-400">
            Como cada cupom depende do estado real da Copa, a base do torneio precisa estar consistente. Grupos, selecoes, fases e jogos sustentam a comparacao entre diferentes leituras.
          </p>
        </div>

        <div class="mt-14 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
          <article
            v-for="selecao in selecoesDestaque"
            :key="selecao.id"
            class="rounded-[1.6rem] border border-white/8 bg-white/[0.03] px-4 py-5 text-center"
          >
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl border border-emerald-400/20 bg-emerald-400/10 text-base font-black text-emerald-300">
              {{ selecao.sigla }}
            </div>
            <p class="mt-4 text-sm font-bold text-white">{{ selecao.nome }}</p>
          </article>
        </div>
      </div>
    </section>

    <section id="faq" class="border-b border-white/5 py-20 sm:py-24">
      <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
        <div class="text-center">
          <p class="text-xs font-semibold uppercase tracking-[0.3em] text-emerald-300">FAQ</p>
          <h2 class="mt-4 text-3xl font-black tracking-[-0.04em] text-white sm:text-5xl">
            Perguntas frequentes sobre o
            <span class="block text-emerald-400">Inter World Cup</span>
          </h2>
        </div>

        <div class="mt-14 space-y-4">
          <article
            v-for="(item, indice) in faq"
            :key="item.pergunta"
            class="overflow-hidden rounded-[1.7rem] border border-white/8 bg-white/[0.03]"
          >
            <button
              type="button"
              class="flex w-full items-center justify-between gap-5 px-6 py-5 text-left"
              @click="faqAberto = faqAberto === indice ? -1 : indice"
            >
              <span class="text-base font-semibold text-white">{{ item.pergunta }}</span>
              <span class="text-xl text-zinc-500">{{ faqAberto === indice ? '-' : '+' }}</span>
            </button>
            <div class="grid transition-[grid-template-rows] duration-300" :class="faqAberto === indice ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]'">
              <div class="overflow-hidden">
                <p class="px-6 pb-6 text-sm leading-7 text-zinc-400">{{ item.resposta }}</p>
              </div>
            </div>
          </article>
        </div>

        <div class="mt-12 flex flex-col items-center justify-center gap-4 sm:flex-row">
          <RouterLink
            v-if="autenticacao.estaAutenticado"
            to="/painel"
            class="inline-flex items-center justify-center rounded-full bg-emerald-400 px-7 py-3 text-sm font-black text-[#04110c] transition hover:bg-emerald-300"
          >
            Ir para meu painel
          </RouterLink>
          <template v-else>
            <button
              type="button"
              class="inline-flex items-center justify-center rounded-full bg-emerald-400 px-7 py-3 text-sm font-black text-[#04110c] transition hover:bg-emerald-300"
              @click="$emit('abrirModalAuth', 'cadastro')"
            >
              Criar minha conta
            </button>
            <button
              type="button"
              class="inline-flex items-center justify-center rounded-full border border-white/10 px-7 py-3 text-sm font-semibold text-zinc-200 transition hover:border-emerald-400/30 hover:text-white"
              @click="$emit('abrirModalAuth', 'entrar')"
            >
              Ja tenho conta
            </button>
          </template>
        </div>
      </div>
    </section>

    <footer class="relative overflow-hidden py-14">
      <img :src="trophyAsset" alt="" class="pointer-events-none absolute bottom-0 right-[-6rem] hidden w-64 opacity-15 xl:block">
      <div class="mx-auto grid max-w-7xl gap-12 px-4 sm:px-6 lg:grid-cols-[1.2fr_0.8fr_0.8fr_0.8fr] lg:px-8">
        <div>
          <div class="flex items-center gap-3">
            <div class="flex h-11 w-11 items-center justify-center rounded-2xl border border-emerald-400/30 bg-emerald-400/10 text-sm font-black tracking-[0.2em] text-emerald-300">
              IW
            </div>
            <div>
              <p class="text-sm font-bold tracking-[0.18em] text-white">INTER WORLD CUP</p>
              <p class="text-[11px] uppercase tracking-[0.3em] text-emerald-300/80">Bolao 2026</p>
            </div>
          </div>
          <p class="mt-6 max-w-sm text-sm leading-7 text-zinc-500">
            Plataforma focada em cupons independentes para que cada usuario possa testar multiplas estrategias, comparar desempenho e acompanhar o ranking da Copa com clareza.
          </p>
        </div>

        <div>
          <h3 class="text-sm font-bold uppercase tracking-[0.25em] text-zinc-300">Navegacao</h3>
          <ul class="mt-5 space-y-3 text-sm text-zinc-500">
            <li><a href="#como-funciona" class="transition hover:text-white">Como funciona</a></li>
            <li><a href="#vantagens" class="transition hover:text-white">Vantagens</a></li>
            <li><a href="#pontuacao" class="transition hover:text-white">Pontuacao</a></li>
            <li><a href="#faq" class="transition hover:text-white">FAQ</a></li>
          </ul>
        </div>

        <div>
          <h3 class="text-sm font-bold uppercase tracking-[0.25em] text-zinc-300">Produto</h3>
          <ul class="mt-5 space-y-3 text-sm text-zinc-500">
            <li>Cupons independentes</li>
            <li>Palpites por fase</li>
            <li>Ranking consolidado</li>
            <li>Painel admin</li>
          </ul>
        </div>

        <div>
          <h3 class="text-sm font-bold uppercase tracking-[0.25em] text-zinc-300">Acesso</h3>
          <div class="mt-5 space-y-3">
            <RouterLink
              v-if="autenticacao.estaAutenticado"
              to="/painel"
              class="inline-flex rounded-full border border-emerald-400/25 px-4 py-2 text-sm font-semibold text-emerald-300 transition hover:border-emerald-300 hover:text-white"
            >
              Abrir painel
            </RouterLink>
            <template v-else>
              <button
                type="button"
                class="inline-flex rounded-full bg-emerald-400 px-4 py-2 text-sm font-black text-[#04110c] transition hover:bg-emerald-300"
                @click="$emit('abrirModalAuth', 'cadastro')"
              >
                Criar conta
              </button>
            </template>
          </div>
        </div>
      </div>

      <div class="mx-auto mt-12 max-w-7xl border-t border-white/5 px-4 pt-6 text-xs text-zinc-600 sm:px-6 lg:px-8">
        © 2026 Inter World Cup. Todos os direitos reservados.
      </div>
    </footer>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import featurePhoneAsset from '../assets/feature-phone-tilted.webp'
import heroCenterAsset from '../assets/Hero-center.webp'
import heroLeftAsset from '../assets/hero-left.webp'
import heroRightAsset from '../assets/Hero-right.webp'
import step01Asset from '../assets/step-01.webp'
import step02Asset from '../assets/step-02.webp'
import step03Asset from '../assets/step-03.webp'
import step04Asset from '../assets/step-04.webp'
import step05Asset from '../assets/step-05.webp'
import trophyAsset from '../assets/trophy.webp'
import { requisicaoApi } from '../services/api'
import { usarAutenticacaoStore } from '../stores/autenticacao'
import type { RegraPontuacao, Selecao, Torneio } from '../tipos'

defineEmits<{ abrirModalAuth: [modo: 'entrar' | 'cadastro'] }>()

const autenticacao = usarAutenticacaoStore()
const faqAberto = ref(-1)
const torneio = ref<Torneio | null>(null)
const regras = ref<RegraPontuacao[]>([])

const passos = [
  {
    numero: '01',
    titulo: 'Entrar na conta',
    descricao: 'Acesse o sistema e va direto para o fluxo principal do bolao.',
    imagem: step01Asset,
  },
  {
    numero: '02',
    titulo: 'Comprar cupom',
    descricao: 'Cada cupom ativa uma entrada independente para disputar o premio.',
    imagem: step02Asset,
  },
  {
    numero: '03',
    titulo: 'Registrar palpites',
    descricao: 'Preencha seus palpites da fase de grupos e acompanhe o chaveamento ate a final.',
    imagem: step03Asset,
  },
  {
    numero: '04',
    titulo: 'Somar pontos',
    descricao: 'Com os resultados oficiais lancados, o sistema recalcula sua pontuacao.',
    imagem: step04Asset,
  },
  {
    numero: '05',
    titulo: 'Subir no ranking',
    descricao: 'Acompanhe sua posicao e veja qual cupom esta mais perto de levar o premio.',
    imagem: step05Asset,
  },
]

const beneficios = [
  'Cupons independentes para testar estrategias diferentes',
  'Palpites completos para grupos e mata-mata com chaveamento visual',
  'Regras de pontuacao visiveis e configuraveis no backend',
  'Ranking por cupom com criterios de desempate consistentes',
  'Estado do torneio vindo da API para manter o fluxo de palpites consistente',
  'Painel admin para resultados, regras e operacao do torneio',
]

const perfisUso = [
  {
    nome: 'Participante',
    badge: 'Joga',
    descricao: 'Entra no sistema, escolhe seus cupons e acompanha a evolucao dos palpites ao longo da Copa.',
    itens: ['Compra e ativa cupom', 'Salva palpites por fase', 'Consulta pontuacao e ranking'],
  },
  {
    nome: 'Competicao',
    badge: 'Disputa',
    descricao: 'O torneio estrutura fases, jogos e regras para que o ranking tenha lastro em eventos auditaveis.',
    itens: ['Grupos e fases cadastrados', 'Pontuacao por regra ativa', 'Ranking consolidado por cupom'],
  },
  {
    nome: 'Administrador',
    badge: 'Opera',
    descricao: 'Lanca resultados, ajusta regras e garante que o sistema reflita o estado oficial do torneio.',
    itens: ['Painel para jogos e regras', 'Recalculo da pontuacao', 'Fluxo operacional mais seguro'],
  },
]

const faq = [
  {
    pergunta: 'Como funciona a compra de cupom?',
    resposta: 'Cada cupom funciona como uma entrada independente. O usuario pode comprar mais de um e usar estrategias diferentes em cada conjunto de palpites.',
  },
  {
    pergunta: 'Quais palpites entram no produto?',
    resposta: 'O fluxo cobre fase de grupos, mata-mata progressivo por cupom e ranking consolidado com criterios de desempate.',
  },
  {
    pergunta: 'O ranking atualiza automaticamente?',
    resposta: 'Quando os resultados sao lancados e o recalculo e executado, o ranking reflete a pontuacao mais recente do torneio.',
  },
  {
    pergunta: 'Preciso instalar app para participar?',
    resposta: 'Nao. A plataforma roda no navegador e foi pensada para funcionar bem no celular e no desktop.',
  },
  {
    pergunta: 'Quem administra os resultados?',
    resposta: 'O administrador usa o painel para salvar resultados e acionar o recalculo da pontuacao do torneio.',
  },
]

const estatisticasHero = computed(() => {
  const selecoes = torneio.value?.grupos.flatMap((grupo) => grupo.selecoes) ?? []
  const jogos = torneio.value?.jogos ?? []
  const valorCupom = torneio.value?.valor_cupom

  return [
    {
      label: 'Selecoes',
      value: selecoes.length || 48,
      caption: 'no torneio',
    },
    {
      label: 'Jogos',
      value: jogos.length || 72,
      caption: 'na competicao',
    },
    {
      label: 'Cupom',
      value: valorCupom ? `R$ ${Number(valorCupom).toFixed(0)}` : 'R$ 10',
      caption: 'por entrada',
    },
  ]
})

const provaSocial = computed(() => {
  const quantidadeRegras = regras.value.length

  if (!quantidadeRegras) {
    return 'Fluxo de palpites e ranking construidos em cima do estado real do torneio.'
  }

  return `${quantidadeRegras} regras ativas prontas para sustentar o calculo da pontuacao.`
})

const regrasExibidas = computed(() => {
  if (regras.value.length) {
    return regras.value.slice(0, 4)
  }

  return [
    { id: 1, nome: 'Placar exato', descricao: 'Premia quem acerta o resultado completo do jogo.', pontos: 10, ativo: true, chave: 'placar_exato', fase_id: null },
    { id: 2, nome: 'Vencedor correto', descricao: 'Reconhece o acerto do vencedor mesmo sem o placar cheio.', pontos: 5, ativo: true, chave: 'vencedor', fase_id: null },
    { id: 3, nome: 'Classificado do mata-mata', descricao: 'Valoriza quem projeta a progressao correta do bracket.', pontos: 8, ativo: true, chave: 'classificado', fase_id: null },
    { id: 4, nome: 'Chaveamento', descricao: 'Valoriza a progressao correta das selecoes no mata-mata.', pontos: 12, ativo: true, chave: 'chaveamento', fase_id: null },
  ] satisfies RegraPontuacao[]
})

const selecoesDestaque = computed<Selecao[]>(() => {
  const selecoes = torneio.value?.grupos.flatMap((grupo) => grupo.selecoes) ?? []
  return selecoes.slice(0, 12)
})

onMounted(async () => {
  try {
    const resposta = await requisicaoApi<{ torneio: Torneio }>('/torneio')
    torneio.value = resposta.torneio
    regras.value = resposta.torneio.regras_pontuacao?.filter((regra) => regra.ativo && regra.chave !== 'artilheiro').slice(0, 6) ?? []
  } catch {
    torneio.value = null
    regras.value = []
  }
})
</script>
