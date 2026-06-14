import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { requisicaoApi } from '../services/api'
import type { PerfilUsuario, UsuarioAutenticado } from '../tipos'

type RespostaAutenticacao = {
  token: string
  usuario: UsuarioAutenticado
}

export const usarAutenticacaoStore = defineStore('autenticacao', () => {
  const token = ref<string | null>(localStorage.getItem('token_acesso'))
  const nome = ref('Visitante')
  const email = ref('')
  const telefone = ref<string | null>(null)
  const cpfCnpj = ref<string | null>(null)
  const perfil = ref<PerfilUsuario>('visitante')
  const carregando = ref(false)
  const erro = ref<string | null>(null)

  const estaAutenticado = computed(() => Boolean(token.value))
  const eAdministrador = computed(() => perfil.value === 'administrador')

  function aplicarUsuario(usuario: UsuarioAutenticado) {
    nome.value = usuario.nome
    email.value = usuario.email
    telefone.value = usuario.telefone ?? null
    cpfCnpj.value = usuario.cpf_cnpj ?? null
    perfil.value = usuario.perfil
  }

  function definirToken(novoToken: string | null) {
    token.value = novoToken

    if (novoToken) {
      localStorage.setItem('token_acesso', novoToken)
      return
    }

    localStorage.removeItem('token_acesso')
  }

  async function entrar(emailUsuario: string, senha: string) {
    carregando.value = true
    erro.value = null

    try {
      const resposta = await requisicaoApi<RespostaAutenticacao>('/entrar', {
        metodo: 'POST',
        corpo: {
          email: emailUsuario,
          password: senha,
        },
      })

      definirToken(resposta.token)
      aplicarUsuario(resposta.usuario)
    } catch (error) {
      erro.value = error instanceof Error ? error.message : 'Nao foi possivel entrar.'
      throw error
    } finally {
      carregando.value = false
    }
  }

  async function cadastrar(nomeUsuario: string, emailUsuario: string, telefoneUsuario: string, cpfCnpjUsuario: string, senha: string, confirmarSenha: string) {
    carregando.value = true
    erro.value = null

    try {
      const resposta = await requisicaoApi<RespostaAutenticacao>('/cadastro', {
        metodo: 'POST',
        corpo: {
          nome: nomeUsuario,
          email: emailUsuario,
          telefone: telefoneUsuario,
          cpf_cnpj: cpfCnpjUsuario,
          password: senha,
          password_confirmation: confirmarSenha,
        },
      })

      definirToken(resposta.token)
      aplicarUsuario(resposta.usuario)
    } catch (error) {
      erro.value = error instanceof Error ? error.message : 'Nao foi possivel cadastrar.'
      throw error
    } finally {
      carregando.value = false
    }
  }

  async function carregarUsuario() {
    if (!token.value) {
      return
    }

    try {
      const resposta = await requisicaoApi<{ usuario: UsuarioAutenticado }>('/usuario', {
        token: token.value,
      })

      aplicarUsuario(resposta.usuario)
    } catch {
      await sair()
    }
  }

  async function atualizarPerfil(dados: { nome: string; telefone: string; cpf_cnpj: string }) {
    carregando.value = true
    erro.value = null

    try {
      const resposta = await requisicaoApi<{ usuario: UsuarioAutenticado }>('/usuario', {
        metodo: 'PUT',
        corpo: dados,
        token: token.value,
      })

      aplicarUsuario(resposta.usuario)
    } catch (error) {
      erro.value = error instanceof Error ? error.message : 'Nao foi possivel atualizar o perfil.'
      throw error
    } finally {
      carregando.value = false
    }
  }

  async function sair() {
    if (token.value) {
      try {
        await requisicaoApi('/sair', {
          metodo: 'POST',
          corpo: {},
          token: token.value,
        })
      } catch {
        // noop
      }
    }

    definirToken(null)
    perfil.value = 'visitante'
    nome.value = 'Visitante'
    email.value = ''
    telefone.value = null
    cpfCnpj.value = null
  }

  return {
    token,
    nome,
    email,
    telefone,
    cpfCnpj,
    perfil,
    estaAutenticado,
    eAdministrador,
    carregando,
    erro,
    carregarUsuario,
    atualizarPerfil,
    cadastrar,
    entrar,
    sair,
  }
})
