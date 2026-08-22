# Invariantes: Test Harness (Pest)
1. Trava Financeira e Isolamento: NUNCA realize chamadas HTTP reais para APIs externas nos testes. Use SEMPRE Http::fake() carregando os dados estritos das fixtures em tests/Fixtures/.
2. Banco de Dados em Memória: Use a trait RefreshDatabase em todos os testes de feature.
3. Validação de Interface (Inertia): Valide props do Inertia usando asserções formais ($response->assertInertia(...)).
