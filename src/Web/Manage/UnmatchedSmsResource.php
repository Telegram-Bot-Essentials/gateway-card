<?php

namespace TelegramBotEssentials\GatewayCard\Web\Manage;

use BackedEnum;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use TelegramBotEssentials\Essence\Web\Filament\PanelDates;
use TelegramBotEssentials\Essence\Web\Filament\PanelGroup;
use TelegramBotEssentials\Essence\Web\Filament\PanelResource;
use TelegramBotEssentials\GatewayCard\Models\UnmatchedCardSms;
use TelegramBotEssentials\GatewayCard\Services\CardSmsMatcher;
use UnitEnum;

/**
 * Bank SMS that matched no single pending payment, kept for the matching
 * window in case the member's proof arrives later. Read only.
 */
class UnmatchedSmsResource extends PanelResource
{
    protected static ?string $model = UnmatchedCardSms::class;

    protected static ?string $ability = 'gateway-card.payments.manage';

    protected static ?string $slug = 'card-sms';

    // Not tenant-scoped in the model either: filtered by bot below.
    protected static bool $isScopedToTenant = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleBottomCenterText;

    protected static string|UnitEnum|null $navigationGroup = PanelGroup::Sales;

    public static function getNavigationLabel(): string
    {
        return (string) __('tbe-gateway-card::web.sms_nav');
    }

    public static function getModelLabel(): string
    {
        return (string) __('tbe-gateway-card::web.sms');
    }

    public static function getPluralModelLabel(): string
    {
        return (string) __('tbe-gateway-card::web.sms_nav');
    }

    /** @return Builder<UnmatchedCardSms> */
    public static function getEloquentQuery(): Builder
    {
        return UnmatchedCardSms::query()->where('bot_id', Filament::getTenant()?->getKey());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->description((string) __('tbe-gateway-card::web.sms_hint', ['minutes' => CardSmsMatcher::MATCH_WINDOW_MINUTES]))
            ->columns([
                TextColumn::make('amount')->label((string) __('tbe-gateway-card::web.amount'))
                    ->formatStateUsing(fn (mixed $state): string => CardPaymentResource::money($state))->sortable(),
                TextColumn::make('raw_text')->label((string) __('tbe-gateway-card::web.sms_text'))->wrap()->limit(200),
                PanelDates::since(TextColumn::make('received_at'))->label((string) __('tbe-gateway-card::web.received'))->sortable(),
            ])
            ->defaultSort('received_at', 'desc')
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->emptyStateHeading((string) __('tbe-gateway-card::web.empty.sms'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUnmatchedSms::route('/'),
        ];
    }
}
