<?php

namespace App\Http\Requests\Tickets;

use App\Workspace\Models\User;
use Domain\Helpdesk\Data\CreateTicketData;
use Illuminate\Foundation\Http\FormRequest;

class CreateTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string'],
            'message' => ['required', 'string'],
            'priority' => ['required', 'string', 'in:low,medium,high,urgent'],
        ];
    }

    public function toData(): CreateTicketData
    {
        /** @var User $user */
        $user = $this->user();

        return new CreateTicketData(
            user: $user,
            title: $this->string('title')->toString(),
            message: $this->string('message')->toString(),
            priority: $this->string('priority')->toString(),
        );
    }
}
