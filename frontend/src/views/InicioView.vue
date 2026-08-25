<template>
  <div class="min-h-screen bg-bg text-text">
    <header class="sticky top-0 z-50 bg-gradient-to-r from-topo to-topo-claro shadow-md">
      <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:h-18 sm:px-6 lg:px-8">
        <RouterLink to="/" class="flex items-center gap-3">
          <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-lime-400 text-lg font-black text-topo">C</div>
          <div>
            <p class="text-sm font-black tracking-[0.14em] text-white">CRAVAÊ</p>
            <p class="text-[11px] uppercase tracking-[0.3em] text-emerald-200/80">Bolão Brasileirão</p>
          </div>
        </RouterLink>

        <nav class="hidden items-center gap-7 text-sm text-emerald-100/80 lg:flex">
          <a href="#jogos" class="transition hover:text-white">Jogos</a>
          <a href="#como-funciona" class="transition hover:text-white">Como funciona</a>
          <a href="#premio" class="transition hover:text-white">Prêmio</a>
          <a href="#pontuacao" class="transition hover:text-white">Pontuação</a>
          <a href="#faq" class="transition hover:text-white">FAQ</a>
        </nav>

        <div class="flex items-center gap-3">
          <RouterLink
            v-if="autenticacao.estaAutenticado"
            to="/painel"
            class="hidden rounded-full border border-white/25 px-4 py-2 text-sm font-semibold text-white transition hover:bg-white/10 sm:inline-flex"
          >
            Meus Cupons
          </RouterLink>
          <template v-else>
            <button
              type="button"
              class="hidden rounded-full px-4 py-2 text-sm font-medium text-emerald-100/80 transition hover:text-white sm:inline-flex"
              @click="$emit('abrirModalAuth', 'entrar')"
            >
              Entrar
            </button>
            <button
              type="button"
              class="rounded-full bg-lime-400 px-4 py-2 text-sm font-bold text-topo shadow-lg transition hover:scale-[1.02] hover:brightness-105"
              @click="$emit('abrirModalAuth', autenticacao.estaAutenticado ? 'entrar' : 'cadastro')"
            >
              {{ autenticacao.estaAutenticado ? 'Abrir painel' : 'Criar conta' }}
            </button>
          </template>
        </div>
      </div>
    </header>

    <!-- Hero escuro-verde -->
    <section class="relative overflow-hidden bg-gradient-to-br from-topo via-topo-claro to-topo pb-28 sm:pb-32">
      <div class="absolute inset-0 bg-[radial-gradient(circle_at_75%_20%,rgba(163,230,53,0.18),transparent_45%),radial-gradient(circle_at_15%_85%,rgba(16,185,129,0.15),transparent_40%)]" />

      <div class="relative mx-auto grid max-w-7xl gap-14 px-4 pt-12 sm:px-6 lg:grid-cols-[1.02fr_1fr] lg:px-8 lg:pt-16">
        <div class="max-w-xl">
          <div class="inline-flex items-center gap-2 rounded-full border border-lime-300/40 bg-lime-300/10 px-4 py-1.5 text-xs font-semibold uppercase tracking-[0.28em] text-lime-300">
            <span class="h-1.5 w-1.5 rounded-full bg-lime-300" />
            Brasileirão Série A
          </div>
          <h1 class="mt-7 text-4xl font-black leading-[1.02] tracking-[-0.03em] text-white sm:text-6xl">
            Crave o placar.
            <span class="block text-lime-300">Crave quem marca.</span>
          </h1>
          <p class="mt-5 max-w-lg text-base leading-8 text-emerald-100/85 sm:text-lg">
            O bolão do Brasileirão em que você palpita o placar de cada jogo da rodada e quem faz os gols. Quanto mais você crava, mais sobe no ranking — e mais perto fica do prêmio.
          </p>

          <div class="mt-9 flex flex-col gap-4 sm:flex-row">
            <RouterLink
              v-if="autenticacao.estaAutenticado"
              to="/painel"
              class="inline-flex items-center justify-center gap-2 rounded-full bg-lime-400 px-7 py-4 text-sm font-black text-topo shadow-xl transition hover:scale-[1.02] hover:brightness-105"
            >
              Abrir meus cupons
            </RouterLink>
            <template v-else>
              <button
                type="button"
                class="inline-flex items-center justify-center gap-2 rounded-full bg-lime-400 px-7 py-4 text-sm font-black text-topo shadow-xl transition hover:scale-[1.02] hover:brightness-105"
                @click="$emit('abrirModalAuth', 'cadastro')"
              >
                Quero participar
              </button>
              <button
                type="button"
                class="inline-flex items-center justify-center gap-2 rounded-full border border-white/30 px-7 py-4 text-sm font-bold text-white transition hover:bg-white/10"
                @click="$emit('abrirModalAuth', 'entrar')"
              >
                Já tenho conta
              </button>
            </template>
          </div>

          <div class="mt-10 grid max-w-md grid-cols-3 gap-4">
            <article
              v-for="item in estatisticasHero"
              :key="item.label"
              class="rounded-2xl border border-white/10 bg-white/5 px-4 py-4 backdrop-blur-sm"
            >
              <p class="text-[11px] uppercase tracking-[0.2em] text-emerald-200/80">{{ item.label }}</p>
              <p class="mt-2 text-2xl font-black tracking-[-0.03em] text-white sm:text-3xl">{{ item.value }}</p>
              <p class="mt-0.5 text-xs text-emerald-100/60">{{ item.caption }}</p>
            </article>
          </div>
        </div>

        <div class="relative hidden min-h-[26rem] lg:block">
          <div class="absolute inset-x-10 top-10 h-72 rounded-full bg-lime-400/15 blur-[110px]" />
          <img :src="heroLeftAsset" alt="" class="absolute left-[2%] top-[16%] w-[28%] rotate-[-14deg] opacity-70 drop-shadow-[0_24px_60px_rgba(0,0,0,0.45)]">
          <img :src="heroCenterAsset" alt="Tela do app" class="relative z-10 mx-auto w-[52%] min-w-[16rem] drop-shadow-[0_32px_80px_rgba(0,0,0,0.55)]">
          <img :src="heroRightAsset" alt="" class="absolute right-[1%] top-[24%] w-[29%] rotate-[15deg] opacity-85 drop-shadow-[0_24px_60px_rgba(0,0,0,0.45)]">
        </div>
      </div>
    </section>

    <!-- Faixa de jogos do Brasileirão (TheSportsDB), sobreposta ao hero -->
    <section id="jogos" class="relative z-10 -mt-20 pb-4 sm:-mt-24">
      <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div v-if="rodadaAtual" class="mb-3 flex items-center justify-between gap-2">
          <div class="flex items-center gap-2">
            <span class="rounded-full bg-white/15 px-3 py-1 text-xs font-bold uppercase tracking-wider text-white backdrop-blur-sm">Brasileirão Série A</span>
            <span class="rounded-full bg-white/15 px-3 py-1 text-xs font-bold uppercase tracking-wider text-white backdrop-blur-sm">Rodada {{ rodadaAtual }}</span>
          </div>
          <span class="hidden text-xs font-semibold text-white/70 sm:block">{{ jogosRodada.length }} jogos na rodada</span>
        </div>
        <div class="group/faixa relative">
        <button
          type="button"
          aria-label="Jogos anteriores"
          class="absolute -left-3 top-1/2 z-20 hidden h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full border border-border bg-bg-card text-text-secondary shadow-lg transition hover:bg-primary hover:text-white sm:flex"
          @click="rolarFaixa(-1)"
        >
          <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
        </button>
        <button
          type="button"
          aria-label="Próximos jogos"
          class="absolute -right-3 top-1/2 z-20 hidden h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full border border-border bg-bg-card text-text-secondary shadow-lg transition hover:bg-primary hover:text-white sm:flex"
          @click="rolarFaixa(1)"
        >
          <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
        </button>
        <div ref="faixaJogosRef" class="flex snap-x gap-4 overflow-x-auto pb-4 scrollbar-none">
          <article
            v-for="jogo in jogosRodada"
            :key="jogo.idEvent"
            class="relative w-72 shrink-0 snap-start overflow-hidden rounded-2xl border border-border bg-bg-card shadow-lg shadow-black/10"
          >
            <div
              v-if="jogo.strThumb"
              class="absolute inset-0 bg-cover bg-center"
              :style="{ backgroundImage: `url(${jogo.strThumb})` }"
            />
            <div class="absolute inset-0 bg-gradient-to-b from-black/70 via-black/45 to-black/80" />

            <div class="relative flex h-full flex-col p-4">
              <div class="flex items-center justify-between gap-2 text-[11px] font-semibold uppercase tracking-wide">
                <span v-if="temPlacar(jogo)" class="shrink-0 rounded-full bg-primary px-2.5 py-1 text-white">Encerrado</span>
                <span v-else class="shrink-0 rounded-full bg-amber-300 px-2.5 py-1 text-[#1c1500]">{{ formatarDataEvento(jogo) }}</span>
                <span class="truncate text-white/70">{{ jogo.strVenue }}</span>
              </div>

              <div class="mt-5 space-y-3">
                <div class="flex items-center justify-between">
                  <div class="flex min-w-0 items-center gap-2.5">
                    <img v-if="jogo.strHomeTeamBadge" :src="jogo.strHomeTeamBadge" alt="" class="h-8 w-8 shrink-0 object-contain drop-shadow">
                    <span class="truncate text-sm font-bold text-white">{{ jogo.strHomeTeam }}</span>
                  </div>
                  <span class="text-base font-black text-white">{{ temPlacar(jogo) ? jogo.intHomeScore : '' }}</span>
                </div>
                <div class="flex items-center justify-between">
                  <div class="flex min-w-0 items-center gap-2.5">
                    <img v-if="jogo.strAwayTeamBadge" :src="jogo.strAwayTeamBadge" alt="" class="h-8 w-8 shrink-0 object-contain drop-shadow">
                    <span class="truncate text-sm font-bold text-white">{{ jogo.strAwayTeam }}</span>
                  </div>
                  <span class="text-base font-black text-white">{{ temPlacar(jogo) ? jogo.intAwayScore : '' }}</span>
                </div>
              </div>

              <RouterLink
                v-if="autenticacao.estaAutenticado"
                to="/painel"
                class="mt-5 block w-full rounded-xl bg-white/90 py-2 text-center text-xs font-bold text-topo backdrop-blur-sm transition hover:bg-white"
              >
                Fazer meu palpite
              </RouterLink>
              <button
                v-else
                type="button"
                class="mt-5 w-full rounded-xl bg-white/90 py-2 text-xs font-bold text-topo backdrop-blur-sm transition hover:bg-white"
                @click="$emit('abrirModalAuth', 'cadastro')"
              >
                Fazer meu palpite
              </button>
            </div>
          </article>

          <article
            v-if="!jogosRodada.length"
            class="w-full rounded-2xl border border-border bg-bg-card p-6 text-center text-sm text-text-muted shadow-lg shadow-black/5"
          >
            Os jogos da próxima rodada do Brasileirão aparecem aqui em instantes.
          </article>
        </div>
        </div>
      </div>
    </section>

    <!-- Como funciona -->
    <section id="como-funciona" class="py-16 sm:py-20">
      <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="text-center">
          <p class="text-xs font-semibold uppercase tracking-[0.3em] text-primary">Passo a passo</p>
          <h2 class="mt-3 text-3xl font-black tracking-[-0.03em] text-text sm:text-4xl">
            Participar leva menos de 5 minutos
          </h2>
          <p class="mx-auto mt-4 max-w-2xl text-base leading-7 text-text-secondary">
            Do cadastro ao primeiro palpite, o fluxo é direto: cupom pago via Pix, palpites por rodada e ranking atualizado a cada resultado do Brasileirão.
          </p>
        </div>

        <div class="mt-12 grid gap-5 md:grid-cols-2 xl:grid-cols-5">
          <article
            v-for="passo in passos"
            :key="passo.titulo"
            class="group rounded-2xl border border-border bg-bg-card p-4 shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-lg"
          >
            <div class="overflow-hidden rounded-xl border border-border bg-bg p-2">
              <img :src="passo.imagem" :alt="passo.titulo" class="mx-auto w-full rounded-lg">
            </div>
            <div class="mt-4 flex items-center gap-3">
              <div class="flex h-8 w-8 items-center justify-center rounded-full bg-primary-dim text-xs font-black text-primary">
                {{ passo.numero }}
              </div>
              <h3 class="text-base font-bold text-text">{{ passo.titulo }}</h3>
            </div>
            <p class="mt-2.5 text-sm leading-6 text-text-secondary">{{ passo.descricao }}</p>
          </article>
        </div>
      </div>
    </section>

    <!-- Prêmio em destaque -->
    <section id="premio" class="py-6">
      <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-topo to-topo-claro px-6 py-10 shadow-xl sm:px-12 sm:py-14">
          <div class="absolute inset-0 bg-[radial-gradient(circle_at_85%_30%,rgba(163,230,53,0.15),transparent_45%)]" />

          <div class="relative max-w-2xl">
            <p class="text-xs font-semibold uppercase tracking-[0.3em] text-lime-300">Prêmio do bolão</p>
            <h2 class="mt-3 text-3xl font-black tracking-[-0.03em] text-white sm:text-4xl">
              Quanto mais cupons na disputa, maior o prêmio final
            </h2>
            <p class="mt-4 text-base leading-7 text-emerald-100/85">
              O valor arrecadado com os cupons forma o prêmio do campeonato. Cada cupom é uma chance independente de chegar ao topo do ranking — e você pode jogar com mais de um.
            </p>
            <button
              v-if="!autenticacao.estaAutenticado"
              type="button"
              class="mt-7 inline-flex items-center justify-center rounded-full bg-lime-400 px-7 py-3.5 text-sm font-black text-topo shadow-xl transition hover:scale-[1.02] hover:brightness-105"
              @click="$emit('abrirModalAuth', 'cadastro')"
            >
              Garantir meu cupom
            </button>
            <RouterLink
              v-else
              to="/painel"
              class="mt-7 inline-flex items-center justify-center rounded-full bg-lime-400 px-7 py-3.5 text-sm font-black text-topo shadow-xl transition hover:scale-[1.02] hover:brightness-105"
            >
              Comprar mais um cupom
            </RouterLink>
          </div>
        </div>
      </div>
    </section>

    <!-- Vantagens -->
    <section id="vantagens" class="py-16 sm:py-20">
      <div class="mx-auto grid max-w-7xl items-center gap-14 px-4 sm:px-6 lg:grid-cols-[0.95fr_1.05fr] lg:px-8">
        <div class="max-w-2xl">
          <p class="text-xs font-semibold uppercase tracking-[0.3em] text-primary">Por que o Cravaê</p>
          <h2 class="mt-3 text-3xl font-black leading-tight tracking-[-0.03em] text-text sm:text-5xl">
            Bolão sério, sem planilha e sem discussão
          </h2>
          <p class="mt-5 max-w-xl text-lg leading-8 text-text-secondary">
            Tudo que costuma dar dor de cabeça em bolão — palpite perdido, pontuação contestada, ranking desatualizado — aqui é resolvido pelo sistema, rodada após rodada.
          </p>

          <ul class="mt-8 space-y-4">
            <li v-for="item in beneficios" :key="item" class="flex items-start gap-3 text-base leading-7 text-text">
              <span class="mt-1 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary-dim text-xs font-black text-primary">
                ✓
              </span>
              <span>{{ item }}</span>
            </li>
          </ul>
        </div>

        <div class="relative flex justify-center lg:justify-end">
          <div class="absolute h-72 w-72 rounded-full bg-primary/10 blur-[120px]" />
          <img :src="featurePhoneAsset" alt="Tela do app no celular" class="relative z-10 w-[20rem] max-w-full drop-shadow-[0_32px_70px_rgba(16,24,40,0.25)] sm:w-[24rem]">
        </div>
      </div>
    </section>

    <!-- Pontuação -->
    <section id="pontuacao" class="py-16 sm:py-20">
      <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="grid gap-10 lg:grid-cols-[0.95fr_1.05fr] lg:items-start">
          <div class="max-w-2xl">
            <p class="text-xs font-semibold uppercase tracking-[0.3em] text-primary">Pontuação</p>
            <h2 class="mt-3 text-3xl font-black tracking-[-0.03em] text-text sm:text-4xl">
              Quanto mais você crava, mais pontua
            </h2>
            <p class="mt-4 text-lg leading-8 text-text-secondary">
              As regras valem para todos e ficam visíveis desde o início. Acertar o resultado vale pontos; acertar o placar exato vale mais; e cravar quem marca os gols rende bônus por cima. Cada acerto vira um evento registrado no seu cupom.
            </p>

            <ul class="mt-8 space-y-4">
              <li v-for="item in pontuacaoItens" :key="item" class="flex items-start gap-3 text-base leading-7 text-text">
                <span class="mt-1 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary-dim text-[11px] font-black text-primary">✓</span>
                <span>{{ item }}</span>
              </li>
            </ul>
          </div>

          <div class="grid gap-4 sm:grid-cols-2">
            <article
              v-for="nivel in niveisPontuacao"
              :key="nivel.nome"
              class="rounded-2xl border border-border bg-bg-card p-6 shadow-sm"
            >
              <p class="text-[11px] font-semibold uppercase tracking-[0.24em]" :class="nivel.bonus ? 'text-gold' : 'text-primary'">
                {{ nivel.bonus ? 'Bônus' : 'Base' }}
              </p>
              <h3 class="mt-3 text-lg font-bold text-text">{{ nivel.nome }}</h3>
              <p class="mt-2 text-sm leading-6 text-text-secondary">{{ nivel.descricao }}</p>
            </article>
          </div>
        </div>
      </div>
    </section>

    <!-- FAQ -->
    <section id="faq" class="py-16 sm:py-20">
      <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
        <div class="text-center">
          <p class="text-xs font-semibold uppercase tracking-[0.3em] text-primary">FAQ</p>
          <h2 class="mt-3 text-3xl font-black tracking-[-0.03em] text-text sm:text-4xl">
            Perguntas frequentes
          </h2>
        </div>

        <div class="mt-12 space-y-4">
          <article
            v-for="(item, indice) in faq"
            :key="item.pergunta"
            class="overflow-hidden rounded-2xl border border-border bg-bg-card shadow-sm"
          >
            <button
              type="button"
              class="flex w-full items-center justify-between gap-5 px-6 py-5 text-left"
              @click="faqAberto = faqAberto === indice ? -1 : indice"
            >
              <span class="text-base font-semibold text-text">{{ item.pergunta }}</span>
              <span class="text-xl text-text-muted">{{ faqAberto === indice ? '-' : '+' }}</span>
            </button>
            <div class="grid transition-[grid-template-rows] duration-300" :class="faqAberto === indice ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]'">
              <div class="overflow-hidden">
                <p class="px-6 pb-6 text-sm leading-7 text-text-secondary">{{ item.resposta }}</p>
              </div>
            </div>
          </article>
        </div>

        <div class="mt-12 flex flex-col items-center justify-center gap-4 sm:flex-row">
          <RouterLink
            v-if="autenticacao.estaAutenticado"
            to="/painel"
            class="inline-flex items-center justify-center rounded-full bg-primary px-7 py-3 text-sm font-black text-white transition hover:bg-primary-hover"
          >
            Ir para meu painel
          </RouterLink>
          <template v-else>
            <button
              type="button"
              class="inline-flex items-center justify-center rounded-full bg-primary px-7 py-3 text-sm font-black text-white transition hover:bg-primary-hover"
              @click="$emit('abrirModalAuth', 'cadastro')"
            >
              Criar minha conta
            </button>
            <button
              type="button"
              class="inline-flex items-center justify-center rounded-full border border-border-light px-7 py-3 text-sm font-semibold text-text-secondary transition hover:border-primary hover:text-primary"
              @click="$emit('abrirModalAuth', 'entrar')"
            >
              Já tenho conta
            </button>
          </template>
        </div>
      </div>
    </section>

    <footer class="border-t border-border bg-bg-card py-12">
      <div class="mx-auto grid max-w-7xl gap-10 px-4 sm:px-6 lg:grid-cols-[1.2fr_0.8fr_0.8fr_0.8fr] lg:px-8">
        <div>
          <div class="flex items-center gap-3">
            <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-lime-400 text-lg font-black text-topo">C</div>
            <div>
              <p class="text-sm font-black tracking-[0.14em] text-text">CRAVAÊ</p>
              <p class="text-[11px] uppercase tracking-[0.3em] text-primary">Bolão Brasileirão</p>
            </div>
          </div>
          <p class="mt-5 max-w-sm text-sm leading-7 text-text-muted">
            O bolão do Brasileirão Série A: crave placares e goleadores, acompanhe o ranking em tempo real e dispute o prêmio até a última rodada.
          </p>
        </div>

        <div>
          <h3 class="text-sm font-bold uppercase tracking-[0.25em] text-text-secondary">Navegação</h3>
          <ul class="mt-5 space-y-3 text-sm text-text-muted">
            <li><a href="#jogos" class="transition hover:text-primary">Jogos</a></li>
            <li><a href="#como-funciona" class="transition hover:text-primary">Como funciona</a></li>
            <li><a href="#pontuacao" class="transition hover:text-primary">Pontuação</a></li>
            <li><a href="#faq" class="transition hover:text-primary">FAQ</a></li>
          </ul>
        </div>

        <div>
          <h3 class="text-sm font-bold uppercase tracking-[0.25em] text-text-secondary">Produto</h3>
          <ul class="mt-5 space-y-3 text-sm text-text-muted">
            <li>Cupom por campeonato</li>
            <li>Palpites por rodada</li>
            <li>Bônus por goleador</li>
            <li>Pagamento via Pix</li>
          </ul>
        </div>

        <div>
          <h3 class="text-sm font-bold uppercase tracking-[0.25em] text-text-secondary">Acesso</h3>
          <div class="mt-5">
            <RouterLink
              v-if="autenticacao.estaAutenticado"
              to="/painel"
              class="inline-flex rounded-full border border-primary/40 px-4 py-2 text-sm font-semibold text-primary transition hover:bg-primary hover:text-white"
            >
              Abrir painel
            </RouterLink>
            <button
              v-else
              type="button"
              class="inline-flex rounded-full bg-primary px-4 py-2 text-sm font-black text-white transition hover:bg-primary-hover"
              @click="$emit('abrirModalAuth', 'cadastro')"
            >
              Criar conta
            </button>
          </div>
        </div>
      </div>

      <div class="mx-auto mt-10 max-w-7xl border-t border-border px-4 pt-6 text-xs text-text-muted sm:px-6 lg:px-8">
        © 2026 Cravaê. Todos os direitos reservados.
      </div>
    </footer>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
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
import { usarAutenticacaoStore } from '../stores/autenticacao'

