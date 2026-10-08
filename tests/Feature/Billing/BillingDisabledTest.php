<?php

use App\Http\Middleware\Access\EnsureAgentHasMessagesCapacity;
use App\Identity\Workspace\Models\User;
use App\Providers\AppServiceProvider;
use Domain\Agent\Models\Agent;
use Domain\Agent\Services\AgentLocator;
use Domain\Billing\Actions\Account\ConsumeMessageCreditsAction;
use Domain\Billing\Contracts\Entitlements;
use Domain\Billing\Enums\CapabilityEnum;
use Domain\Billing\Enums\MessageAdmission;
use Domain\Billing\Listeners\HandleStripeWebhook;
use Domain\Billing\Models\UserCredit;
use Domain\Billing\Services\MessageSendGate;
use Domain\Evaluation\Actions\RunEvalSuiteAction;
use Domain\Evaluation\Models\EvalCase;
use Domain\Evaluation\Models\EvalRun;
use Domain\Llm\Models\Llm;
use Domain\Membership\Team\Services\TeamService;
use Domain\Security\Enums\SecurityFeature;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Events\WebhookReceived;
use Laravel\Pennant\Feature;
use Stripe\Stripe;
use Support\Enums\User\UserType;

/**
 * A self-hosted install can run without Stripe (BILLING_ENABLED=false). Then
 * every workspace may always send messages, has every feature and no quota
 * limits, the billing pages and Stripe flows answer 404, and people sign up
 * through the normal registration form. With billing on (production) every
 * one of these behaves exactly as before — each group below pins both sides.
 */
beforeEach(function (): void {
    Cache::flush();

    config([
        'billing.enabled' => false,
        'billing.registration_enabled' => true,
        'billing.unlimited_retention_days' => 400,
    ]);
});

function billingOffOwner(): User
{
    $owner = User::factory()->workspace()->create();
    app(TeamService::class)->createDefaultTeam($owner);
    $owner->bootstrapCurrentTeam();

    return $owner->fresh();
}

/**
 * Evaluate config/billing.php under the given environment variables, as a
 * fresh boot would.
 *
 * @param  array<string, string|null>  $env
 * @return array<string, mixed>
 */
function billingConfigWith(array $env): array
{
    $previous = [];

    foreach ($env as $key => $value) {
        $previous[$key] = [$_SERVER[$key] ?? null, $_ENV[$key] ?? null, getenv($key)];

        if ($value === null) {
            unset($_SERVER[$key], $_ENV[$key]);
            putenv($key);
        } else {
            $_SERVER[$key] = $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }
    }

    try {
        return require base_path('config/billing.php');
    } finally {
        foreach ($previous as $key => [$server, $envValue, $putenv]) {
            if ($server === null) {
                unset($_SERVER[$key]);
            } else {
                $_SERVER[$key] = $server;
            }

            if ($envValue === null) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $envValue;
            }

            $putenv === false ? putenv($key) : putenv("{$key}={$putenv}");
        }
    }
}

