<?php

use App\Enums\Equipment;
use App\Enums\LoadMode;
use App\Services\ValidLoadService;

function validLoads(): ValidLoadService
{
    return new ValidLoadService;
}

test('halteres: 10 kg sobe para 12 kg, nunca 10,5', function () {
    $next = validLoads()->nextAbove(Equipment::Dumbbells, LoadMode::PerDumbbell, 10);

    expect($next)->toBe(12.0);
    expect(validLoads()->isValid(Equipment::Dumbbells, LoadMode::PerDumbbell, 10, 10.5))->toBeFalse();
});

test('halteres: 8 kg sobe para 9 kg (de 1 em 1 até 10 kg)', function () {
    expect(validLoads()->nextAbove(Equipment::Dumbbells, LoadMode::PerDumbbell, 8))->toBe(9.0);
});

test('halteres: acima de 10 kg só existem pares (12, 14, 16...) e as reduções também respeitam isso', function () {
    $service = validLoads();

    expect($service->nextAbove(Equipment::Dumbbells, LoadMode::PerDumbbell, 12))->toBe(14.0);
    expect($service->nextBelow(Equipment::Dumbbells, LoadMode::PerDumbbell, 14))->toBe(12.0);
    expect($service->nextBelow(Equipment::Dumbbells, LoadMode::PerDumbbell, 12))->toBe(10.0);
    expect($service->nextBelow(Equipment::Dumbbells, LoadMode::PerDumbbell, 10))->toBe(9.0);
    expect($service->nextBelow(Equipment::Dumbbells, LoadMode::PerDumbbell, 1))->toBeNull();
    expect($service->isValid(Equipment::Dumbbells, LoadMode::PerDumbbell, 12, 13))->toBeFalse();
});

test('máquina de placas: 45 kg sobe para 50 kg (de 5 em 5)', function () {
    expect(validLoads()->nextAbove(Equipment::SelectorizedMachine, LoadMode::Total, 45))->toBe(50.0);
    expect(validLoads()->nextBelow(Equipment::SelectorizedMachine, LoadMode::Total, 45))->toBe(40.0);
});

test('barra com anilhas (carga total): 40 kg sobe para 45 kg (um par de 2,5)', function () {
    expect(validLoads()->nextAbove(Equipment::BarbellPlates, LoadMode::Total, 40))->toBe(45.0);
    expect(validLoads()->nextBelow(Equipment::BarbellPlates, LoadMode::Total, 40))->toBe(35.0);
});

test('máquina com anilhas (carga total): 45 kg sobe para 50 kg', function () {
    expect(validLoads()->nextAbove(Equipment::PlateLoadedMachine, LoadMode::Total, 45))->toBe(50.0);
});

test('barra com carga registrada por lado sobe 2,5 kg, sempre a partir da carga atual', function () {
    expect(validLoads()->nextAbove(Equipment::BarbellPlates, LoadMode::PerSide, 40))->toBe(42.5);
    expect(validLoads()->nextAbove(Equipment::BarbellPlates, LoadMode::PerSide, 21.25))->toBe(23.75);
});

test('kettlebell segue a lista fixa de pesos e não passa de 32 kg', function () {
    $service = validLoads();

    expect($service->nextAbove(Equipment::Kettlebell, LoadMode::PerDumbbell, 16))->toBe(20.0);
    expect($service->nextBelow(Equipment::Kettlebell, LoadMode::PerDumbbell, 20))->toBe(16.0);
    expect($service->nextAbove(Equipment::Kettlebell, LoadMode::PerDumbbell, 32))->toBeNull();
});

test('nearbyLoads devolve as cargas válidas vizinhas abaixo e acima', function () {
    expect(validLoads()->nearbyLoads(Equipment::Dumbbells, LoadMode::PerDumbbell, 10))->toBe([
        'below' => [9.0, 8.0, 7.0],
        'above' => [12.0, 14.0, 16.0],
    ]);

    expect(validLoads()->candidateLoads(Equipment::Dumbbells, LoadMode::PerDumbbell, 10))
        ->toBe([7.0, 8.0, 9.0, 10.0, 12.0, 14.0, 16.0]);
});

