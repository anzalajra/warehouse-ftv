<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use App\Models\CustomerDocument;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditCustomer extends EditRecord
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('verify')
                ->label('Verify Customer')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Verify Customer')
                ->modalDescription('Are you sure you want to verify this customer? This will allow them to make rentals.')
                ->visible(fn () => !$this->record->is_verified)
                ->action(function () {
                    $this->record->documents()
                        ->where('status', CustomerDocument::STATUS_PENDING)
                        ->update([
                            'status' => CustomerDocument::STATUS_APPROVED,
                            'verified_by' => Auth::id(),
                            'verified_at' => now(),
                        ]);

                    $this->record->verify(Auth::id());

                    Notification::make()
                        ->title('Customer verified successfully')
                        ->success()
                        ->send();
                }),

            Action::make('resetPassword')
                ->label('Reset Password')
                ->icon('heroicon-o-key')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Reset Password')
                ->modalDescription('Are you sure you want to reset this customer\'s password to "resetpassword"?')
                ->action(function () {
                    $this->record->update([
                        'password' => 'resetpassword',
                    ]);

                    Notification::make()
                        ->title('Password reset successfully')
                        ->success()
                        ->send();
                }),

            Action::make('unverify')
                ->label('Revoke Verification')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Revoke Verification')
                ->modalDescription('Are you sure you want to revoke this customer\'s verification?')
                ->visible(fn () => $this->record->is_verified)
                ->action(function () {
                    $this->record->documents()
                        ->where('status', CustomerDocument::STATUS_APPROVED)
                        ->update([
                            'status' => CustomerDocument::STATUS_PENDING,
                            'verified_by' => null,
                            'verified_at' => null,
                        ]);

                    $this->record->unverify();

                    Notification::make()
                        ->title('Verification revoked')
                        ->success()
                        ->send();
                }),

            Action::make('impersonate')
                ->label('Impersonate User')
                ->icon('heroicon-o-arrow-right-on-rectangle')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Impersonate User')
                ->modalDescription('Anda akan masuk ke akun customer ini tanpa password. Gunakan hanya untuk maintenance / cek kesalahan customer. Semua aksi yang Anda lakukan akan tercatat atas nama customer tersebut.')
                ->url(fn () => route('impersonate.start', ['user' => $this->record->id]))
                ->openUrlInNewTab(),

            Action::make('save')
                ->label('Save Changes')
                ->action('save')
                ->keyBindings(['mod+s']),

            $this->getCancelFormAction(),

            DeleteAction::make(),
        ];
    }

    protected function getFormActions(): array
    {
        return [];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
