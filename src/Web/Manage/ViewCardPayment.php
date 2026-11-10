<?php

namespace TelegramBotEssentials\GatewayCard\Web\Manage;

use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;
use TelegramBotEssentials\Essence\Web\Filament\Concerns\GuardsResourcePage;
use TelegramBotEssentials\GatewayCard\Models\ToCardAttempt;
use TelegramBotEssentials\GatewayCard\Services\CardReview;
use TelegramBotEssentials\GatewayCard\Telegram\Features\Member\CardPaymentFeature;

/** One card payment: what the member sent as proof, and the review. */
class ViewCardPayment extends Page
{
    use GuardsResourcePage;
    use InteractsWithRecord;

    protected static string $resource = CardPaymentResource::class;

    protected string $view = 'tbe-gateway-card::web.payment';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function attempt(): ToCardAttempt
    {
        return CardPaymentResource::getEloquentQuery()->whereKey($this->getRecord()->getKey())->firstOrFail();
    }

    public function getTitle(): string
    {
        $id = $this->getRecord()->getKey();

        return (string) __('tbe-gateway-card::web.title', ['id' => is_scalar($id) ? (string) $id : '']);
    }

    /** The receipt photo as a data URI: it lives on a private disk, never at a public URL. */
    public function photo(): ?string
    {
        $photo = $this->attempt()->info_photo;

        return is_string($photo) && $photo !== '' ? 'data:image/jpeg;base64,'.base64_encode($photo) : null;
    }

    private function reviewable(): bool
    {
        return CardPaymentFeature::isCardPaymentEnabled() && app(CardReview::class)->isPending($this->attempt());
    }

    private function alreadyHandled(Action $action): void
    {
        Notification::make()->title((string) __('tbe-gateway-card::invoice.to_card.answers.admin-already_handled'))->warning()->send();
        $action->cancel();
    }

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('accept')
                ->label((string) __('tbe-gateway-card::web.accept'))
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription(fn (): string => (string) __('tbe-gateway-card::web.accept_confirm', ['amount' => CardPaymentResource::money($this->attempt()->amount)]))
                ->visible(fn (): bool => $this->reviewable())
                ->action(function (Action $action, CardReview $review): void {
                    if (! $review->accept($this->attempt(), wHook()->user())) {
                        $this->alreadyHandled($action);

                        return;
                    }

                    Notification::make()->title((string) __('tbe-gateway-card::invoice.to_card.answers.admin-payment_accepted'))->success()->send();
                }),
            Action::make('reject')
                ->label((string) __('tbe-gateway-card::web.reject'))
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger')
                ->visible(fn (): bool => $this->reviewable())
                ->schema([
                    Textarea::make('reason')->label((string) __('tbe-gateway-card::web.reason'))
                        ->helperText((string) __('tbe-gateway-card::web.reason_hint'))
                        ->required()->maxLength(255)->rows(3),
                ])
                ->action(function (array $data, Action $action, CardReview $review): void {
                    $reason = is_string($data['reason'] ?? null) ? trim($data['reason']) : '';
                    if (! $review->reject($this->attempt(), wHook()->user(), $reason)) {
                        $this->alreadyHandled($action);

                        return;
                    }

                    Notification::make()->title((string) __('tbe-gateway-card::invoice.to_card.text.admin-payment_rejected'))->success()->send();
                }),
        ];
    }
}
