<?php

declare(strict_types=1);

namespace TelegramBotEssentials\GatewayCard\Tests;

use TelegramBotEssentials\Billing\TbeBillingServiceProvider;
use TelegramBotEssentials\Essence\Testing\WebTestCase as EssenceWebTestCase;
use TelegramBotEssentials\GatewayCard\TbeGatewayCardServiceProvider;
use TelegramBotEssentials\Settings\TbeSettingsServiceProvider;

abstract class WebTestCase extends EssenceWebTestCase
{
    protected function getPackageProviders($app): array
    {
        return array_merge(parent::getPackageProviders($app), [
            TbeSettingsServiceProvider::class,
            TbeBillingServiceProvider::class,
            TbeGatewayCardServiceProvider::class,
        ]);
    }
}
