<?php

namespace Domain\Auth\Actions;

use Domain\Auth\Data\CreateApiKeyData;
use Domain\Auth\Data\CreatedApiKeyData;

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
