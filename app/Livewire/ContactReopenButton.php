<?php

namespace App\Livewire;

use App\Enums\ContactStatus;
use App\Filament\Resources\ContactResource;
use App\Models\Contact;
use App\Support\ContactReopenService;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Enums\Size;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Throwable;

class ContactReopenButton extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    public int $contactId;

    public function mount(int $contactId): void
    {
        $this->contactId = $contactId;
    }

    public function reopenFailedAction(): Action
    {
        return Action::make('reopenFailed')
            ->label('Вернуть в работу')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('primary')
            ->size(Size::Small)
            ->requiresConfirmation()
            // Confirmation modals need wire:click on submit; formWrapper(true) breaks it.
            ->formWrapper(false)
            ->modalIcon('heroicon-o-arrow-uturn-left')
            ->modalIconColor('primary')
            ->modalHeading('Вернуть в работу')
            ->modalDescription('Контакт будет назначен вам и переведён в статус «В работе».')
            ->modalSubmitActionLabel('Вернуть')
            ->modalCancelActionLabel('Отмена')
            ->modalWidth(Width::Medium)
            ->action(function (): void {
                $this->reopen();
            });
    }

    public function reopen(): void
    {
        $user = auth()->user();

        if (! $user?->hasRole('leader')) {
            Notification::make()
                ->title('Недостаточно прав')
                ->danger()
                ->send();

            return;
        }

        $contact = Contact::query()->findOrFail($this->contactId);

        $status = $contact->status instanceof ContactStatus
            ? $contact->status
            : ContactStatus::from((string) $contact->status);

        if ($status !== ContactStatus::FAILED) {
            Notification::make()
                ->title('Контакт уже не в статусе «Отказ»')
                ->warning()
                ->send();

            $this->redirect(ContactResource::getUrl('view', ['record' => $contact]));

            return;
        }

        try {
            ContactReopenService::reopenFromFailed($contact, $user);
        } catch (ValidationException $exception) {
            Notification::make()
                ->title('Не удалось вернуть в работу')
                ->body(collect($exception->errors())->flatten()->first() ?: 'Недопустимый переход статуса.')
                ->danger()
                ->send();

            return;
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('Не удалось вернуть в работу')
                ->body('Произошла ошибка. Попробуйте ещё раз.')
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title('Контакт взят в работу')
            ->success()
            ->send();

        $this->redirect(ContactResource::getUrl('view', ['record' => $contact]));
    }

    public function render(): View
    {
        return view('livewire.contact-reopen-button');
    }
}
