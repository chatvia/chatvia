/**
 * Human-in-the-loop tool approval for the hosted chat. Collects the
 * Approve/Deny cards a turn paused on (keyed by assistant message UUID) and the
 * visitor's decisions. One checkpoint (`approval_id`) can gate several calls;
 * once every call in it is decided, `onResolve` re-opens the stream.
 */
import type { StreamToolData, ToolApprovalCall } from '@/types/stream';
import { computed, ref } from 'vue';

export type ApprovalDecision = 'approve' | 'deny';

/** tool_call_id → decision, for one checkpoint. */
export type ApprovalDecisions = Record<string, ApprovalDecision>;

type MessageId = string | number;

type ResolveHandler = (approvalId: string, assistantMessageId: MessageId, decisions: ApprovalDecisions) => void;

export function useToolApprovals(onResolve: ResolveHandler) {
    const pendingApprovals = ref(new Map<MessageId, ToolApprovalCall[]>());
    // Checkpoints being resolved: their cards show a spinner and disable.
    const resolvingApprovalIds = ref<string[]>([]);
    const decisionsByApproval = new Map<string, ApprovalDecisions>();

    const hasPendingApprovals = computed(() => pendingApprovals.value.size > 0);

    function approvalsFor(assistantMessageId: MessageId | null | undefined): ToolApprovalCall[] {
        return assistantMessageId ? (pendingApprovals.value.get(assistantMessageId) ?? []) : [];
    }

    /** Shows a card for a call the server paused (`tool` event, phase `awaiting_approval`). */
    function addPending(assistantMessageId: MessageId, data: StreamToolData) {
        if (!data.approval_id) return;

        const calls = pendingApprovals.value.get(assistantMessageId) ?? [];
        if (!calls.some((call) => call.tool_call_id === data.tool_call_id)) {
            calls.push({
                tool_call_id: data.tool_call_id,
                approval_id: data.approval_id,
                name: data.name ?? '',
                label: data.label ?? data.name ?? '',
                kind: data.kind ?? 'function',
                icon_url: data.icon_url ?? null,
                brand_color: data.brand_color ?? null,
                args: typeof data.args === 'string' ? data.args : '',
                reason: data.reason ?? null,
            });
        }
        pendingApprovals.value.set(assistantMessageId, calls);
    }

    /** Drops the cards (and their spinners) for one assistant message. */
    function clearFor(assistantMessageId: MessageId) {
        const calls = pendingApprovals.value.get(assistantMessageId);
        if (!calls) return;

        const approvalIds = calls.map((call) => call.approval_id);
        pendingApprovals.value.delete(assistantMessageId);
        resolvingApprovalIds.value = resolvingApprovalIds.value.filter((id) => !approvalIds.includes(id));
    }

    /** A fresh turn supersedes every pending card. */
    function reset() {
        pendingApprovals.value.clear();
        resolvingApprovalIds.value = [];
        decisionsByApproval.clear();
    }

    function messageFor(approvalId: string): MessageId | null {
        for (const [messageId, calls] of pendingApprovals.value.entries()) {
            if (calls.some((call) => call.approval_id === approvalId)) return messageId;
        }

        return null;
    }

    function decide(call: ToolApprovalCall, decision: ApprovalDecision) {
        if (resolvingApprovalIds.value.includes(call.approval_id)) return;

        const decisions = decisionsByApproval.get(call.approval_id) ?? {};
        decisions[call.tool_call_id] = decision;
        decisionsByApproval.set(call.approval_id, decisions);

        const assistantMessageId = messageFor(call.approval_id);
        if (assistantMessageId === null) return;

        const siblings = approvalsFor(assistantMessageId).filter((sibling) => sibling.approval_id === call.approval_id);
        if (!siblings.every((sibling) => decisions[sibling.tool_call_id])) return;

        resolvingApprovalIds.value = [...resolvingApprovalIds.value, call.approval_id];
        decisionsByApproval.delete(call.approval_id);
        onResolve(call.approval_id, assistantMessageId, decisions);
    }

    return {
        resolvingApprovalIds,
        hasPendingApprovals,
        approvalsFor,
        addPending,
        clearFor,
        reset,
        approve: (call: ToolApprovalCall) => decide(call, 'approve'),
        deny: (call: ToolApprovalCall) => decide(call, 'deny'),
    };
}

export type ToolApprovals = ReturnType<typeof useToolApprovals>;
