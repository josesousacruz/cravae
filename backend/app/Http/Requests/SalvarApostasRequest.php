<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SalvarApostasRequest extends FormRequest
{
    private const TIPOS_SUPORTADOS = [
        'placar_jogo_grupos',
        'placar_jogo_eliminatoria',
        'artilheiro',
        'podio',
    ];

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
            'apostas' => ['required', 'array', 'min:1'],
            'apostas.*.tipo' => ['required', 'string', 'in:'.implode(',', self::TIPOS_SUPORTADOS)],
            'apostas.*.torneio_id' => ['nullable', 'integer', 'exists:torneios,id'],
            'apostas.*.jogo_id' => ['nullable', 'integer', 'exists:jogos,id'],
            'apostas.*.grupo_id' => ['nullable', 'integer', 'exists:grupos,id'],
            'apostas.*.jogador_id' => ['nullable', 'integer', 'exists:jogadores,id'],
            'apostas.*.selecao_id' => ['nullable', 'integer', 'exists:selecoes,id'],
            'apostas.*.campeao_selecao_id' => ['nullable', 'integer', 'exists:selecoes,id'],
            'apostas.*.vice_selecao_id' => ['nullable', 'integer', 'exists:selecoes,id'],
            'apostas.*.terceiro_selecao_id' => ['nullable', 'integer', 'exists:selecoes,id'],
            'apostas.*.placar_mandante' => ['nullable', 'integer', 'min:0'],
            'apostas.*.placar_visitante' => ['nullable', 'integer', 'min:0'],
            'apostas.*.penal_mandante' => ['nullable', 'integer', 'min:0'],
            'apostas.*.penal_visitante' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $apostas = $this->input('apostas', []);

                foreach ($apostas as $indice => $aposta) {
                    $tipo = $aposta['tipo'] ?? null;

                    if (! is_string($tipo)) {
                        continue;
                    }

                    match ($tipo) {
                        'placar_jogo_grupos' => $this->validarPlacarJogo($validator, $indice, $aposta, false),
                        'placar_jogo_eliminatoria' => $this->validarPlacarJogo($validator, $indice, $aposta, true),
                        'artilheiro' => $this->validarArtilheiro($validator, $indice, $aposta),
                        'podio' => $this->validarPodio($validator, $indice, $aposta),
                        default => null,
                    };
                }
            },
        ];
    }

    /**
     * @param array<string, mixed> $aposta
     */
    private function validarPlacarJogo(Validator $validator, int $indice, array $aposta, bool $eliminatoria): void
    {
        if (! isset($aposta['jogo_id'])) {
            $validator->errors()->add("apostas.$indice.jogo_id", 'Jogo e obrigatorio para este tipo de aposta.');
        }

        if (! array_key_exists('placar_mandante', $aposta) || ! array_key_exists('placar_visitante', $aposta)) {
            $validator->errors()->add("apostas.$indice.placar", 'Placares mandante e visitante sao obrigatorios.');
        }

        if (! $eliminatoria) {
            return;
        }

        $placarMandante = $aposta['placar_mandante'] ?? null;
        $placarVisitante = $aposta['placar_visitante'] ?? null;

        if (! is_numeric($placarMandante) || ! is_numeric($placarVisitante)) {
            return;
        }

        if ((int) $placarMandante !== (int) $placarVisitante) {
            return;
        }

        if (! array_key_exists('penal_mandante', $aposta) || ! array_key_exists('penal_visitante', $aposta)) {
            $validator->errors()->add("apostas.$indice.penalidades", 'Empates no mata-mata exigem placar de penaltis.');
            return;
        }

        if ((int) $aposta['penal_mandante'] === (int) $aposta['penal_visitante']) {
            $validator->errors()->add("apostas.$indice.penal_visitante", 'Penaltis nao podem terminar empatados.');
        }
    }

    /**
     * @param array<string, mixed> $aposta
     */
    private function validarArtilheiro(Validator $validator, int $indice, array $aposta): void
    {
        foreach (['torneio_id', 'jogador_id'] as $campo) {
            if (! isset($aposta[$campo])) {
                $validator->errors()->add("apostas.$indice.$campo", 'Campo obrigatorio para artilheiro.');
            }
        }
    }

    /**
     * @param array<string, mixed> $aposta
     */
    private function validarPodio(Validator $validator, int $indice, array $aposta): void
    {
        foreach (['torneio_id', 'campeao_selecao_id', 'vice_selecao_id', 'terceiro_selecao_id'] as $campo) {
            if (! isset($aposta[$campo])) {
                $validator->errors()->add("apostas.$indice.$campo", 'Campo obrigatorio para o palpite de podio.');
            }
        }

        $ids = array_filter([
            $aposta['campeao_selecao_id'] ?? null,
            $aposta['vice_selecao_id'] ?? null,
            $aposta['terceiro_selecao_id'] ?? null,
        ], fn ($id) => $id !== null);

        if (count($ids) === 3 && count(array_unique($ids)) !== 3) {
            $validator->errors()->add("apostas.$indice.podio", 'Campeao, vice e terceiro devem ser selecoes diferentes.');
        }
    }
}
