<?php

namespace App\Http\Requests;

use App\Models\Jogo;
use App\Services\ServicoResultadosTorneio;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SalvarResultadoJogoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'placar_mandante' => ['required', 'integer', 'min:0'],
            'placar_visitante' => ['required', 'integer', 'min:0'],
            'selecao_classificada_id' => ['nullable', 'integer', 'exists:selecoes,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Jogo|null $jogo */
            $jogo = $this->route('jogo');

            if (! $jogo) {
                return;
            }

            $jogo->loadMissing('fase', 'torneio');

            if ($jogo->fase?->tipo === 'grupos') {
                return;
            }

            $selecionada = $this->input('selecao_classificada_id');
            $servicoResultadosTorneio = app(ServicoResultadosTorneio::class);
            $participantes = $servicoResultadosTorneio->participantesDoJogo($jogo);
            $participantesIds = collect([$participantes['mandante']?->id, $participantes['visitante']?->id])
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            if (! $participantesIds) {
                $validator->errors()->add('selecao_classificada_id', 'Os participantes deste confronto ainda nao estao definidos.');

                return;
            }

            if ($selecionada !== null && ! in_array((int) $selecionada, $participantesIds, true)) {
                $validator->errors()->add('selecao_classificada_id', 'A selecao classificada precisa pertencer ao confronto informado.');
            }
        });
    }
}