describe('sending messages', function (): void {
    it('admits a workspace with no subscription and no credits', function (): void {
        $owner = billingOffOwner();

        expect(app(MessageSendGate::class)->admit($owner))->toBe(MessageAdmission::Allowed);
    });

    it('still refuses that workspace when billing is on', function (): void {
        config(['billing.enabled' => true]);

        expect(app(MessageSendGate::class)->admit(billingOffOwner()))->toBe(MessageAdmission::SubscriptionInactive);
    });

    it('lets the hosted chat through the message-capacity middleware', function (): void {
        $owner = billingOffOwner();
        $request = Request::create('/chat');
        $request->attributes->set('agent_user_id', $owner->id);

        $response = app(EnsureAgentHasMessagesCapacity::class)->handle($request, fn (): ResponseFactory|\Illuminate\Http\Response => response('passed'));

        expect($response->getContent())->toBe('passed');
    });

    it('starts an eval suite without message credits', function (): void {
        Bus::fake();
        $agent = Agent::factory()->create(['llm_id' => Llm::factory()->create()->id]);

        EvalCase::create([
            'agent_id' => $agent->id,
            'agent_name' => $agent->name,
            'workspace_id' => $agent->user_id,
            'name' => 'case',
            'input' => 'ping',
            'scorer_type' => 'contains',
            'scorer_config' => ['value' => 'pong'],
            'active' => true,
        ]);

        expect(app(RunEvalSuiteAction::class)->execute($agent)->status)->toBe(EvalRun::STATUS_RUNNING);

        config(['billing.enabled' => true]);

        expect(fn () => app(RunEvalSuiteAction::class)->execute($agent))->toThrow(ValidationException::class);
    });

    it('does not charge or warn when a reply is billed', function (): void {
        $owner = billingOffOwner();
        $credit = UserCredit::factory()->create([
            'user_id' => $owner->id,
            'messages' => 100,
            'started_at' => now()->subDay(),
            'ends_at' => now()->addDays(20),
        ]);
        $credit->usage()->create(['messages' => 0]);
        $withoutCredit = billingOffOwner();
        $llm = Llm::factory()->create(['credits' => 2]);

        Log::spy();

        app(ConsumeMessageCreditsAction::class)->execute($owner->id, $llm->id, chargeKey: 'off-1');
        app(ConsumeMessageCreditsAction::class)->execute($withoutCredit->id, $llm->id, chargeKey: 'off-2');

        expect((int) $credit->usage()->value('messages'))->toBe(0);
        Log::shouldNotHaveReceived('warning');
    });
});

describe('plan features', function (): void {
    it('turns every capability on', function (): void {
        $owner = billingOffOwner();

        foreach (CapabilityEnum::cases() as $capability) {
            expect($owner->hasCapability($capability))->toBeTrue();
        }

        expect($owner->capabilities->security)->toBeTrue()
            ->and($owner->capabilities->siemWebhook)->toBeTrue()
            ->and($owner->capabilities->retentionDays)->toBe(400)
            ->and($owner->canHideBranding())->toBeTrue()
            ->and($owner->hasActiveSubscription())->toBeTrue()
            ->and(Feature::for($owner)->active(SecurityFeature::Security->value))->toBeTrue();
    });

    it('gives employees the same capabilities as their workspace', function (): void {
        $owner = billingOffOwner();
        $employee = User::factory()->employee($owner)->create();

        expect($employee->hasCapability(CapabilityEnum::AUDIT))->toBeTrue();
    });

    it('keeps conversations for the configured number of days', function (): void {
        $agent = Agent::factory()->create();

        expect($agent->messageRetentionInDays())->toBe(400);
    });

    it('falls back to the starter plan for an account without a plan when billing is on', function (): void {
        config(['billing.enabled' => true]);
        $owner = billingOffOwner();

        expect($owner->hasCapability(CapabilityEnum::API))->toBeFalse()
            ->and($owner->hasCapability(CapabilityEnum::EVALS))->toBeFalse()
            ->and($owner->capabilities->retentionDays)->toBe(90)
            ->and($owner->canHideBranding())->toBeFalse()
            ->and($owner->hasActiveSubscription())->toBeFalse();
    });
});

describe('quotas', function (): void {
    it('has no limits even without a quota row', function (): void {
        $owner = billingOffOwner();

        expect($owner->quota)->toBeNull()
            ->and($owner->quotaLimit('teams_limit'))->toBe(Entitlements::UNLIMITED)
            ->and($owner->totalAgentsLimit())->toBeGreaterThanOrEqual(Entitlements::UNLIMITED)
            ->and($owner->totalWorkspaceUsersLimit())->toBeGreaterThanOrEqual(Entitlements::UNLIMITED)
            ->and($owner->totalCustomDomainsLimit())->toBeGreaterThanOrEqual(Entitlements::UNLIMITED)
            ->and($owner->canCreateTeam())->toBeTrue()
            ->and($owner->canAddWorkspaceUser())->toBeTrue()
            ->and($owner->consumption()->canUploadPages(500_000))->toBeTrue();
    });

    it('serves an agent on its custom domain without a custom-domain quota', function (): void {
        $agent = Agent::factory()->create(['domain' => 'help.billing-off.test']);

        expect(AgentLocator::resolveByHost('help.billing-off.test'))->toBe($agent->external_id);

        config(['billing.enabled' => true]);

        expect(AgentLocator::resolveByHost('help.billing-off.test'))->toBeNull();
    });

    it('uses the default package limits for an account without a quota row when billing is on', function (): void {
        config(['billing.enabled' => true]);
        $owner = billingOffOwner();

        expect($owner->quotaLimit('teams_limit'))->toBe((int) config('billing.packages.starter.quota_limits.fixed.teams_limit'))
            ->and($owner->totalCustomDomainsLimit())->toBe(0)
            ->and($owner->canCreateTeam())->toBeFalse();
    });

    it('reports usage without limits and without a credit cycle', function (): void {
        $owner = billingOffOwner();

        $this->actingAs($owner)
            ->getJson(route('usage'))
            ->assertOk()
            ->assertJsonPath('unlimited', true)
            ->assertJsonPath('pages.limit', Entitlements::UNLIMITED)
            ->assertJsonPath('subscription.started_at', null);
    });

    it('reports a limited usage when billing is on', function (): void {
        config(['billing.enabled' => true]);
        $owner = billingOffOwner();

        $this->actingAs($owner)
            ->getJson(route('usage'))
            ->assertOk()
            ->assertJsonPath('unlimited', false)
            ->assertJsonPath('pages.limit', (int) config('billing.packages.starter.quota_limits.fixed.pages_limit'));
    });
});

