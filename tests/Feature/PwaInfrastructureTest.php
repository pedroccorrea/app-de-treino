<?php

test('manifest.json is publicly accessible with the correct content-type', function () {
    $response = $this->get('/manifest.json');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/manifest+json');

    $manifest = json_decode($response->getContent(), true);

    expect($manifest['name'])->toBe('Setwave — Treino e Progressão');
    expect($manifest['short_name'])->toBe('Setwave');
    expect($manifest['display'])->toBe('standalone');
    expect($manifest['background_color'])->toBe('#0B0F17');
    expect($manifest['theme_color'])->toBe('#8B5CF6');
    expect($manifest['orientation'])->toBe('portrait-primary');

    expect($manifest['icons'])->toBeArray()->not->toBeEmpty();
    expect(collect($manifest['icons'])->pluck('sizes')->all())
        ->toContain('192x192')
        ->toContain('512x512');
});

test('sw.js is publicly accessible with the correct content-type', function () {
    $response = $this->get('/sw.js');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/javascript');
    expect($response->getContent())->toContain("addEventListener('install'");
});

test('sw.js is served with cache-control headers that prevent a stale script from getting stuck (Phase 38.1)', function () {
    $response = $this->get('/sw.js');

    // Sem isso, qualquer camada de cache HTTP no caminho (um proxy, uma CDN,
    // ou o próprio navegador em alguma configuração) pode continuar servindo
    // bytes antigos deste arquivo — e nenhuma correção aqui dentro chega a
    // ser instalada de verdade, por mais correta que esteja. O Symfony
    // reordena e completa a diretiva (adiciona `private`) ao normalizar o
    // header — o que importa é que as três diretivas anti-cache estejam lá.
    $cacheControl = $response->headers->get('Cache-Control');
    expect($cacheControl)->toContain('no-cache');
    expect($cacheControl)->toContain('no-store');
    expect($cacheControl)->toContain('must-revalidate');
});

test('sw.js intercepts Inertia requests and caches build assets for offline use', function () {
    $response = $this->get('/sw.js');

    $content = $response->getContent();

    // Requisições do Inertia devem ser reconhecidas pelo cabeçalho X-Inertia
    // e ter um fallback de cache quando a rede falhar.
    expect($content)->toContain("request.headers.get('X-Inertia')");
    expect($content)->toContain('networkFirst');

    // Pacotes compilados pelo Vite (nome do chunk muda a cada build) usam
    // stale-while-revalidate: a resposta em cache é servida imediatamente e a
    // rede é consultada em paralelo apenas para atualizar o cache.
    expect($content)->toContain('/build/assets/');
    expect($content)->toContain('staleWhileRevalidate');

    // Navegação de documento completo (F5 sem internet) cai para o app shell
    // em cache em vez do erro nativo de conexão do navegador.
    expect($content)->toContain("request.mode === 'navigate'");
});

test('sw.js ignores cross-origin requests (browser extensions) and never lets an asset fetch reject uncaught', function () {
    $response = $this->get('/sw.js');

    $content = $response->getContent();

    // Requisições de outra origem (ex: `chrome-extension://`) não podem cair
    // nas estratégias de cache do app — o listener de fetch precisa devolver
    // cedo, deixando o navegador tratá-las normalmente.
    expect($content)->toContain('requestUrl.origin !== self.location.origin');

    // A revalidação em segundo plano do stale-while-revalidate nunca pode
    // deixar uma rejeição de rede escapar sem tratamento (isso é o que gerava
    // `ERR_INTERNET_DISCONNECTED` no console ao ficar offline).
    expect($content)->toContain('.catch(() => null)');
});

test('sw.js implements the offline mini-server (Phase 33): synthesizes Inertia pages and queues mutations from IndexedDB', function () {
    $response = $this->get('/sw.js');

    $content = $response->getContent();

    // O banco/stores do mini-servidor precisam ser idênticos aos definidos
    // em `resources/js/Utils/offlineStorage.js`, já que os dois mantêm
    // cópias independentes da mesma camada de acesso ao IndexedDB.
    expect($content)->toContain("const DB_NAME = 'ia-lift-offline'");
    expect($content)->toContain("const STORE_PROGRAMS = 'cached_programs'");

    // Navegação/Inertia GET para as telas offline-first monta o JSON no
    // formato esperado pelo Inertia a partir do IndexedDB, sem bater na rede.
    expect($content)->toContain('Dashboard');
    expect($content)->toContain("'Workouts/Index'");
    expect($content)->toContain("'Programs/Index'");
    expect($content)->toContain("'Programs/Show'");
    expect($content)->toContain('buildOfflinePageResponse');

    // Mutações offline (criar/editar/excluir programa ou treino) são
    // enfileiradas na sync_queue e aplicadas otimisticamente, com um
    // redirecionamento 303 forjado para o Inertia seguir sem erro.
    expect($content)->toContain('MUTATION_ROUTES');
    expect($content)->toContain('handleOfflineMutation');
    expect($content)->toContain('enqueueSyncAction');
    expect($content)->toContain('status: 303');
});

