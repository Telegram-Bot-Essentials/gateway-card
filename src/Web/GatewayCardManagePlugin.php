<?php

namespace TelegramBotEssentials\GatewayCard\Web;

use Filament\Contracts\Plugin;
use Filament\Panel;
use TelegramBotEssentials\GatewayCard\Web\Manage\CardPaymentResource;
use TelegramBotEssentials\GatewayCard\Web\Manage\UnmatchedSmsResource;
use TelegramBotEssentials\GatewayCard\Web\Widgets\CardPaymentStats;

class GatewayCardManagePlugin implements Plugin
{
    public function getId(): string
    {
        return 'tbe-gateway-card-manage';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([CardPaymentResource::class, UnmatchedSmsResource::class]);
        $panel->widgets([CardPaymentStats::class]);
    }

    public function boot(Panel $panel): void {}
}