type EventoSportsDb = {
  idEvent: string
  strHomeTeam: string
  strAwayTeam: string
  strHomeTeamBadge?: string | null
  strAwayTeamBadge?: string | null
  strThumb?: string | null
  strVenue?: string | null
  dateEvent: string
  strTime?: string | null
  intRound?: string | null
  strSeason?: string | null
  intHomeScore?: string | number | null
  intAwayScore?: string | number | null
}

defineEmits<{ abrirModalAuth: [modo: 'entrar' | 'cadastro'] }>()

const SPORTSDB = 'https://www.thesportsdb.com/api/v1/json/123'
const LIGA_BRASILEIRAO = 4351

const autenticacao = usarAutenticacaoStore()
const faqAberto = ref(-1)
const jogosRodada = ref<EventoSportsDb[]>([])
const rodadaAtual = ref<string | null>(null)
const faixaJogosRef = ref<HTMLElement | null>(null)

function rolarFaixa(direcao: number) {
  const faixa = faixaJogosRef.value
  if (!faixa) return
  const larguraCard = faixa.querySelector(':scope > *')?.clientWidth ?? 288
  faixa.scrollBy({ left: direcao * (larguraCard + 16) * 2, behavior: 'smooth' })
}

const estatisticasHero = [
  { label: 'Clubes', value: 20, caption: 'na Série A' },
  { label: 'Rodadas', value: 38, caption: 'de disputa' },
  { label: 'Cupom', value: 'R$ 10', caption: 'por campeonato' },
]