test('sw.js synthesizes the dedicated workout create/edit pages offline (Phase 38.1)', function () {
    $response = $this->get('/sw.js');

    $content = $response->getContent();

    // Sem estas duas rotas no mini-servidor, `/workouts/create` e
    // `/workouts/{id}/edit` caíam no JSON de fallback (ou na página cacheada
    // errada) offline — tornando impossível criar ou editar uma ficha sem
    // internet, mesmo sendo uma operação puramente local.
    expect($content)->toContain("'Workouts/Create'");
    expect($content)->toContain('buildWorkoutCreateProps');
    expect($content)->toContain("'Workouts/Edit'");
    expect($content)->toContain('buildWorkoutEditProps');
});

test('the page warns in the console when the service worker registers while Vite dev/HMR is serving pages (Phase 38.5)', function () {
    // `public/hot` só existe enquanto `npm run dev` está rodando — é o
    // sinalizador que faz `@vite(...)` apontar para o servidor do Vite (uma
    // origem separada) em vez dos arquivos compilados em `public/build`.
    // Nesse modo, o Service Worker (registrado para a origem do Laravel)
    // nunca consegue interceptar o carregamento de uma página Vue ainda não
    // visitada nesta sessão — offline, isso trava para sempre, mesmo com os
    // dados corretos por trás (IndexedDB, fila de sincronização). Não dá
    // para distinguir isso de "a mutação não funcionou" sem este aviso.
    $hotFile = public_path('hot');
    expect($hotFile)->not->toBeFile(); // não deveríamos rodar isso com `npm run dev` de verdade ativo

    file_put_contents($hotFile, 'http://localhost:5173');

    try {
        $withHot = $this->get(route('login'))->getContent();
        expect($withHot)->toContain('Vite está em modo dev (HMR)');
    } finally {
        unlink($hotFile);
    }

    $withoutHot = $this->get(route('login'))->getContent();
    expect($withoutHot)->not->toContain('Vite está em modo dev (HMR)');
});

test('sw.js install never fails wholesale because a single app-shell asset could not be cached (Phase 38.4)', function () {
    $response = $this->get('/sw.js');

    $content = $response->getContent();

    // `cache.addAll()` é tudo-ou-nada: se um único asset falhar, a
    // instalação inteira falha e o worker nunca ativa — a página nunca fica
    // sob controle nenhum, offline ou online.
    expect($content)->not->toContain('cache.addAll(APP_SHELL_ASSETS)');
    expect($content)->toContain('Promise.allSettled');
});

test('the page shell reloads automatically once a new service worker takes control (Phase 38.1)', function () {
    $response = $this->get(route('login'));

    $content = $response->getContent();

    // `skipWaiting()` + `clients.claim()` (em `resources/pwa/sw.js`) fazem o
    // Service Worker novo assumir o controle da aba quase imediatamente,
    // mas isso sozinho NÃO recarrega o JavaScript da página já carregada —
    // uma aba aberta há tempo continua rodando o bundle Vue/Inertia antigo
    // enquanto já fala com um Service Worker novo. Sem este reload
    // automático, uma correção de bug entregue no código só se manifesta
    // depois de um F5 manual, o que parece "a correção não funcionou".
    expect($content)->toContain("addEventListener('controllerchange'");
    expect($content)->toContain('window.location.reload()');

    // O reload NUNCA pode disparar enquanto offline: se o novo Service
    // Worker terminar de ativar bem no instante em que a conexão caiu, um
    // reload incondicional navegaria offline para a URL atual sem garantia
    // de que o mini-servidor saiba reconstruir aquela rota — trocando "app
    // desatualizado, mas funcionando" por "navegação quebrada".
    expect($content)->toContain('!navigator.onLine');
});

