<?php

namespace TelegramBotEssentials\GatewayCard\Web\Manage;

use Filament\Resources\Pages\ListRecords;
use TelegramBotEssentials\Essence\Web\Filament\Concerns\GuardsResourcePage;

class ListUnmatchedSms extends ListRecords
{
    use GuardsResourcePage;

    protected static string $resource = UnmatchedSmsResource::class;
}
