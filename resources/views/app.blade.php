<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <!-- PWA -->
        <meta name="theme-color" content="#8B5CF6">
        <meta name="application-name" content="Setwave">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="Setwave">
        <meta property="og:site_name" content="Setwave">
        <link rel="manifest" href="/manifest.json">
        <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
        <link rel="icon" type="image/x-icon" href="/favicon.ico">
        <link rel="icon" type="image/png" sizes="512x512" href="/icons/icon-512x512.png">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia

        <script>
            @if (file_exists(public_path('hot')))
                // `public/hot` só existe enquanto `npm run dev` está rodando — é o
                // sinalizador que faz o Laravel apontar `@vite(...)` para o
                // servidor do Vite (porta separada, ex: 5173) em vez dos arquivos
                // já compilados em `public/build`. Nesse modo, CADA componente
                // Vue (cada página) é buscado sob demanda diretamente do
                // servidor do Vite — uma origem que o Service Worker registrado
                // para esta origem NUNCA consegue interceptar. Offline, abrir
                // uma página cujo componente ainda não tinha sido carregado
                // nesta sessão trava para sempre (o `import()` dinâmico nunca
                // resolve), mesmo que os dados por trás (IndexedDB, mutação
                // sincronizada) estejam corretos — o sintoma clássico de "criei/
                // exclui, mas só aparece quando a internet volta". Isso NÃO É
                // um bug do Service Worker: é uma limitação inerente ao HMR do
                // Vite, documentada em qualquer projeto PWA. Para testar
                // comportamento offline de verdade, rode `npm run build` e sirva
                // só com `php artisan serve` (sem `npm run dev`).
                console.warn(
                    '[Setwave] Vite está em modo dev (HMR) — cada página é ' +
                    'carregada sob demanda de um servidor separado que o Service ' +
                    'Worker NÃO consegue interceptar. Testar offline aqui vai ' +
                    'travar ao abrir uma página ainda não visitada nesta sessão, ' +
                    'mesmo com os dados corretos por trás. Rode `npm run build` ' +
                    'e sirva só com `php artisan serve` para testar offline de verdade.'
                );
            @endif

            if ('serviceWorker' in navigator) {
                const CONTROL_RELOAD_KEY = 'setwave:sw-control-reload';

                window.addEventListener('load', async () => {
                    try {
                        await navigator.serviceWorker.register('/sw.js');

                        // Comportamento documentado (e fácil de esquecer) dos
                        // Service Workers: a página que dispara o PRIMEIRO
                        // registro nunca fica sob o controle dele — nem
                        // depois de ativo, nem com `clients.claim()` — só a
                        // PRÓXIMA navegação de documento fica controlada.
                        // Sem este reload, esta aba específica nunca teria
                        // NENHUMA requisição interceptada offline (Links do
                        // menu, criar/excluir ficha…) até um F5 manual —
                        // exatamente o padrão intermitente relatado: funciona
                        // depois de recarregar, mas nunca "sozinho".
                        //
                        // `navigator.serviceWorker.ready` resolve assim que
                        // existe um worker ATIVO para este escopo — só então
                        // faz sentido checar `controller`, senão um registro
                        // ainda instalando pareceria "sem controle" à toa.
                        await navigator.serviceWorker.ready;

                        if (navigator.serviceWorker.controller) return;

                        // Guarda de uma tentativa só por aba: evita loop se,
                        // por algum motivo, a página continuar sem controle
                        // mesmo depois do reload.
                        if (window.sessionStorage.getItem(CONTROL_RELOAD_KEY)) return;
                        window.sessionStorage.setItem(CONTROL_RELOAD_KEY, '1');

                        window.location.reload();
                    } catch (error) {
                        console.error('Falha ao registrar o Service Worker:', error);
                    }
                });

                // `skipWaiting()` + `clients.claim()` (em `resources/pwa/sw.js`)
                // fazem o NOVO Service Worker assumir o controle desta aba quase
                // imediatamente após ser instalado — mas isso não recarrega o
                // JavaScript da página já carregada, então uma aba aberta há
                // tempo continua rodando o bundle Vue/Inertia antigo enquanto já
                // fala com um Service Worker novo. Um `location.reload()`
                // automático RESOLVERIA isso, mas é perigoso demais para disparar
                // incondicionalmente: se este evento cair bem no instante em que
                // a conexão caiu (a instalação do novo Service Worker terminou
                // enquanto online, mas a ativação/claim só completou depois de o
                // usuário já estar offline), o reload forçado navegaria offline
                // para a URL atual — e não há garantia de que o mini-servidor
                // saiba reconstruir TODA rota (uma tela ainda não coberta por
                // `PAGE_ROUTES`), então essa "correção automática" podia deixar o
                // usuário pior do que estava (foi exatamente isso que causou uma
                // regressão real: navegação offline parou de funcionar depois
                // deste reload disparar sem internet). Por isso o reload só
                // acontece quando há certeza de que a rede está de pé.
                let refreshingAfterControllerChange = false;
                navigator.serviceWorker.addEventListener('controllerchange', () => {
                    if (refreshingAfterControllerChange || !navigator.onLine) return;
                    refreshingAfterControllerChange = true;
                    window.location.reload();
                });
            }
        </script>
    </body>
</html>
