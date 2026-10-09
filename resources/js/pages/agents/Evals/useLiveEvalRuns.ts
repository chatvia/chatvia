/**
 * A live copy of the agent's eval runs. Reverb events update a run's status and
 * pass/fail tally in place, so progress shows without a reload; any Inertia
 * reload (run started, deleted, cleared) resets the copy to the server's list.
 */
import { useEcho } from '@laravel/echo-vue';
import { computed, ref, watch } from 'vue';
import type { EvalCaseScoredEvent, EvalRun, EvalRunProgressedEvent } from './types';

export function useLiveEvalRuns(agentExternalId: string, serverRuns: () => EvalRun[]) {
    const runs = ref<EvalRun[]>([...serverRuns()]);

    watch(serverRuns, (next) => {
        runs.value = [...next];
    });

    const hasRunning = computed(() => runs.value.some((r) => r.status === 'running'));

    // A run row upserts when it's started or finalized; each scored case bumps
    // its running pass/fail tally so progress shows before the run is finalized.
    // useEcho cleans up on unmount, and both listeners share the one
    // ref-counted channel subscription.
    useEcho(`agents.${agentExternalId}.evals`, '.EvalRunProgressed', (e: EvalRunProgressedEvent) => {
        const idx = runs.value.findIndex((r) => r.external_id === e.run.external_id);
        if (idx === -1) {
            runs.value = [e.run, ...runs.value];
        } else {
            runs.value[idx] = e.run;
        }
    });

    useEcho(`agents.${agentExternalId}.evals`, '.EvalCaseScored', (e: Omit<EvalCaseScoredEvent, 'result'>) => {
        const run = runs.value.find((r) => r.external_id === e.run_external_id);
        if (!run) return;
        run.passed_count = e.passed_count;
        run.failed_count = e.failed_count;
        const scored = e.passed_count + e.failed_count;
        run.pass_rate = scored > 0 ? Math.round((e.passed_count / scored) * 100) : null;
    });

    return { runs, hasRunning };
}
