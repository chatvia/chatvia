<?php

declare(strict_types=1);

namespace App\Admin\Settings;

use App\Admin\Settings\Data\LicenseActivationData;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Support\License\LicenseKey;
use Support\License\LicenseSignature;

/**
 * Talks to chatvia.ai about this install's license key. A key works on one
 * domain at a time: chatvia.ai records where it is activated, refuses it on
 * any other domain, and answers an activation with the key signed for this
 * domain, which the install saves and checks offline ({@see CreditLicense}).
 * The buyer can deactivate a key there to move it; the daily check notices.
 *
 * Only the key (or its id, for the check) and the domain are sent.
 */
final readonly class LicenseActivation
{
    private const string UNREACHABLE = 'chatvia.ai could not be reached to activate the key. Try again in a few minutes.';

    public function __construct(
        private Config $config,
        private CreditLicense $license,
    ) {}

    /** Activates $key (as bought, or activated before) for this install's domain. */
    public function activate(string $key): LicenseActivationData
    {
        try {
            $response = $this->post('activate', ['key' => Str::replaceMatches('/\s+/', '', $key)]);
        } catch (ConnectionException) {
            return new LicenseActivationData(message: self::UNREACHABLE);
        }

        if ($response->successful()) {
            $activated = $response->json('key');

            return filled($activated) && $this->license->allowsHidingCredit($activated)
                ? new LicenseActivationData(key: $activated)
                : new LicenseActivationData(message: 'chatvia.ai answered, but this install could not check the activated key. Update ChatVia.ai and try again.');
        }

        $message = $response->json('message');

        return new LicenseActivationData(message: $response->clientError() && filled($message)
            ? Str::limit($message, 300)
            : self::UNREACHABLE);
    }

    /**
     * Frees the key at chatvia.ai, so it can be activated on another domain.
     * Best effort: the buyer can also deactivate it on chatvia.ai.
     */
    public function release(string $key): void
    {
        try {
            $this->post('deactivate', ['key' => $key]);
        } catch (ConnectionException) {
            // Nothing to do: the key stays activated for this domain at chatvia.ai.
        }
    }

    /**
     * Whether chatvia.ai still has the key active for this domain: null when
     * it could not say (unreachable, or an error on its side).
     */
    public function isActive(string $key): ?bool
    {
        $license = LicenseSignature::verify($key, $this->config->get('chatvia.license.public_key'));

        if (! $license instanceof LicenseKey) {
            return false;
        }

        try {
            $response = $this->post('check', ['id' => $license->id]);
        } catch (ConnectionException) {
            return null;
        }

        $active = $response->json('active');

        return $response->successful() && is_bool($active) ? $active : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws ConnectionException
     */
    private function post(string $endpoint, array $payload): Response
    {
        return Http::acceptJson()
            ->timeout(15)
            ->post(Str::rtrim((string) $this->config->get('chatvia.license.api_url'), '/').'/'.$endpoint, [
                ...$payload,
                'domain' => $this->license->host(),
            ]);
    }
}
