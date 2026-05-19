# Context

A auditoria do milestone apontou dois problemas centrais no fluxo de palpites: o frontend mantinha uma fila implicita de saves concorrentes e reconstruia regras de desbloqueio/podio localmente. Isso gerava necessidade de refresh manual para enxergar a proxima fase e deixava a `CupomView` mais acoplada ao dominio do que o aceitavel.

Objetivo da fase:
- serializar o autosave no frontend sem perder ack do backend
- consumir o estado derivado do cupom pela API
- remover o refresh manual para destravar fases e atualizar o resumo do bracket
