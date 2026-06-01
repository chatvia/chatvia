<?php

namespace App\Auth\Actions\ApiKeys;

use App\Auth\Data\ApiKeys\CreateApiKeyData;
use App\Auth\Data\ApiKeys\CreatedApiKeyData;

class CreateApiKeyAction
{
    public function execute(CreateApiKeyData $data): CreatedApiKeyData
    {
        $token = $data->user->createToken($data->name);

        return new CreatedApiKeyData(
            id: $token->accessToken->id,
            name: $token->accessToken->name,
            plaintext: $token->plainTextToken,
        );
    }
}
