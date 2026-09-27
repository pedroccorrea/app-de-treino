<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Trava financeira (.ai/rules/testing-harness.md): nenhum teste pode
        // chegar a uma API real. Qualquer request HTTP sem Http::fake()
        // correspondente lança exceção em vez de sair para a rede — o que
        // também cobre chamadas de IA disparadas indiretamente (ex: a
        // classificação de equipamento que roda após a resposta).
        Http::preventStrayRequests();
    }
}
