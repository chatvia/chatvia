<?php

namespace App\Identity\ApiKey\Actions;

use App\Identity\ApiKey\Data\CreateApiKeyData;
use App\Identity\ApiKey\Data\CreatedApiKeyData;

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