test('equipamento "outro" não restringe nenhuma carga', function () {
    $service = validLoads();

    expect($service->isRestricted(Equipment::Other))->toBeFalse();
    expect($service->nearbyLoads(Equipment::Other, LoadMode::Total, 40))->toBe(['below' => [], 'above' => []]);
    expect($service->candidateLoads(Equipment::Other, LoadMode::Total, 40))->toBe([]);
    expect($service->roundToValid(Equipment::Other, LoadMode::Total, 40, 42.5))->toBe(42.5);
});

test('roundToValid leva a carga ao valor válido mais próximo e, no empate, fica com o menor', function () {
    $service = validLoads();

    expect($service->roundToValid(Equipment::Dumbbells, LoadMode::PerDumbbell, 10, 10.5))->toBe(10.0);
    expect($service->roundToValid(Equipment::Dumbbells, LoadMode::PerDumbbell, 10, 11))->toBe(10.0);
    expect($service->roundToValid(Equipment::Dumbbells, LoadMode::PerDumbbell, 10, 11.5))->toBe(12.0);
    expect($service->roundToValid(Equipment::Dumbbells, LoadMode::PerDumbbell, 8, 8.5))->toBe(8.0);
    expect($service->roundToValid(Equipment::SelectorizedMachine, LoadMode::Total, 45, 47.5))->toBe(45.0);
    expect($service->roundToValid(Equipment::SelectorizedMachine, LoadMode::Total, 45, 48))->toBe(50.0);
    expect($service->roundToValid(Equipment::BarbellPlates, LoadMode::Total, 40, 42.5))->toBe(40.0);
    expect($service->roundToValid(Equipment::BarbellPlates, LoadMode::Total, 40, 43))->toBe(45.0);
    expect($service->roundToValid(Equipment::Kettlebell, LoadMode::PerDumbbell, 16, 18))->toBe(16.0);
    expect($service->roundToValid(Equipment::Kettlebell, LoadMode::PerDumbbell, 16, 40))->toBe(32.0);
    expect($service->roundToValid(Equipment::Dumbbells, LoadMode::PerDumbbell, 10, 12))->toBe(12.0);
});

test('o passo dos botões + e − segue o equipamento quando o incremento é o padrão', function () {
    $service = validLoads();

    expect($service->stepFrom(Equipment::Dumbbells, LoadMode::PerDumbbell, 10, 1))->toBe(12.0);
    expect($service->stepFrom(Equipment::Dumbbells, LoadMode::PerDumbbell, 8, 1))->toBe(9.0);
    expect($service->stepFrom(Equipment::Dumbbells, LoadMode::PerDumbbell, 12, -1))->toBe(10.0);
    expect($service->stepFrom(Equipment::SelectorizedMachine, LoadMode::Total, 45, 1))->toBe(50.0);
    expect($service->stepFrom(Equipment::SelectorizedMachine, LoadMode::Total, 0, 1))->toBe(5.0);
    expect($service->stepFrom(Equipment::BarbellPlates, LoadMode::Total, 40, 1))->toBe(45.0);
    expect($service->stepFrom(Equipment::Kettlebell, LoadMode::PerDumbbell, 32, 1))->toBe(32.0);
});

test('um incremento configurado pelo usuário tem prioridade sobre o passo do equipamento', function () {
    expect(validLoads()->stepFrom(Equipment::Dumbbells, LoadMode::PerDumbbell, 10, 1, customIncrement: 1))->toBe(11.0);
    expect(validLoads()->stepFrom(Equipment::SelectorizedMachine, LoadMode::Total, 45, -1, customIncrement: 2.5))->toBe(42.5);
});

test('equipamento "outro" mantém o passo padrão de 2,5 kg nos botões + e −', function () {
    expect(validLoads()->stepFrom(Equipment::Other, LoadMode::Total, 40, 1))->toBe(42.5);
    expect(validLoads()->stepFrom(Equipment::Other, LoadMode::Total, 1, -1))->toBe(0.0);
});

test('a forma de registro da carga é deduzida do equipamento', function () {
    expect(Equipment::Dumbbells->loadMode())->toBe(LoadMode::PerDumbbell);
    expect(Equipment::Kettlebell->loadMode())->toBe(LoadMode::PerDumbbell);
    expect(Equipment::BarbellPlates->loadMode())->toBe(LoadMode::Total);
    expect(Equipment::PlateLoadedMachine->loadMode())->toBe(LoadMode::Total);
    expect(Equipment::SelectorizedMachine->loadMode())->toBe(LoadMode::Total);
    expect(Equipment::Other->loadMode())->toBe(LoadMode::Total);
});
