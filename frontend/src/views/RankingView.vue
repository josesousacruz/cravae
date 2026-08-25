<template>
  <RankingConteudo :cupom-destaque="meuCupomStore.cupom?.id ?? null" />
</template>

<script setup lang="ts">
import { onMounted } from 'vue'
import RankingConteudo from '../components/RankingConteudo.vue'
import { usarAutenticacaoStore } from '../stores/autenticacao'
import { usarMeuCupomStore } from '../stores/meuCupom'

const autenticacao = usarAutenticacaoStore()
const meuCupomStore = usarMeuCupomStore()

onMounted(() => {
  if (autenticacao.estaAutenticado) {
    void meuCupomStore.carregar()
  }
})
</script>