const passos = [
  {
    numero: '01',
    titulo: 'Crie sua conta',
    descricao: 'Cadastro rápido, direto no navegador — sem instalar nada.',
    imagem: step01Asset,
  },
  {
    numero: '02',
    titulo: 'Compre seu cupom',
    descricao: 'Pagamento via Pix. Um cupom vale o campeonato inteiro.',
    imagem: step02Asset,
  },
  {
    numero: '03',
    titulo: 'Crave seus palpites',
    descricao: 'Placar de cada jogo da rodada e quem marca os gols — tudo salvo automaticamente.',
    imagem: step03Asset,
  },
  {
    numero: '04',
    titulo: 'Some pontos',
    descricao: 'A cada resultado do Brasileirão, sua pontuação é recalculada na hora.',
    imagem: step04Asset,
  },
  {
    numero: '05',
    titulo: 'Dispute o prêmio',
    descricao: 'Acompanhe o ranking rodada a rodada e veja quem chega ao topo.',
    imagem: step05Asset,
  },
]

const beneficios = [
  'Palpite completo: placar do jogo e autores dos gols',
  'Bônus para quem crava o goleador — e bônus cheio no placar perfeito',
  'Palpites abertos até o horário de início de cada jogo',
  'Pontuação recalculada automaticamente a cada resultado',
  'Ranking com critérios de desempate claros e consistentes',
  'Pagamento do cupom via Pix, confirmado na hora',
]

