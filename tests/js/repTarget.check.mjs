// Executado por tests/Feature/RepTargetJsMirrorTest.php (não há runner de
// testes JS no projeto): imprime, em JSON, o resultado do utilitário do
// frontend para os mesmos casos da regra PHP (RepRange).
import {
    formatRepTarget,
    initialRepsFor,
    parseRepRange,
} from '../../resources/js/Utils/repTarget.js';

const texts = ['10', '8-10', '8 a 10', '8–10', '8 - 10', '10-8', '12/10/8', '10 cada lado', '3x10', '4 × 8-10', 'até a falha', 'AMRAP', '', null];

const range = { target_reps: '8-10', target_reps_min: 8, target_reps_max: 10 };
const legacy = { target_reps: '8-10' };

console.log(JSON.stringify({
    parse: Object.fromEntries(texts.map((t) => [String(t), parseRepRange(t)])),
    format: Object.fromEntries(texts.map((t) => [String(t), formatRepTarget(t)])),
    initial: {
        suggestion_in_range: initialRepsFor(range, { reps: 7 }, { suggested_reps: 9 }),
        suggestion_clamped: initialRepsFor(range, { reps: 7 }, { suggested_reps: 14 }),
        suggestion_rounded: initialRepsFor(range, null, { suggested_reps: '9' }),
        no_suggestion_with_history: initialRepsFor(range, { reps: 7 }, null),
        suggestion_without_reps: initialRepsFor(range, { reps: 7 }, { suggested_reps: null }),
        no_suggestion_no_history: initialRepsFor(range, null, null),
        legacy_offline_no_history: initialRepsFor(legacy, undefined, null),
        no_numeric_target: initialRepsFor({ target_reps: 'até a falha' }, null, null),
        sw_default_target: initialRepsFor({ target_reps: '10' }, null, null),
    },
}));
