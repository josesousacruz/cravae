# Summary

O admin deixou de aceitar classificados invalidos em confrontos eliminatorios. O backend agora valida o classificado contra os participantes reais do jogo, o painel administrativo mostra apenas opcoes validas e o `ServicoBracketCupom` passou a usar contexto do torneio carregado, sem depender de resolucoes globais por `slug`.