const pontuacaoItens = [
  'Regras configuradas por campeonato e visíveis para todos os participantes.',
  'Cada acerto vira um evento de pontuação registrado no seu cupom.',
  'Ranking consolidado com desempate por desempenho.',
  'Fechamento por jogo: deu a bola, travou o palpite.',
]

const niveisPontuacao = [
  { nome: 'Resultado certo', descricao: 'Acertou quem vence (ou o empate), mesmo sem o placar cheio.', bonus: false },
  { nome: 'Placar exato', descricao: 'Cravou o placar completo do jogo — vale mais que o resultado.', bonus: false },
  { nome: 'Goleador certo', descricao: 'Apontou um jogador que marcou no jogo. Bônus por cada acerto.', bonus: true },
  { nome: 'Placar perfeito', descricao: 'Placar exato com todos os autores dos gols. O bônus máximo do Cravaê.', bonus: true },
]

const faq = [
  {
    pergunta: 'Como funciona a compra de cupom?',
    resposta: 'Cada cupom é uma entrada independente para o campeonato, paga via Pix. Você pode comprar mais de um e usar estratégias diferentes em cada conjunto de palpites.',
  },
  {
    pergunta: 'Até quando posso palpitar?',
    resposta: 'Cada jogo fecha no seu horário de início. Deu a bola em um jogo, o palpite daquele jogo trava — os demais da rodada continuam abertos até começarem.',
  },
  {
    pergunta: 'Como funciona o palpite de goleador?',
    resposta: 'Além do placar, você aponta quem faz cada gol do seu palpite, escolhendo do elenco real de cada clube. Acertou um goleador, ganha bônus; cravou o placar com todos os autores, leva o bônus máximo.',
  },
  {
    pergunta: 'O ranking atualiza automaticamente?',
    resposta: 'Sim. Assim que os resultados da rodada entram, a pontuação de todos os cupons é recalculada e o ranking reflete a posição mais recente.',
  },
  {
    pergunta: 'Preciso instalar um aplicativo?',
    resposta: 'Não. A plataforma roda no navegador, no celular e no desktop — e você pode adicioná-la à tela inicial como um app.',
  },
]

