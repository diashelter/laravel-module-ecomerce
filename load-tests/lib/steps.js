// Small helpers shared by the flows.
import { sleep } from 'k6';
import exec from 'k6/execution';
import { PROFILE } from './http.js';

/** `smoke` walks every state once; every other profile is a volume profile. */
export const MODE = PROFILE === 'smoke' ? 'smoke' : 'volume';

export const STATES = ['SP', 'RJ', 'MG', 'PR', 'BA', 'PE', 'RS', 'DF', 'AM', 'GO'];

/** The pause of a person between two screens: 1 to 3 seconds. */
export function think() {
    sleep(1 + Math.random() * 2);
}

export function pick(list) {
    return list[Math.floor(Math.random() * list.length)];
}

export function pickMany(list, count) {
    const copy = list.slice();
    const picked = [];

    while (picked.length < count && copy.length > 0) {
        picked.push(copy.splice(Math.floor(Math.random() * copy.length), 1)[0]);
    }

    return picked;
}

export function position() {
    return { vu: exec.vu.idInTest, iteration: exec.vu.iterationInScenario };
}
