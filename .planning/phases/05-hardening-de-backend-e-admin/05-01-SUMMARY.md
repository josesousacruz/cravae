# Summary

O recalculo administrativo saiu da request e passou a ser despachado por `RecalcularPontuacaoTorneioJob`. Isso reduziu o acoplamento entre salvar resultado/regra e reprocessar pontuacao, melhorando a latencia da operacao administrativa e alinhando o backend ao requisito de uso de jobs do Laravel.
