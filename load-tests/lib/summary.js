// handleSummary(): the end-of-run summary on the terminal, the full JSON in results/, and results/<profile>-checks.txt,
// one line per check and per custom metric, which the proofs read (tests/assert-check.sh, tests/assert-metric.sh).
import { PROFILE } from './http.js';
import { LOGIN_LIMIT } from '../scenarios/limits.js';
import { breakpointRateAt } from './profiles.js';

const CUSTOM_METRICS = [
    'unexpected_errors',
    'order_payable_wait',
    'contention_created',
    'contention_refused',
    'contention_server_errors',
    'cross_orders_created',
    'cross_orders_server_errors',
    'rate_limit_burst_passed',
];

/** Every check of the run, wherever the summary nests it (root group, groups, sub-groups). */
export function collectChecks(group) {
    if (!group) {
        return [];
    }

    const own = (group.checks || []).map((c) => ({ name: c.name, passes: c.passes, fails: c.fails }));
    const nested = (group.groups || []).flatMap(collectChecks);

    return own.concat(nested);
}

/** Counters sum their values, trends count their samples, rates count passes and fails. */
function metricLine(name, metric) {
    const values = metric.values || {};
    const count = values.count !== undefined ? values.count : (values.passes || 0) + (values.fails || 0);
    const value = values['p(95)'] !== undefined ? values['p(95)'] : values.rate !== undefined ? values.rate : count;

    return `metric\t${name}\t${count}\t${value}`;
}

function derivedLines(data) {
    const lines = [];
    const burst = data.metrics.rate_limit_burst_passed;

    if (burst) {
        lines.push(`metric\trate_limit_overshoot\t1\t${Math.max(0, burst.values.count - LOGIN_LIMIT)}`);
    }

    // The breakpoint result: the arrival rate when the run stopped (aborted by a threshold, or at the end).
    if (PROFILE === 'breakpoint' && data.setup_data && data.setup_data.startedAt) {
        const rate = breakpointRateAt((Date.now() - data.setup_data.startedAt) / 1000);
        lines.push(`metric\tbreakpoint_rate_at_stop\t1\t${rate.toFixed(1)}`);
    }

    return lines;
}

function format(value) {
    return typeof value === 'number' ? value.toFixed(2) : String(value);
}

function textReport(data, checks) {
    const out = [`\nk6 · perfil ${PROFILE} · ${format((data.state || {}).testRunDurationMs / 1000)} s\n`];

    out.push('Limites de aprovação:');
    for (const [name, metric] of Object.entries(data.metrics)) {
        for (const [threshold, result] of Object.entries(metric.thresholds || {})) {
            out.push(`  ${result.ok ? 'ok   ' : 'FALHA'} ${name}: ${threshold}`);
        }
    }

    const failed = checks.filter((c) => c.fails > 0);
    out.push(`\nChecks: ${checks.length - failed.length} de ${checks.length} sem falha`);
    for (const c of failed) {
        out.push(`  FALHA ${c.name} (${c.passes} ok, ${c.fails} falhas)`);
    }

    out.push('\nMétricas:');
    for (const name of ['http_reqs', 'http_req_duration', 'http_req_duration{flow:storefront}', 'http_req_duration{name:POST /api/orders}', ...CUSTOM_METRICS]) {
        const metric = data.metrics[name];
        if (metric) {
            const values = Object.entries(metric.values).map(([key, value]) => `${key}=${format(value)}`).join(' ');
            out.push(`  ${name}: ${values}`);
        }
    }

    for (const line of derivedLines(data)) {
        out.push(`  ${line.split('\t')[1]}: ${line.split('\t')[3]}`);
    }

    return `${out.join('\n')}\n`;
}

export function summarize(data) {
    const stamp = __ENV.RUN_STAMP || 'manual';
    const checks = collectChecks(data.root_group);
    const lines = checks.map((c) => `check\t${c.passes}\t${c.fails}\t${c.name}`);

    for (const name of CUSTOM_METRICS) {
        if (data.metrics[name]) {
            lines.push(metricLine(name, data.metrics[name]));
        }
    }

    lines.push(...derivedLines(data));

    return {
        stdout: textReport(data, checks),
        [`results/${PROFILE}-${stamp}.json`]: JSON.stringify(data, null, 2),
        [`results/${PROFILE}-checks.txt`]: `${lines.join('\n')}\n`,
    };
}
