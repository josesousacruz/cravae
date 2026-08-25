import { createRouter, createWebHistory } from 'vue-router'

import { usarAutenticacaoStore } from '../stores/autenticacao'
import AdminPainelView from '../views/AdminPainelView.vue'
import InicioView from '../views/InicioView.vue'
import PalpitesView from '../views/PalpitesView.vue'
import PerfilView from '../views/PerfilView.vue'
import RankingView from '../views/RankingView.vue'

export const roteador = createRouter({
  history: createWebHistory(),
  scrollBehavior() {
    return { top: 0 }
  },
  routes: [
    {
      path: '/',
      name: 'inicio',
      component: InicioView,
    },
    {
      path: '/palpites',
      name: 'palpites',
      component: PalpitesView,
      meta: { requerAutenticacao: true },
    },
    {
      path: '/ranking',
      name: 'ranking',
      component: RankingView,
    },
    {
      path: '/resultados',
      name: 'resultados',
      component: () => import('../views/ResultadosView.vue'),
      meta: { requerAutenticacao: true },
    },
    {
      path: '/perfil',
      name: 'perfil',
      component: PerfilView,
      meta: { requerAutenticacao: true },
    },
    {
      path: '/assinatura',
      name: 'assinatura',
      component: () => import('../views/AssinaturaView.vue'),
      meta: { requerAutenticacao: true },
    },
    {
      // Viewer de temporadas anteriores (ex.: Copa 2026): somente leitura.
      path: '/cupons/:id',
      name: 'cupom',
      component: PalpitesView,
      props: (rota) => ({ cupomId: Number(rota.params.id), somenteLeitura: true }),
      meta: { requerAutenticacao: true },
    },
    {
      path: '/admin',
      name: 'admin',
      component: AdminPainelView,
      meta: { requerAutenticacao: true, requerAdministrador: true },
    },
    {
      path: '/entrar',
      redirect: { name: 'inicio', query: { modal: 'entrar' } },
    },
    {
      path: '/cadastro',
      redirect: { name: 'inicio', query: { modal: 'cadastro' } },
    },
    // Compatibilidade com a navegação antiga (fase 09: Bolões e Painel morreram).
    { path: '/boloes', redirect: { name: 'palpites' } },
    { path: '/painel', redirect: { name: 'palpites' } },
    { path: '/checkout', redirect: { name: 'assinatura' } },
    { path: '/:pathMatch(.*)*', redirect: { name: 'inicio' } },
  ],
})

roteador.beforeEach((to) => {
  const autenticacao = usarAutenticacaoStore()

  if (autenticacao.token && autenticacao.nome === 'Visitante') {
    return autenticacao
      .carregarUsuario()
      .then(() => validarRota(to, autenticacao))
  }

  return validarRota(to, autenticacao)
})

function validarRota(
  to: { name?: unknown; query: Record<string, unknown>; meta: { requerAutenticacao?: unknown; requerAdministrador?: unknown } },
  autenticacao: ReturnType<typeof usarAutenticacaoStore>,
) {
  if (to.meta.requerAutenticacao && !autenticacao.estaAutenticado) {
    return { name: 'inicio', query: { modal: 'entrar' } }
  }

  if (to.meta.requerAdministrador && !autenticacao.eAdministrador) {
    return { name: 'palpites' }
  }

  // Autenticado na landing sem modal explícito: direto para o palpite.
  if (to.name === 'inicio' && autenticacao.estaAutenticado && !to.query.modal) {
    return { name: 'palpites' }
  }

  return true
}
