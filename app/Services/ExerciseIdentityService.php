<?php

namespace App\Services;

use App\Models\Exercise;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Decide quando dois registros de exercício são, na prática, o MESMO
 * exercício. O histórico (set_logs) é sempre ligado ao exercise_id, nunca à
 * ficha ou ao programa; mas o usuário pode ter o mesmo exercício salvo com
 * grafias diferentes ("Supino c/ Halteres" x "supino com halteres"), por
 * exemplo depois de escanear uma ficha nova. Este serviço reconhece essas
 * grafias equivalentes SÓ NA LEITURA: nenhum nome salvo é alterado e nenhum
 * registro é mesclado.
 */
class ExerciseIdentityService
{
    /**
     * Abreviações comuns em fichas de papel, aplicadas depois de tirar
     * acentos e maiúsculas.
     *
     * @var array<string, string>
     */
    private const ABBREVIATIONS = [
        '/\bc\s*\/\s*/' => 'com ',
        '/\bs\s*\/\s*/' => 'sem ',
        '/\bp\s*\/\s*/' => 'para ',
        '/\bmaq\b\.?/' => 'maquina',
    ];

    /**
     * Chave de comparação: sem acentos, minúsculas, abreviações expandidas,
     * pontuação trocada por espaço e espaços repetidos colapsados.
     */
    public function normalize(string $name): string
    {
        $normalized = Str::of($name)->ascii()->lower()->value();

        foreach (self::ABBREVIATIONS as $pattern => $replacement) {
            $normalized = (string) preg_replace($pattern, $replacement, $normalized);
        }

        $normalized = (string) preg_replace('/[^a-z0-9]+/', ' ', $normalized);

        return Str::squish($normalized);
    }

    public function sameExercise(string $a, string $b): bool
    {
        return $this->normalize($a) === $this->normalize($b);
    }

    /**
     * Para cada exercise_id informado, a lista de ids visíveis ao usuário que
     * representam o mesmo exercício (ele próprio primeiro). Um id que também
     * foi informado nunca é "emprestado" como equivalente de outro — cada um
     * continua dono do próprio histórico.
     *
     * @param  array<int, int>  $exerciseIds
     * @return array<int, list<int>>
     */
    public function equivalentIds(User $user, array $exerciseIds): array
    {
        $exerciseIds = array_values(array_unique(array_map('intval', $exerciseIds)));

        if ($exerciseIds === []) {
            return [];
        }

        $byKey = Exercise::query()
            ->forUser($user)
            ->get(['id', 'name'])
            ->groupBy(fn (Exercise $exercise) => $this->normalize($exercise->name));

        $names = Exercise::query()->whereKey($exerciseIds)->pluck('name', 'id');
        $result = [];

        foreach ($exerciseIds as $id) {
            $name = $names->get($id);
            $aliases = $name === null
                ? []
                : $byKey->get($this->normalize($name), collect())
                    ->pluck('id')
                    ->reject(fn (int $aliasId) => in_array($aliasId, $exerciseIds, true))
                    ->values()
                    ->all();

            $result[$id] = [$id, ...$aliases];
        }

        return $result;
    }
}
