import { createRouter, createWebHistory } from 'vue-router'

import { usarAutenticacaoStore } from '../stores/autenticacao'
import AdminPainelView from '../views/AdminPainelView.vue'
import CupomView from '../views/CupomView.vue'
import InicioView from '../views/InicioView.vue'
import PainelView from '../views/PainelView.vue'
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
      path: '/ranking',
      name: 'ranking',
      component: RankingView,
    },
    {
      path: '/entrar',
      redirect: { name: 'inicio', query: { modal: 'entrar' } },
    },
    {
      path: '/cadastro',
      redirect: { name: 'inicio', query: { modal: 'cadastro' } },
    },
    {
      path: '/painel',
      name: 'painel',
      component: PainelView,
      meta: { requerAutenticacao: true },
    },
    {
      path: '/perfil',
      name: 'perfil',
      component: PerfilView,
      meta: { requerAutenticacao: true },
    },
    {
      path: '/cupons/:id',
      name: 'cupom',
      component: CupomView,
      meta: { requerAutenticacao: true },
    },
    {
      path: '/checkout',
      name: 'checkout',
      component: () => import('../views/CheckoutView.vue'),
      meta: { requerAutenticacao: true },
    },
    {
      path: '/admin',
      name: 'admin',
      component: AdminPainelView,
      meta: { requerAutenticacao: true, requerAdministrador: true },
    },
  ],
})

roteador.beforeEach((to) => {
  const autenticacao = usarAutenticacaoStore()

  if (autenticacao.token && autenticacao.nome === 'Visitante') {
    return autenticacao
      .carregarUsuario()
      .then(() => validarRota(to.meta.requerAutenticacao, to.meta.requerAdministrador, autenticacao))
  }

  return validarRota(to.meta.requerAutenticacao, to.meta.requerAdministrador, autenticacao)
})

function validarRota(
  requerAutenticacao: unknown,
  requerAdministrador: unknown,
  autenticacao: ReturnType<typeof usarAutenticacaoStore>,
) {
  if (requerAutenticacao && !autenticacao.estaAutenticado) {
    return { name: 'inicio', query: { modal: 'entrar' } }
  }

  if (requerAdministrador && !autenticacao.eAdministrador) {
    return { name: 'painel' }
  }

  return true
}