function temPlacar(jogo: EventoSportsDb) {
  return jogo.intHomeScore !== null && jogo.intHomeScore !== undefined && jogo.intHomeScore !== ''
}

function formatarDataEvento(jogo: EventoSportsDb) {
  if (!jogo.dateEvent) return ''
  // dateEvent/strTime chegam em UTC na TheSportsDB.
  const data = new Date(`${jogo.dateEvent}T${jogo.strTime || '00:00:00'}Z`)
  if (Number.isNaN(data.getTime())) return jogo.dateEvent
  return data.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' }) +
    ' · ' +
    data.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })
}

onMounted(async () => {
  try {
    const proximos = await fetch(`${SPORTSDB}/eventsnextleague.php?id=${LIGA_BRASILEIRAO}`).then((r) => r.json())
    const primeiro: EventoSportsDb | undefined = proximos?.events?.[0]
    if (!primeiro?.intRound) return

    rodadaAtual.value = primeiro.intRound
    const temporada = primeiro.strSeason || String(new Date().getFullYear())
    const rodada = await fetch(`${SPORTSDB}/eventsround.php?id=${LIGA_BRASILEIRAO}&r=${primeiro.intRound}&s=${temporada}`).then((r) => r.json())
    const eventos: EventoSportsDb[] = rodada?.events ?? []
    jogosRodada.value = eventos.length ? eventos : [primeiro]
  } catch {
    jogosRodada.value = []
  }
})
</script>
