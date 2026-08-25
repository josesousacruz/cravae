<?php

namespace App\Services;

use App\Models\Assinatura;
use App\Models\CicloAssinatura;
use App\Models\Cupom;
use App\Models\Plano;
use App\Models\Torneio;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Modelo de assinatura (fase 08): a assinatura controla a elegibilidade a
 * prêmios por ciclo mensal (competência YYYY-MM); o cupom continua sendo a
 * entrada de palpites e passa a nascer gratuito (1 por usuário por torneio).
 */
class ServicoAssinatura
{
    public function __construct(private readonly ServicoAsaas $servicoAsaas)
    {
    }

    public function contratar(Usuario $usuario, Plano $plano): Assinatura
    {
        return DB::transaction(function () use ($usuario, $plano) {
            $existente = Assinatura::query()
                ->where('usuario_id', $usuario->id)
                ->whereIn('status', [Assinatura::STATUS_ATIVA, Assinatura::STATUS_INADIMPLENTE])
                ->lockForUpdate()
                ->first();

            if ($existente) {
                throw ValidationException::withMessages([
                    'assinatura' => 'Você já possui uma assinatura em vigor.',
                ]);
            }

            // Preço de fundador: 50% vitalício para as primeiras vagas; o lock
            // no plano serializa a disputa pela última vaga.
            $plano = Plano::query()->whereKey($plano->id)->lockForUpdate()->firstOrFail();
            $fundador = $plano->preco_fundador_centavos !== null && $plano->vagasFundadorRestantes() > 0;
            $preco = $fundador ? $plano->preco_fundador_centavos : $plano->preco_centavos;

            $assinatura = Assinatura::query()->create([
                'usuario_id' => $usuario->id,
                'plano_id' => $plano->id,
                'status' => Assinatura::STATUS_ATIVA,
                'fundador' => $fundador,
                'preco_centavos_contratado' => $preco,
                'assinada_em' => now(),
            ]);

            if ($this->asaasHabilitado()) {
                if (! $usuario->asaas_cliente_id) {
                    $cliente = $this->servicoAsaas->criarCliente($usuario);
                    $usuario->forceFill(['asaas_cliente_id' => $cliente['id'] ?? null])->save();
                }

                $resposta = $this->servicoAsaas->criarAssinatura(
                    $usuario,
                    $preco,
                    'Assinatura '.config('app.name'),
                    'assinatura:'.$assinatura->id,
                );

                $assinatura->forceFill(['id_assinatura_asaas' => $resposta['id'] ?? null])->save();
            }

            $assinatura->ciclos()->create([
                'competencia' => $this->competenciaCorrente(),
                'status' => CicloAssinatura::STATUS_PENDENTE,
            ]);

            return $assinatura;
        });
    }

    public function cancelar(Assinatura $assinatura): Assinatura
    {
        if ($assinatura->status === Assinatura::STATUS_CANCELADA) {
            return $assinatura;
        }

        if ($assinatura->id_assinatura_asaas && $this->asaasHabilitado()) {
            $this->servicoAsaas->cancelarAssinatura($assinatura->id_assinatura_asaas);
        }

        // Cancelou: vira gratuito (mantém cupom e palpites) e, se era fundador,
        // perde a condição — a vaga volta para o contador.
        $assinatura->forceFill([
            'status' => Assinatura::STATUS_CANCELADA,
            'cancelada_em' => now(),
        ])->save();

        return $assinatura;
    }

    /**
     * Registra o pagamento de um ciclo (webhook ou confirmação sandbox) e
     * reativa a assinatura caso estivesse inadimplente.
     */
    public function registrarPagamentoCiclo(Assinatura $assinatura, string $competencia, ?string $idCobranca = null): CicloAssinatura
    {
        return DB::transaction(function () use ($assinatura, $competencia, $idCobranca) {
            $ciclo = $assinatura->ciclos()->firstOrCreate(
                ['competencia' => $competencia],
                ['status' => CicloAssinatura::STATUS_PENDENTE],
            );

            $ciclo->forceFill([
                'status' => CicloAssinatura::STATUS_PAGO,
                'pago_em' => now(),
                'id_cobranca_asaas' => $idCobranca ?? $ciclo->id_cobranca_asaas,
            ])->save();

            if ($assinatura->status === Assinatura::STATUS_INADIMPLENTE) {
                $assinatura->forceFill(['status' => Assinatura::STATUS_ATIVA])->save();
            }

            return $ciclo;
        });
    }

