# Admin define o prazo de fechamento do pódio

**Data:** 2026-06-18
**Status:** Aprovado (design)

## Problema

O fechamento do palpite de pódio (campeão/vice/3º) é calculado automaticamente em
`ServicoFechamentoApostas`: 1h antes do primeiro jogo do mata-mata, com fallback para
1h antes do início do torneio. O admin não tem como ajustar esse prazo — por exemplo,
para abrir/fechar o pódio em uma data específica, independente do calendário dos jogos.

## Objetivo

Permitir que o admin defina manualmente **quando o pódio fecha**, mantendo o cálculo
automático atual como padrão quando nenhum prazo manual estiver definido.

## Decisão-chave

**Override opcional com fallback automático** (escolhido pelo usuário):
- Campo vazio (`null`) → comportamento automático atual (1h antes do 1º mata-mata;
  fallback 1h antes do início do torneio).
- Campo preenchido → usa exatamente essa data/hora como prazo de fechamento.

Segue o mesmo padrão do override já existente `rodada.data_fechamento` (que sobrepõe o
fechamento por dia dos jogos de grupos).

## Modelo de dados

- **Migration**: adicionar coluna `data_fechamento_podio` (`nullable`, `datetime`) à
  tabela `torneios`, posicionada após `data_fim`.
- **Model `Torneio`** (`app/Models/Torneio.php`):
  - incluir `'data_fechamento_podio'` no `$fillable`;
  - incluir `'data_fechamento_podio' => 'datetime'` no `casts()`.

## Backend

### Regra de fechamento
`ServicoFechamentoApostas::resolverDataFechamento`, ramo `tipo === 'podio'`:
```php
if ($tipo === 'podio') {
    if ($torneio->data_fechamento_podio) {
        return $torneio->data_fechamento_podio;
    }
    // ... lógica automática atual (min mata-mata - 1h; fallback data_inicio - 1h) ...
}
```
Nenhuma outra alteração de regra. O artilheiro permanece inalterado (fora de escopo).

### Endpoint admin
Novo endpoint espelhando `atualizarComprasAbertas`:
- Rota: `PUT /admin/torneios/{torneio}/fechamento-podio`
  (dentro do grupo `can:acessar-area-admin`, em `routes/api.php`).
- Método: `PainelAdministradorController::atualizarFechamentoPodio`.
- Form Request dedicado (`AtualizarFechamentoPodioRequest`):
  - `data_fechamento_podio` → `['nullable', 'date']`.
  - `authorize()` retorna `true` (autorização já feita pelo middleware da rota).
- Comportamento: `forceFill(['data_fechamento_podio' => $request->date('data_fechamento_podio')])->save()`.
  Enviar `null`/ausente limpa o campo (volta ao automático).
- Resposta: `['torneio' => $torneio->fresh()]` (igual a `atualizarComprasAbertas`).

### Serialização
`data_fechamento_podio` é coluna do model, então já é incluída automaticamente nas
respostas existentes: `/admin/dados`, `/api/torneio` e `/api/torneios/{id}`. Nenhuma
mudança nos resources/serializers necessária.

## Frontend — Admin (`AdminPainelView.vue`)

No card de configuração do torneio (junto ao toggle "Compra de cupons", ~linha 27),
adicionar um bloco "Fechamento do pódio":
- input `<input type="datetime-local">` vinculado ao valor local derivado de
  `torneio.data_fechamento_podio`;
- botão **Salvar** (chama o novo endpoint) e botão **Limpar** (envia `null`, voltando ao
  automático);
- texto auxiliar: quando o campo está vazio, exibir o prazo automático calculado
  (mesma lógica do cupom) para orientar o admin; quando preenchido, deixar claro que o
  valor manual está em uso.
- estados de carregando/erro/mensagem reaproveitando o padrão de `alternarCompras`.

### Tratamento de timezone (importante)
App roda em **UTC**; a API retorna ISO 8601 em UTC (sufixo `Z`); o frontend exibe via
`new Date(iso).toLocaleString` (horário local do navegador). O input `datetime-local`
opera em horário **local sem timezone**. Portanto:
- **Popular o input**: converter o ISO UTC para string local `YYYY-MM-DDTHH:mm`
  (via `Date` + componentes locais).
- **Enviar ao backend**: interpretar o valor do input como horário local, converter para
  UTC com `new Date(valorLocal).toISOString()` antes do PUT.

Helper utilitário pequeno para esse round-trip (local↔UTC), já que é o primeiro
`datetime-local` do projeto.

## Frontend — Cupom (`CupomView.vue`)

`prazoPodioMs` (computed) passa a considerar o override:
```ts
const prazoPodioMs = computed<number | null>(() => {
  if (torneio.value?.data_fechamento_podio) {
    return new Date(torneio.value.data_fechamento_podio).getTime()
  }
  // ... lógica atual (min mata-mata - 1h; fallback data_inicio - 1h) ...
})
```
Como `podioFechado`, o banner do pódio e a contagem regressiva já derivam de
`prazoPodioMs`, o override **propaga automaticamente** para toda a UI existente
(incluindo o contador "Fecha em ..." e o estado "Fechado").

## Tipos (`tipos.ts`)

Adicionar ao tipo `Torneio` (linha ~174):
```ts
data_fechamento_podio: string | null
```
O tipo `Bolao` (listagem leve) não precisa do campo.

## Testes

### Backend (`tests/Feature/FechamentoApostasTest.php`)
- Com `data_fechamento_podio` definido: `prazoEncerrado` para aposta `podio` respeita o
  override (fecha exatamente no horário definido), ignorando o cálculo automático.
- Sem `data_fechamento_podio` (`null`): mantém o comportamento automático atual.

### Backend (endpoint admin)
- `PUT /admin/torneios/{torneio}/fechamento-podio` com data válida grava o campo.
- Enviar `null` limpa o campo (volta ao automático).
- Requer permissão de admin (middleware já cobre).

## Fora de escopo (YAGNI)

- **Artilheiro**: continua fechando 1h antes do início do torneio. Extensão futura
  seguiria o mesmo padrão, se desejado.
- Override por cupom/bolão individual: não aplicável (pódio é por torneio).

## Arquivos afetados

- `backend/database/migrations/<nova>_add_data_fechamento_podio_to_torneios.php`
- `backend/app/Models/Torneio.php`
- `backend/app/Services/ServicoFechamentoApostas.php`
- `backend/app/Http/Requests/AtualizarFechamentoPodioRequest.php` (novo)
- `backend/app/Http/Controllers/PainelAdministradorController.php`
- `backend/routes/api.php`
- `backend/tests/Feature/FechamentoApostasTest.php`
- `frontend/src/views/AdminPainelView.vue`
- `frontend/src/views/CupomView.vue`
- `frontend/src/tipos.ts`
