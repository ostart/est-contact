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

    public function takeToWorkAction(): Action
    {
        $contact = $this->resolveContact();
        $status = $this->resolveStatus($contact);
        $isTakeToWork = ContactReopenService::usesTakeToWorkLabel($status);

        $label = $isTakeToWork ? 'Взять в работу' : 'Вернуть в работу';
        $submitLabel = $isTakeToWork ? 'Взять' : 'Вернуть';

        return Action::make('takeToWork')
            ->label($label)
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('primary')
            ->size(Size::Small)
            ->requiresConfirmation()
            // Confirmation modals need wire:click on submit; formWrapper(true) breaks it.
            ->formWrapper(false)
            ->modalIcon('heroicon-o-arrow-uturn-left')
            ->modalIconColor('primary')
            ->modalHeading($label)
            ->modalDescription('Контакт будет назначен вам и переведён в статус «В работе».')
            ->modalSubmitActionLabel($submitLabel)
            ->modalCancelActionLabel('Отмена')
            ->modalWidth(Width::Medium)
            ->action(function (): void {
                $this->takeToWork();
            });
    }

    public function takeToWork(): void
    {
        $user = auth()->user();

        if (! $user?->hasRole('leader')) {
            Notification::make()
                ->title('Недостаточно прав')
                ->danger()
                ->send();

            return;
        }

        $contact = $this->resolveContact();
        $status = $this->resolveStatus($contact);

        if (! ContactReopenService::isTakeToWorkSource($status)) {
            Notification::make()
                ->title('Контакт уже нельзя взять в работу из текущего статуса')
                ->warning()
                ->send();

            $this->redirect(ContactResource::getUrl('view', ['record' => $contact]));

            return;
        }

        try {
            ContactReopenService::takeToWork($contact, $user);
        } catch (ValidationException $exception) {
            Notification::make()
                ->title('Не удалось перевести в работу')
                ->body(collect($exception->errors())->flatten()->first() ?: 'Недопустимый переход статуса.')
                ->danger()
                ->send();

            return;
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('Не удалось перевести в работу')
                ->body('Произошла ошибка. Попробуйте ещё раз.')
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title(ContactReopenService::usesTakeToWorkLabel($status)
                ? 'Контакт взят в работу'
                : 'Контакт возвращён в работу')
            ->success()
            ->send();

        $this->redirect(ContactResource::getUrl('view', ['record' => $contact]));
    }

    public function render(): View
    {
        return view('livewire.contact-reopen-button');
    }

    protected function resolveContact(): Contact
    {
        return Contact::query()->findOrFail($this->contactId);
    }

    protected function resolveStatus(Contact $contact): ContactStatus
    {
        return $contact->status instanceof ContactStatus
            ? $contact->status
            : ContactStatus::from((string) $contact->status);
    }
}
