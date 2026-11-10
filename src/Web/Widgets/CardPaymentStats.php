<?php

namespace TelegramBotEssentials\GatewayCard\Web\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use TelegramBotEssentials\Essence\Web\Filament\Concerns\GuardsWidget;
use TelegramBotEssentials\Essence\Web\Filament\DailySeries;
use TelegramBotEssentials\GatewayCard\Web\Manage\CardPaymentResource;
use TelegramBotEssentials\GatewayCard\Web\Manage\UnmatchedSmsResource;

/**
 * What waits for a reviewer, and what was accepted this month. Nothing sets
 * accepted_at, so acceptance is read from when the attempt last changed.
 */
class CardPaymentStats extends StatsOverviewWidget
{
    use GuardsWidget;

    protected static ?string $ability = 'gateway-card.payments.manage';

    protected static ?int $sort = 9;

    // Drawn with the page above the payment list.
    protected static bool $isLazy = false;

    /** @return array<Stat> */
    protected function getStats(): array
    {
        $accepted = DailySeries::of(CardPaymentResource::getEloquentQuery()->where('status', 'succeed'), 'updated_at', 30, 'amount');
        $rejected = DailySeries::of(CardPaymentResource::getEloquentQuery()->where('status', 'failed'), 'updated_at');
        $last30 = (string) __('tbe-gateway-card::web.stats.last_30');

        return [
            Stat::make((string) __('tbe-gateway-card::web.stats.waiting'), number_format(CardPaymentResource::waiting(CardPaymentResource::getEloquentQuery())->count()))
                ->description((string) __('tbe-gateway-card::web.stats.sms', ['count' => number_format(UnmatchedSmsResource::getEloquentQuery()->count())]))
                ->color('warning'),
            Stat::make((string) __('tbe-gateway-card::web.stats.accepted'), CardPaymentResource::money(array_sum($accepted)))
                ->description($last30)->chart(array_values($accepted))->color('success'),
            Stat::make((string) __('tbe-gateway-card::web.stats.rejected'), number_format(array_sum($rejected)))
                ->description($last30)->chart(array_values($rejected))->color('danger'),
        ];
    }
}
