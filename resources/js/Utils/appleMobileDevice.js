// Única checagem de "é iPhone/iPad?" do app (Phase 50). No iOS/iPadOS, o
// app não toca nenhum áudio durante o treino: qualquer `play()` — até o
// loop de silêncio — interrompe a música que a pessoa ouve em outro app.
//
// Todo navegador do iPhone/iPad roda sobre o WebKit do iOS, então Chrome
// (`CriOS`), Firefox (`FxiOS`) e Edge (`EdgiOS`) também contam. O app
// instalado na tela de início usa o mesmo user agent do Safari.
//
// O iPad com iPadOS 13+ se identifica como Mac ("Macintosh" no user agent,
// `MacIntel` na plataforma). A diferença é a tela sensível ao toque: um Mac
// de verdade tem `maxTouchPoints` 0 (ou 1 em alguns trackpads), o iPad 5.
//
// Os dados do aparelho podem ser passados por parâmetro (para os testes em
// `tests/js/`); sem eles, usa os do navegador. Fora do navegador (Node, sem
// `navigator`), responde `false`.
const IOS_DEVICE = /iPhone|iPad|iPod/i;
const IOS_BROWSER = /CriOS|FxiOS|EdgiOS/;

export function isAppleMobileDevice({ userAgent, platform, maxTouchPoints } = {}) {
    const nav = typeof navigator !== 'undefined' ? navigator : undefined;

    const ua = userAgent ?? nav?.userAgent ?? '';
    const platformName = platform ?? nav?.userAgentData?.platform ?? nav?.platform ?? '';
    const touchPoints = maxTouchPoints ?? nav?.maxTouchPoints ?? 0;

    if (/Android/i.test(ua) || /Android/i.test(platformName)) return false;

    if (IOS_DEVICE.test(ua) || IOS_BROWSER.test(ua) || IOS_DEVICE.test(platformName)) {
        return true;
    }

    // iPad que se identifica como Mac: só conta com tela de toque.
    const looksLikeMac = /Macintosh/.test(ua) || /^Mac/i.test(platformName);
    return looksLikeMac && touchPoints > 1;
}
