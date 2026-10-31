# Cravaê — Fantasy do Brasileirão

Fantasy de palpites do **Campeonato Brasileiro Série A**. O usuário compra um cupom por campeonato via **PIX** e, rodada a rodada, crava o placar de cada jogo e o autor de cada gol, escolhido do elenco real. Resultados e gols são sincronizados automaticamente, e a pontuação é configurável em banco.

O Cravaê nasceu do **bolão da Copa do Mundo 2026** (fase de grupos e mata-mata), que continua como base do projeto: checkout, cupons, ranking e pontuação vêm dele.

![PHP](https://img.shields.io/badge/PHP-8.3-777BB4?logo=php&logoColor=white)
![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white)
![Vue](https://img.shields.io/badge/Vue-3-4FC08D?logo=vuedotjs&logoColor=white)
![Tests](https://img.shields.io/badge/testes-30%20automatizados-brightgreen)

## Destaques técnicos

- **Pagamentos PIX** — integração completa com **Asaas**: checkout, cobrança e confirmação assíncrona via **webhooks** (com log de eventos `EventoWebhookAsaas` para idempotência e auditoria)
- **Sincronização de resultados** — integração com a API **TheSportsDB**, com job de recálculo de pontuação (`RecalcularPontuacaoTorneioJob`) em fila
- **Pontuação dinâmica** — regras de pontuação configuráveis por torneio (`RegraPontuacao`), sem hardcode
- **Mata-mata com bracket resolvível** — confrontos referenciam vencedores de fases anteriores e se resolvem automaticamente
- **Transações ACID** — apostas processadas com `DB::transaction` garantindo consistência entre cupom, palpites e pagamento
- **Auditoria** — trilha completa de alterações de palpites (`LogAposta`)
- **Rate limiting** — proteção de endpoints sensíveis
- **30 testes automatizados** (Feature + Unit) cobrindo apostas, pontuação e checkout

## Stack

| Camada | Tecnologias |
|---|---|
| Back-end | PHP 8.3, Laravel, Sanctum, Jobs/Queues |
| Front-end | Vue 3, TypeScript, Pinia, Vite, Tailwind CSS |
| Dados | MySQL (22 migrations, 18 models) |
| Integrações | Asaas (PIX + webhooks), TheSportsDB |
| Qualidade | PHPUnit, arquitetura em Services (11 serviços de domínio) |

## Arquitetura

```
backend/app/
├── Services/
│   ├── ServicoApostas.php      # regras de palpite + transações
│   ├── ServicoPontuacao.php    # cálculo por regra configurável
│   ├── ServicoMataMata.php     # resolução de bracket
│   ├── ServicoCheckout.php     # fluxo de compra de cupom
│   └── ServicoAsaas.php        # PIX + webhooks
├── Jobs/
│   └── RecalcularPontuacaoTorneioJob.php
└── Models/                     # Torneio, Fase, Rodada, Jogo, Cupom, Aposta...
```

## Rodando localmente

```bash
git clone https://github.com/cruztechdevelopers/bolaoInter.git
cd bolaoInter/backend
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve & php artisan queue:listen
# front-end
cd ../frontend && npm install && npm run dev
```

## Testes

```bash
cd backend && php artisan test   # 30 testes (Feature + Unit)
```

---

Desenvolvido por [José Almir Sousa Cruz Filho](https://github.com/josesousacruz) com fluxo **AI-First** (Claude Code + Specification-Driven Development).
