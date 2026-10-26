<?php

declare(strict_types=1);

namespace Domain\Booking\Actions;

use Domain\Agent\Models\Agent;
use Domain\Form\Enums\FormType;
use Domain\Form\Models\FormDefinition;
use Illuminate\Support\Facades\DB;

/**
 * The agent's "Meeting bookings" form, created on first use. Bookings are
 * stored as its submissions, so they appear with the agent's other leads
 * (list, export, CRM push, notification). The Calendar capability manages it:
 * the owner can read its leads but not change the form.
 */
class EnsureBookingFormAction
{
    public const string SLUG = 'meeting-bookings';

    /** The capability that owns the form (its `managed_by`). */
    public const string CAPABILITY_SLUG = 'calendar';

    /** @var list<array<string, mixed>> */
    private const array SCHEMA = [
        ['key' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true],
        ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
        ['key' => 'meeting_type', 'label' => 'Meeting type', 'type' => 'text', 'required' => true],
        ['key' => 'start_time', 'label' => 'Start time', 'type' => 'text', 'required' => true],
        ['key' => 'timezone', 'label' => 'Time zone', 'type' => 'text', 'required' => true],
        ['key' => 'notes', 'label' => 'Notes', 'type' => 'textarea', 'required' => false],
    ];

    public function execute(Agent $agent): FormDefinition
    {
        $existing = $this->find($agent);

        if ($existing instanceof FormDefinition) {
            return $existing;
        }

        // The agent row lock keeps two first bookings from creating two forms.
        return DB::transaction(function () use ($agent): FormDefinition {
            Agent::query()->whereKey($agent->id)->lockForUpdate()->first();

            return $this->find($agent) ?? FormDefinition::create([
                'agent_id' => $agent->id,
                'name' => 'Meeting bookings',
                'subtitle' => null,
                'slug' => $this->uniqueSlug($agent),
                'form_type' => FormType::Booking,
                'schema' => self::SCHEMA,
                'lead_field_map' => ['name' => 'name', 'email' => 'email', 'notes' => 'message'],
                'success_message' => 'Your meeting is booked. A confirmation is on its way to your email.',
                'agent_instructions' => null,
                'is_active' => true,
                'managed_by' => self::CAPABILITY_SLUG,
            ]);
        });
    }

    private function find(Agent $agent): ?FormDefinition
    {
        return FormDefinition::query()->forAgent($agent)->managedBy(self::CAPABILITY_SLUG)->first();
    }

    /** The owner may already have a form with this slug. */
    private function uniqueSlug(Agent $agent): string
    {
        $slug = self::SLUG;
        $i = 1;

        while (FormDefinition::query()->forAgent($agent)->where('slug', $slug)->exists()) {
            $slug = self::SLUG.'-'.(++$i);
        }

        return $slug;
    }
}