describe('billing pages and Stripe flows', function (): void {
    it('answers 404 for every billing page', function (string $method, string $route): void {
        $this->actingAs(billingOffOwner())
            ->call($method, route($route))
            ->assertNotFound();
    })->with([
        'billing home' => ['GET', 'billing.index'],
        'plans' => ['GET', 'billing.packages'],
        'subscribe' => ['GET', 'subscribe'],
        'invoices' => ['GET', 'billing.invoices'],
        'payment methods' => ['GET', 'billing.payment-methods'],
        'add-on checkout' => ['GET', 'billing.addons.extra-agents'],
        'cancel subscription' => ['POST', 'billing.cancel'],
    ]);

    it('answers 404 for the trial checkout and its success page', function (): void {
        $this->post(route('trial.checkout'), ['package' => 'starter'])->assertNotFound();
        $this->get(route('trial.success'))->assertNotFound();
    });

    it('still serves the billing pages when billing is on', function (): void {
        config(['billing.enabled' => true]);

        $this->actingAs(billingOffOwner())
            ->get(route('billing.packages'))
            ->assertOk();
    });

    it('lets a workspace without a subscription into the dashboard routes', function (): void {
        $owner = billingOffOwner();

        $this->actingAs($owner)->getJson(route('countries.list'))->assertOk();

        config(['billing.enabled' => true]);

        $this->actingAs($owner->fresh())->get(route('countries.list'))->assertRedirect(route('billing.packages'));
    });

    it('shares the billing switch with the frontend', function (): void {
        $owner = billingOffOwner();

        $this->actingAs($owner)
            ->get(route('usage'))
            ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('billing.enabled', false));

        config(['billing.enabled' => true]);

        $this->actingAs($owner->fresh())
            ->get(route('usage'))
            ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('billing.enabled', true));
    });
});

describe('Stripe webhook', function (): void {
    it('ignores Stripe events', function (): void {
        $owner = billingOffOwner();
        $owner->billing()->create(['stripe_id' => 'cus_billing_off']);
        $users = User::query()->count();

        $listener = app(HandleStripeWebhook::class);

        // A trial sign-up would create an account (and call Stripe); a renewal
        // would grant credits. Neither may happen with billing off.
        $listener->handle(new WebhookReceived([
            'id' => 'evt_billing_off_trial',
            'type' => 'checkout.session.completed',
            'data' => ['object' => [
                'customer' => 'cus_new_billing_off',
                'subscription' => 'sub_billing_off',
                'metadata' => ['registration_type' => 'trial_registration', 'package' => 'pro'],
            ]],
        ]));
        $listener->handle(new WebhookReceived([
            'id' => 'evt_billing_off_renewal',
            'type' => 'invoice.payment_succeeded',
            'data' => ['object' => [
                'id' => 'in_billing_off',
                'customer' => 'cus_billing_off',
                'billing_reason' => 'subscription_cycle',
                'subscription_details' => ['metadata' => ['to_package' => 'pro', 'is_yearly' => 'false']],
            ]],
        ]));

        expect(User::query()->count())->toBe($users)
            ->and(UserCredit::query()->where('user_id', $owner->id)->exists())->toBeFalse();
    });

    it('does not register the Cashier webhook route', function (): void {
        try {
            Cashier::$registersRoutes = true;

            (new AppServiceProvider(app()))->register();

            expect(Cashier::$registersRoutes)->toBeFalse();

            Cashier::$registersRoutes = true;
            config(['billing.enabled' => true]);

            (new AppServiceProvider(app()))->register();

            expect(Cashier::$registersRoutes)->toBeTrue();
        } finally {
            Cashier::$registersRoutes = true;
        }
    });
});

