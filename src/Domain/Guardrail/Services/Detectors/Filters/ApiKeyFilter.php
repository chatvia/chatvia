<?php

declare(strict_types=1);

namespace Domain\Guardrail\Services\Detectors\Filters;

use Illuminate\Support\Arr;

/**
 * Masks API keys and private keys ([API_KEY_n]) a visitor pastes or an
 * answer repeats. Only formats with a fixed provider prefix (or a PEM
 * header) count, so ordinary ids and hashes stay readable: AWS access key
 * ids, GitHub, GitLab, OpenAI, Anthropic, OpenRouter, Stripe, Slack, Google,
 * Hugging Face, Groq and xAI keys.
 */
final class ApiKeyFilter extends PiiFilter
{
    private const array PATTERNS = [
        '\b(?:AKIA|ASIA|ABIA|ACCA|A3T[A-Z0-9])[A-Z0-9]{16}\b',
        '\bgh[pousr]_[A-Za-z0-9]{36,}',
        '\bgithub_pat_[A-Za-z0-9_]{82,}',
        '\bglpat-[A-Za-z0-9_-]{20,}',
        '\bsk-(?:proj|svcacct|admin)-[A-Za-z0-9_-]{20,200}T3BlbkFJ[A-Za-z0-9_-]{20,}',
        '\bsk-[A-Za-z0-9]{20}T3BlbkFJ[A-Za-z0-9]{20,}',
        '\bsk-ant-(?:api03|admin01)-[A-Za-z0-9_-]{32,}',
        '\bsk-or-v1-[a-f0-9]{64}\b',
        '\b(?:sk|rk)_(?:live|test)_[A-Za-z0-9]{24,}',
        '\bxox[abposr]-[A-Za-z0-9-]{10,}',
        '\bAIza[A-Za-z0-9_-]{35}',
        '\bhf_[A-Za-z0-9]{34,}',
        '\bgsk_[A-Za-z0-9]{52}\b',
        '\bxai-[A-Za-z0-9]{80}\b',
        '-----BEGIN (?:[A-Z]+ )?PRIVATE KEY-----[\s\S]*?-----END (?:[A-Z]+ )?PRIVATE KEY-----',
    ];

    protected function pattern(): string
    {
        $alternatives = Arr::join(self::PATTERNS, '|');

        return "/{$alternatives}/";
    }

    protected function label(): string
    {
        return 'API_KEY';
    }

    protected function type(): string
    {
        return 'api_key';
    }
}
