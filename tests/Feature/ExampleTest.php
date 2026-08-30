<?php

use App\Models\User;

it('redireciona visitante da rota raiz para o login', function () {
    $response = $this->get('/');

    $response->assertRedirect('/login');
});

it('redireciona usuário autenticado da rota raiz para o dashboard', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/');

    $response->assertRedirect('/dashboard');
});