test('the page that first registers the service worker reloads once to gain control of itself (Phase 38.4)', function () {
    $response = $this->get(route('login'));

    $content = $response->getContent();

    // Comportamento documentado (e fácil de esquecer) dos Service Workers:
    // a página que dispara o PRIMEIRO registro nunca fica sob o controle
    // dele — nem depois de ativo, nem com `clients.claim()` — só a PRÓXIMA
    // navegação de documento fica controlada. Sem este reload, aquela aba
    // específica nunca teria NENHUMA requisição interceptada offline (Links
    // do menu, criar/excluir ficha…) até um F5 manual — o padrão
    // intermitente relatado: funciona depois de recarregar, nunca sozinho.
    expect($content)->toContain('navigator.serviceWorker.ready');
    expect($content)->toContain('navigator.serviceWorker.controller');
    expect($content)->toContain('sessionStorage.getItem(CONTROL_RELOAD_KEY)');
});

test('sw.js never redirects a create/edit mutation back to its own dedicated form (Phase 38.1)', function () {
    $response = $this->get('/sw.js');

    $content = $response->getContent();

    // `request.referrer` de um POST/PUT disparado por `/workouts/create` ou
    // `/workouts/{id}/edit` é a própria página do formulário — usá-lo como
    // fallback do redirect fazia o usuário "voltar" para o formulário em
    // branco após salvar, indistinguível de "não funcionou".
    expect($content)->toContain('resolveMutationRedirectTarget');
    expect($content)->toMatch("/create_workout.*return.*'\\/workouts'/s");
    expect($content)->toMatch('/update_workout.*return.*workouts\\/\\$\\{route\\.params\\[0\\]\\}/s');
});

test('sw.js never lets a failure in offline mutation or page synthesis escape as a silent network error (Phase 38.1)', function () {
    $response = $this->get('/sw.js');

    $content = $response->getContent();

    // Antes desta blindagem, uma exceção dentro do processamento de uma
    // mutação offline (criar/editar/excluir) ou da montagem de uma página
    // pelo mini-servidor rejeitava a Promise de `event.respondWith()` — o
    // navegador reporta isso como um erro de rede genérico
    // (`net::ERR_FAILED`), indistinguível de "não funcionou" para quem usa o
    // app e sem nenhuma pista no console sobre a causa real.
    expect($content)->toContain('async function applyOfflineMutation');
    expect($content)->toContain('async function safeBuildOfflinePageResponse');
    expect($content)->toContain('console.error(`[SW] Falha ao aplicar mutação offline');
    expect($content)->toContain('console.error(`[SW] Falha ao montar a página offline');
});

test('sw.js never trusts navigator.onLine to decide fetch priority (Phase 38.3)', function () {
    $response = $this->get('/sw.js');

    $content = $response->getContent();

    // O modo "Offline" da aba Network do DevTools — o jeito mais comum de
    // testar isso — bloqueia requisições de verdade SEM NUNCA atualizar
    // `navigator.onLine` (que só reflete a interface de rede do sistema
    // operacional). Confiar nessa flag para decidir se o mini-servidor tem
    // prioridade sobre o cache HTTP fazia o Service Worker sempre tentar a
    // rede primeiro, falhar, e servir uma resposta cacheada desatualizada —
    // uma ficha criada offline só aparecia depois que a internet voltava de
    // verdade.
    expect($content)->not->toContain('self.navigator.onLine');
    expect($content)->toContain('networkFirstWithOfflineSynthesis');
});

test('sw.js intercepts the workout session lifecycle even when the frontend wrongly assumed it was online (Phase 38.3)', function () {
    $response = $this->get('/sw.js');

    $content = $response->getContent();

    // `Workouts/Show.vue` e `WorkoutSessions/Active.vue` decidem se
    // enfileiram a ação diretamente ou mandam um POST "normal" com base em
    // `navigator.onLine` — a mesma flag não confiável do teste acima. Sem
    // estas três rotas, um POST "online" que a rede rejeita de verdade
    // nunca era interceptado (não batia em nenhum padrão de
    // `MUTATION_ROUTES`), e o erro de rede cru estourava até o XHR —
    // indistinguível de "o app offline não funciona".
    expect($content)->toContain('start_session_fallback');
    expect($content)->toContain('log_set_fallback');
    expect($content)->toContain('finish_session_fallback');
    expect($content)->toContain('/^\\/workouts\\/([^/]+)\\/start\\/?$/');
    expect($content)->toContain('/^\\/workout-sessions\\/([^/]+)\\/sets\\/?$/');
    expect($content)->toContain('/^\\/workout-sessions\\/([^/]+)\\/finish\\/?$/');
});
