<?php

namespace TelegramBotEssentials\GatewayCard\Web\Manage;

use BackedEnum;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use TelegramBotEssentials\Billing\Models\Invoice;
use TelegramBotEssentials\Essence\Models\BotUser;
use TelegramBotEssentials\Essence\Models\TelegramUser;
use TelegramBotEssentials\Essence\Web\Filament\PanelResource;
use TelegramBotEssentials\GatewayCard\Models\ToCardAttempt;
use TelegramBotEssentials\GatewayCard\Services\CardReview;
use TelegramBotEssentials\GatewayCard\Web\Widgets\CardPaymentStats;

/**
 * Card-to-card payments, waiting for review first. Accepting and rejecting go
 * through CardReview, as the buttons in the transactions chat do.
 */
class CardPaymentResource extends PanelResource
{
    protected static ?string $model = ToCardAttempt::class;

    protected static ?string $ability = 'gateway-card.payments.manage';

    protected static ?string $slug = 'card-payments';

    // An attempt has no bot of its own: it belongs to its invoice's.
    protected static bool $isScopedToTenant = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    public static function getNavigationLabel(): string
    {
        return (string) __('tbe-gateway-card::web.nav');
    }

    public static function getModelLabel(): string
    {
        return (string) __('tbe-gateway-card::web.payment');
    }

    public static function getPluralModelLabel(): string
    {
        return (string) __('tbe-gateway-card::web.nav');
    }

    /** @return Builder<ToCardAttempt> */
    public static function getEloquentQuery(): Builder
    {
        $botId = Filament::getTenant()?->getKey();

        return ToCardAttempt::query()
            ->whereHas('invoice', fn (Builder $invoice): Builder => $invoice->where('bot_id', $botId))
            ->with('invoice.botUser.telegramUser');
    }

    public static function getNavigationBadge(): ?string
    {
        $waiting = self::waiting(static::getEloquentQuery())->count();

        return $waiting > 0 ? (string) $waiting : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    /**
     * @param  Builder<ToCardAttempt>  $query
     * @return Builder<ToCardAttempt>
     */
    public static function waiting(Builder $query): Builder
    {
        return $query->whereNull('status')->whereNotNull('received_at');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('member')->label((string) __('tbe-gateway-card::web.member'))
                    ->state(fn (ToCardAttempt $record): string => self::memberName(CardReview::buyerOf($record))),
                TextColumn::make('amount')->label((string) __('tbe-gateway-card::web.amount'))
                    ->formatStateUsing(fn (mixed $state): string => self::money($state))->sortable(),
                TextColumn::make('status')->label((string) __('tbe-gateway-card::web.status'))
                    ->state(fn (ToCardAttempt $record): string => self::statusLabel($record))
                    ->badge()
                    ->color(fn (ToCardAttempt $record): string => self::statusColor($record)),
                TextColumn::make('info_text')->label((string) __('tbe-gateway-card::web.receipt'))->limit(40)->placeholder('—'),
                TextColumn::make('received_at')->label((string) __('tbe-gateway-card::web.sent'))->since()->sortable()->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('state')
                    ->label((string) __('tbe-gateway-card::web.status'))
                    ->options([
                        'waiting' => (string) __('tbe-gateway-card::web.statuses.waiting'),
                        'succeed' => (string) __('tbe-gateway-card::web.statuses.succeed'),
                        'failed' => (string) __('tbe-gateway-card::web.statuses.failed'),
                    ])
                    ->default('waiting')
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'waiting' => self::waiting($query),
                        'succeed', 'failed' => $query->where('status', $data['value']),
                        default => $query,
                    }),
            ])
            ->defaultSort('id', 'desc')
            ->recordUrl(fn (ToCardAttempt $record): string => ViewCardPayment::getUrl(['record' => $record]));
    }

    public static function statusLabel(ToCardAttempt $attempt): string
    {
        $status = $attempt->status ?? ($attempt->received_at !== null ? 'waiting' : 'unpaid');

        return (string) __('tbe-gateway-card::web.statuses.'.$status);
    }

    public static function statusColor(ToCardAttempt $attempt): string
    {
        return match ($attempt->status) {
            'succeed' => 'success',
            'failed' => 'danger',
            null => $attempt->received_at !== null ? 'warning' : 'gray',
            default => 'gray',
        };
    }

    public static function money(mixed $amount): string
    {
        return currency()->priceFormat(is_numeric($amount) ? (string) $amount : '0');
    }

    public static function memberName(?BotUser $user): string
    {
        $telegramUser = $user?->telegramUser;
        if (! $telegramUser instanceof TelegramUser) {
            return '?';
        }
        if (is_string($telegramUser->username) && $telegramUser->username !== '') {
            return '@'.$telegramUser->username;
        }
        $name = CardReview::fullName($user);

        return $name !== '' ? $name : (string) $telegramUser->peer_id;
    }

    public static function invoiceOf(ToCardAttempt $attempt): ?Invoice
    {
        $invoice = $attempt->invoice;

        return $invoice instanceof Invoice ? $invoice : null;
    }

    public static function getWidgets(): array
    {
        return [CardPaymentStats::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCardPayments::route('/'),
            'view' => ViewCardPayment::route('/{record}'),
        ];
    }
}
