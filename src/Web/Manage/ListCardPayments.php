<?php

namespace TelegramBotEssentials\GatewayCard\Web\Manage;

use Filament\Resources\Pages\ListRecords;
use TelegramBotEssentials\Essence\Web\Filament\Concerns\GuardsResourcePage;
use TelegramBotEssentials\GatewayCard\Web\Widgets\CardPaymentStats;

class ListCardPayments extends ListRecords
{
    use GuardsResourcePage;

    protected static string $resource = CardPaymentResource::class;

    protected function getHeaderWidgets(): array
    {
        return [CardPaymentStats::class];
    }
}