    /**
     * Abre os ciclos pendentes da competência corrente e rebaixa a
     * inadimplente quem não pagou o ciclo anterior. Pensado para rodar
     * agendado na virada do mês.
     */
    public function fecharCiclo(?string $competencia = null): array
    {
        $competencia ??= $this->competenciaCorrente();
        $anterior = $this->competenciaAnterior($competencia);
        $abertos = 0;
        $rebaixadas = 0;

        Assinatura::query()
            ->whereIn('status', [Assinatura::STATUS_ATIVA, Assinatura::STATUS_INADIMPLENTE])
            ->each(function (Assinatura $assinatura) use ($competencia, $anterior, &$abertos, &$rebaixadas) {
                $assinatura->ciclos()->firstOrCreate(
                    ['competencia' => $competencia],
                    ['status' => CicloAssinatura::STATUS_PENDENTE],
                );
                $abertos++;

                $cicloAnterior = $assinatura->cicloDaCompetencia($anterior);

                if ($assinatura->status === Assinatura::STATUS_ATIVA
                    && $cicloAnterior
                    && $cicloAnterior->status !== CicloAssinatura::STATUS_PAGO) {
                    $assinatura->forceFill(['status' => Assinatura::STATUS_INADIMPLENTE])->save();
                    $rebaixadas++;
                }
            });

        return ['ciclos_abertos' => $abertos, 'rebaixadas' => $rebaixadas];
    }

    /**
     * Elegibilidade a prêmio: ciclo pago na competência, independente do
     * status atual (quem pagou o mês concorre naquele mês).
     */
    public function usuarioElegivelNaCompetencia(int $usuarioId, ?string $competencia = null): bool
    {
        $competencia ??= $this->competenciaCorrente();

        return CicloAssinatura::query()
            ->where('competencia', $competencia)
            ->where('status', CicloAssinatura::STATUS_PAGO)
            ->whereHas('assinatura', fn ($consulta) => $consulta->where('usuario_id', $usuarioId))
            ->exists();
    }

    /**
     * Ids de usuários elegíveis na competência (para marcar o ranking em lote).
     *
     * @return array<int,int>
     */
    public function usuariosElegiveisNaCompetencia(?string $competencia = null): array
    {
        $competencia ??= $this->competenciaCorrente();

        return CicloAssinatura::query()
            ->where('ciclos_assinatura.competencia', $competencia)
            ->where('ciclos_assinatura.status', CicloAssinatura::STATUS_PAGO)
            ->join('assinaturas', 'assinaturas.id', '=', 'ciclos_assinatura.assinatura_id')
            ->pluck('assinaturas.usuario_id')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Garante o cupom gratuito do usuário no torneio (1 por usuário/torneio).
     */
    public function garantirCupomGratuito(Usuario $usuario, Torneio $torneio): Cupom
    {
        $existente = Cupom::query()
            ->where('usuario_id', $usuario->id)
            ->where('torneio_id', $torneio->id)
            ->first();

        if ($existente) {
            return $existente;
        }

        return Cupom::query()->create([
            'usuario_id' => $usuario->id,
            'torneio_id' => $torneio->id,
            'codigo' => $this->gerarCodigoCupom(),
            'status' => 'ativo',
            'origem' => 'gratuito',
        ]);
    }

    public function competenciaCorrente(): string
    {
        return now()->format('Y-m');
    }

    public function asaasHabilitado(): bool
    {
        return (string) config('services.asaas.access_token') !== '';
    }

    private function competenciaAnterior(string $competencia): string
    {
        return \Carbon\Carbon::createFromFormat('Y-m', $competencia)->subMonthNoOverflow()->format('Y-m');
    }

    private function gerarCodigoCupom(): string
    {
        do {
            $codigo = strtoupper(Str::random(10));
        } while (Cupom::query()->where('codigo', $codigo)->exists());

        return $codigo;
    }
}
