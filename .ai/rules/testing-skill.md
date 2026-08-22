# Skill: Invariantes de Teste (Pest)

1. **Trava Financeira (CRÍTICO):** NENHUM teste pode bater em APIs externas pagas. Você DEVE usar `Http::fake()` em todo teste de Feature que toque nos Services de IA.
2. **Invariante do `.env`:** O arquivo `phpunit.xml` DEVE ter `<env name="GEMINI_API_KEY" value="" force="true"/>`. Se o teste tentar ler a chave real, ele deve falhar. 
3. **SQLite Foreign Keys:** Lembre-se que o SQLite em testes de memória tem comportamentos específicos com Foreign Keys. Use sempre `RefreshDatabase`.
4. **Asserts do Inertia:** Para testes de interface, não teste strings HTML. Use `$response->assertInertia(fn (Assert $page) => $page->component('NomeDaTela')->has('propNome'))`.