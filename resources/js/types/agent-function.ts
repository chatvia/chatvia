/**
 * Shape of an agent function (LLM tool). Mirrors
 * App\Data\Resources\Agent\AgentFunctionData on the server.
 *
 * The canonical type will be regenerated into types/index.d.ts the next time
 * `php artisan typescript:transform` runs; this file is kept as a small
 * authoritative reference for code that imports it explicitly.
 */
export interface AgentFunctionHeader {
    key: string;
    value: string;
}

export type AuthType = 'none' | 'bearer' | 'header' | 'hmac';

export interface ParametersSchema {
    type: 'object';
    properties: Record<string, unknown>;
    required?: string[];
    [key: string]: unknown;
}

export interface AgentFunction {
    external_id: string;
    name: string;
    description: string;
    parameters_schema: ParametersSchema;
    webhook_url: string;
    webhook_method: 'POST' | 'GET';
    webhook_headers: AgentFunctionHeader[];
    auth_type: AuthType;
    timeout_ms: number;
    max_response_bytes: number;
    is_enabled: boolean;
    last_invoked_at: string | null;
    last_error: string | null;
    invocation_count: number;
    created_at: string;
}

export interface AgentFunctionDraft {
    name: string;
    description: string;
    parameters_schema: ParametersSchema;
    webhook_url: string;
    webhook_method: 'POST' | 'GET';
    webhook_headers: AgentFunctionHeader[];
    auth_type: AuthType;
    auth_value: string | null;
    timeout_ms: number;
    max_response_bytes: number;
    is_enabled: boolean;
}

export const DEFAULT_PARAMETERS_SCHEMA: ParametersSchema = {
    type: 'object',
    properties: {},
    required: [],
};

export const EMPTY_FUNCTION_DRAFT: AgentFunctionDraft = {
    name: '',
    description: '',
    parameters_schema: DEFAULT_PARAMETERS_SCHEMA,
    webhook_url: '',
    webhook_method: 'POST',
    webhook_headers: [],
    auth_type: 'none',
    auth_value: null,
    timeout_ms: 10000,
    max_response_bytes: 65536,
    is_enabled: true,
};
