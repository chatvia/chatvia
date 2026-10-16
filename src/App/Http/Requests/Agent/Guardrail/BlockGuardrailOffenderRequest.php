<?php

declare(strict_types=1);

namespace App\Http\Requests\Agent\Guardrail;

use App\Identity\Chatter\Data\BlockChatterData;
use App\Identity\Chatter\Enums\ChatterBlockNotice;
use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\TeamPermissionPolicy;
use Domain\Agent\Models\Agent;
use Domain\Guardrail\Models\GuardrailEvent;
use Domain\Guardrail\Queries\GuardrailOffendersQuery;
use Domain\Security\Data\BlockVisitorData;
use Domain\Security\Data\SecurityActorData;
use Domain\Security\Enums\VisitorBlockDuration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Block the person behind an abusive guardrail trigger, from the Guardrails
 * Activity log. A chatter is switched off, which needs the permission the
 * People page needs (`account.chatters.manage`); an anonymous visitor is
 * blocked by fingerprint for a while, which needs `account.security` (the
 * Security page lists the blocks). A trigger no one can be blamed for (PII,
 * a knowledge or tool result) cannot be blocked.
 */
class BlockGuardrailOffenderRequest extends FormRequest
{
    public function authorize(TeamPermissionPolicy $permissions): bool
    {
        $user = $this->user();

        if (! $user instanceof User) {
            return false;
        }

        return $this->event()->chatter_id !== null
            ? $permissions->allows($user, 'account.chatters.manage')
            : $permissions->allows($user, 'account.security');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'duration' => [Rule::requiredIf($this->event()->chatter_id === null), Rule::enum(VisitorBlockDuration::class)],
            'reason' => ['nullable', 'string', 'max:255'],
            'notify' => ['boolean'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(GuardrailOffendersQuery $offenders): array
    {
        return [
            function (Validator $validator) use ($offenders): void {
                if (! $offenders->isBlamable($this->event())) {
                    $validator->errors()->add('event', __('No one can be blocked for this trigger.'));
                }
            },
        ];
    }

    public function blocksChatter(): bool
    {
        return $this->event()->chatter_id !== null;
    }

    public function toChatterData(): BlockChatterData
    {
        $event = $this->event();

        return new BlockChatterData(
            chatter: Chatter::query()
                ->where('workspace_id', $event->workspace_id)
                ->whereKey($event->chatter_id)
                ->firstOrFail(),
            notice: $this->boolean('notify', true) ? ChatterBlockNotice::Email : ChatterBlockNotice::Silent,
            actor: $this->actor(),
            reason: $this->reason(),
            agentId: $event->agent_id,
        );
    }

    public function toVisitorData(): BlockVisitorData
    {
        $event = $this->event();

        return new BlockVisitorData(
            workspaceId: $event->workspace_id,
            visitorKey: (string) $event->visitor_key,
            duration: $this->enum('duration', VisitorBlockDuration::class) ?? VisitorBlockDuration::Week,
            actor: $this->actor(),
            agentId: $event->agent_id,
            conversationExternalId: $event->conversation_external_id,
            reason: $this->reason(),
        );
    }

    private function event(): GuardrailEvent
    {
        $event = $this->route('event');

        abort_unless($event instanceof GuardrailEvent && $this->route('agent') instanceof Agent, 404);

        return $event;
    }

    private function reason(): ?string
    {
        $reason = trim($this->string('reason')->toString());

        return $reason === '' ? null : $reason;
    }

    private function actor(): SecurityActorData
    {
        /** @var User $user */
        $user = $this->user();

        return SecurityActorData::forUser($user, $this->ip(), $this->userAgent());
    }
}
