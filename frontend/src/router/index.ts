import { createRouter, createWebHistory } from 'vue-router'

import { usarAutenticacaoStore } from '../stores/autenticacao'
import AdminPainelView from '../views/AdminPainelView.vue'
import CadastroView from '../views/CadastroView.vue'
import CupomView from '../views/CupomView.vue'
import EntrarView from '../views/EntrarView.vue'
import InicioView from '../views/InicioView.vue'
import PainelView from '../views/PainelView.vue'
import RankingView from '../views/RankingView.vue'

export const roteador = createRouter({
  history: createWebHistory(),
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
      name: 'entrar',
      component: EntrarView,
    },
    {
      path: '/cadastro',
      name: 'cadastro',
      component: CadastroView,
    },
    {
      path: '/painel',
      name: 'painel',
      component: PainelView,
      meta: { requerAutenticacao: true },
    },
    {
      path: '/cupons/:id',
      name: 'cupom',
      component: CupomView,
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
    return { name: 'entrar' }
  }

  if (requerAdministrador && !autenticacao.eAdministrador) {
    return { name: 'painel' }
  }

  return true
}
