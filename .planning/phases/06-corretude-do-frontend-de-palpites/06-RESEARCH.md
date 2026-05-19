# Research

- O backend ja expunha `GET /cupons/{cupom}/bracket`, mas o frontend nao refazia a consulta apos salvar.
- O `POST /cupons/{cupom}/apostas/lote` precisa continuar sincrono para garantir validacao de prazo, ownership e erros de negocio.
- A abordagem escolhida foi `single-flight`: um save por vez, com novo disparo apenas se houver edicao durante o envio.
