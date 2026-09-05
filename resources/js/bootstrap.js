import axios from 'axios';
window.axios = axios;

// O Service Worker responde com um JSON de fallback sinalizado por este
// cabeçalho quando uma navegação/requisição Inertia falha offline e não há
// nenhuma cópia em cache daquela URL exata (ver `resources/pwa/sw.js`). Em
// vez de deixar o Inertia tentar renderizar esse JSON vazio como se fosse uma
// página de verdade, recarregamos: o reload é uma navegação de documento
// completo, que o Service Worker sabe resolver com o app shell em cache.
axios.interceptors.response.use((response) => {
    if (response.headers?.['x-inertia-offline-fallback']) {
        window.location.reload();
        return new Promise(() => {});
    }

    return response;
});