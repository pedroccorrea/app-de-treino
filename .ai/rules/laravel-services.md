# Invariantes: Backend Laravel & Services
## 1. Garantia Transacional Incondicional
Toda mutação que envolva mais de uma tabela DEVE ser envelopada em DB::transaction(function () use ($user, $data) { ... }).
## 2. Controllers Magros e DTOs
- Controllers NUNCA executam loops para formatar dados de banco em JSON.
- A transformação de dados para props do Inertia deve ser feita via DTOs, API Resources (JsonResource) ou métodos privados de formatação no Controller.
