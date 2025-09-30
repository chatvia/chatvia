<?php

namespace App\Filament\Admin\Resources\UserResource\Pages;

use App\Filament\Admin\Resources\UserResource;
use App\Models\UserQuota;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Basic Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('email')
                                    ->email()
                                    ->required()
                                    ->maxLength(255),
                            ]),
                        Grid::make(2)
                            ->schema([
                                Forms\Components\DateTimePicker::make('email_verified_at'),
                                Forms\Components\TextInput::make('password')
                                    ->password()
                                    ->dehydrated(fn ($state) => filled($state))
                                    ->maxLength(255)
                                    ->placeholder('Leave blank to keep current password'),
                            ]),
                    ]),

                Section::make('Limits & Quotas')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('agents_limit')
                                    ->label('Agents Limit')
                                    ->numeric()
                                    ->default(0),
                                Forms\Components\TextInput::make('pages_limit')
                                    ->label('Pages Limit')
                                    ->numeric()
                                    ->default(0),
                                Forms\Components\TextInput::make('links_limit')
                                    ->label('Links Limit')
                                    ->numeric()
                                    ->default(0),
                            ]),
                        Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('minutes_limit')
                                    ->label('Minutes Limit')
                                    ->numeric()
                                    ->default(0),
                                Forms\Components\TextInput::make('custom_domains_limit')
                                    ->label('Custom Domains Limit')
                                    ->numeric()
                                    ->default(0),
                                Forms\Components\TextInput::make('members_limit')
                                    ->label('Members Limit')
                                    ->numeric()
                                    ->default(0),
                            ]),
                    ]),
            ]);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Ensure quota relationship is loaded
        $this->record->load('quota');
        
        // Load quota data into the form with fallback defaults
        $quota = $this->record->quota;
        
        // Map quota data to form fields
        $data['agents_limit'] = $quota?->agents_limit ?? 0;
        $data['pages_limit'] = $quota?->pages_limit ?? 0;
        $data['links_limit'] = $quota?->links_limit ?? 0;
        $data['minutes_limit'] = $quota?->minutes_limit ?? 0;
        $data['custom_domains_limit'] = $quota?->custom_domains_limit ?? 0;
        $data['members_limit'] = $quota?->members_limit ?? 0;

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        // Extract quota fields
        $quotaFields = [
            'agents_limit',
            'pages_limit',
            'links_limit',
            'minutes_limit',
            'custom_domains_limit',
            'members_limit'
        ];
        
        $quotaData = [];
        $userData = [];
        
        foreach ($data as $key => $value) {
            if (in_array($key, $quotaFields)) {
                $quotaData[$key] = $value;
            } else {
                $userData[$key] = $value;
            }
        }

        // Hash password if provided
        if (!empty($userData['password'])) {
            $userData['password'] = bcrypt($userData['password']);
        } else {
            // Remove empty password to avoid overwriting with null
            unset($userData['password']);
        }

        // Update the user record
        $record->update($userData);

        // Update or create the quota record
        if (!empty($quotaData)) {
            $record->quota()->updateOrCreate(
                ['user_id' => $record->id],
                $quotaData
            );
        }

        return $record;
    }
}
