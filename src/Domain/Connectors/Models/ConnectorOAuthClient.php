<?php

namespace Domain\Connectors\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Per-auth-server DCR registration. We register ChatVia as an OAuth client
 * once per upstream authorization server (RFC 7591) and reuse the same
 * client_id for every agent connecting that service.
 *
 * @property int $id
 * @property string $auth_server_url
 * @property string $client_id
 * @property string|null $client_secret
 * @property string|null $registration_access_token
 * @property string|null $registration_client_uri
 * @property string $token_endpoint
 * @property string $authorization_endpoint
 * @property array<array-key, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ConnectorOAuthClient newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ConnectorOAuthClient newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ConnectorOAuthClient query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ConnectorOAuthClient whereAuthServerUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ConnectorOAuthClient whereAuthorizationEndpoint($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ConnectorOAuthClient whereClientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ConnectorOAuthClient whereClientSecret($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ConnectorOAuthClient whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ConnectorOAuthClient whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ConnectorOAuthClient whereMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ConnectorOAuthClient whereRegistrationAccessToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ConnectorOAuthClient whereRegistrationClientUri($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ConnectorOAuthClient whereTokenEndpoint($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ConnectorOAuthClient whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
#[Guarded(['id'])]
#[Table(name: 'connector_oauth_clients')]
class ConnectorOAuthClient extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'client_secret' => 'encrypted',
            'registration_access_token' => 'encrypted',
            'metadata' => 'array',
        ];
    }
}
