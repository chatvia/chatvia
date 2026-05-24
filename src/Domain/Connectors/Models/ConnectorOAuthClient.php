<?php

namespace Domain\Connectors\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Per-auth-server DCR registration. We register ChatVia as an OAuth client
 * once per upstream authorization server (RFC 7591) and reuse the same
 * client_id for every agent connecting that service.
 */
class ConnectorOAuthClient extends Model
{
    use HasFactory;

    protected $table = 'connector_oauth_clients';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'client_secret' => 'encrypted',
            'registration_access_token' => 'encrypted',
            'metadata' => 'array',
        ];
    }
}
