<?php

namespace App\ApiKey\Actions;

use App\ApiKey\Data\CreateApiKeyData;
use App\ApiKey\Data\CreatedApiKeyData;

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
