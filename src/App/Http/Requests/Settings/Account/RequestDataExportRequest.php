<?php

namespace App\Http\Requests\Settings\Account;

use App\Identity\Workspace\Data\DataExport\RequestDataExportData;
use App\Identity\Workspace\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class RequestDataExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    public function toData(): RequestDataExportData
    {
        /** @var User $user */
        $user = $this->user();

        return new RequestDataExportData(user: $user);
    }
}