describe('booting', function (): void {
    it('boots in production without Stripe keys', function (): void {
        config(['cashier.webhook.secret' => null, 'cashier.secret' => 'sk_should_not_be_used']);
        $provider = new AppServiceProvider(app());
        $previousKey = Stripe::getApiKey();
        app()->detectEnvironment(fn (): string => 'production');

        try {
            (fn () => $this->assertProductionSecurityConfig())->call($provider);
            (fn () => $this->configureStripe())->call($provider);

            expect(Stripe::getApiKey())->toBe($previousKey);

            config(['billing.enabled' => true]);

            expect(fn () => (fn () => $this->assertProductionSecurityConfig())->call($provider))
                ->toThrow(RuntimeException::class, 'STRIPE_WEBHOOK_SECRET');
        } finally {
            app()->detectEnvironment(fn (): string => 'testing');
            Stripe::setApiKey($previousKey);
        }
    });
});

describe('sign-up', function (): void {
    it('shows the sign-up form and the link to it', function (): void {
        $this->get(route('register'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->component('auth/Register'));

        $this->get(route('login'))
            ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('canRegister', true));
    });

    it('creates a ready-to-use workspace account', function (): void {
        Event::fake([Registered::class]);

        $this->post(route('register.store'), [
            'name' => 'Self Hoster',
            'email' => 'owner@self-hosted.test',
            'password' => 'a-long-password',
            'password_confirmation' => 'a-long-password',
        ])->assertRedirect(route('dashboard'));

        $user = User::query()->where('email', 'owner@self-hosted.test')->sole();
        $team = $user->ownedTeams()->sole();

        $this->assertAuthenticatedAs($user);
        expect($user->type)->toBe(UserType::Workspace)
            ->and($user->current_team_id)->toBe($team->id)
            ->and($team->roles()->where('code', 'owner')->exists())->toBeTrue()
            ->and(app(MessageSendGate::class)->admit($user))->toBe(MessageAdmission::Allowed);
        Event::assertDispatched(Registered::class);
    });

    it('answers 404 once sign-ups are closed', function (): void {
        config(['billing.registration_enabled' => false]);

        $this->get(route('register'))->assertNotFound();
        $this->post(route('register.store'), [
            'name' => 'Late Comer',
            'email' => 'late@self-hosted.test',
            'password' => 'a-long-password',
            'password_confirmation' => 'a-long-password',
        ])->assertNotFound();

        $this->get(route('login'))
            ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('canRegister', false));

        expect(User::query()->where('email', 'late@self-hosted.test')->exists())->toBeFalse();
    });
});

describe('config defaults', function (): void {
    it('keeps billing on and sign-up closed when nothing is set', function (): void {
        $config = billingConfigWith(['BILLING_ENABLED' => null, 'REGISTRATION_ENABLED' => null]);

        expect($config['enabled'])->toBeTrue()
            ->and($config['registration_enabled'])->toBeFalse();
    });

    it('opens sign-up by default when billing is off, unless it is closed explicitly', function (): void {
        expect(billingConfigWith(['BILLING_ENABLED' => 'false', 'REGISTRATION_ENABLED' => null])['registration_enabled'])->toBeTrue()
            ->and(billingConfigWith(['BILLING_ENABLED' => 'false', 'REGISTRATION_ENABLED' => 'false'])['registration_enabled'])->toBeFalse()
            ->and(billingConfigWith(['BILLING_ENABLED' => 'false', 'REGISTRATION_ENABLED' => null])['enabled'])->toBeFalse();
    });
});
