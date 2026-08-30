<?php

declare(strict_types=1);

namespace TelegramBotEssentials\GatewayCard\Tests;

use TelegramBotEssentials\Billing\TbeBillingServiceProvider;
use TelegramBotEssentials\Essence\Testing\TestCase as EssenceTestCase;
use TelegramBotEssentials\GatewayCard\TbeGatewayCardServiceProvider;
use TelegramBotEssentials\Settings\TbeSettingsServiceProvider;

abstract class TestCase extends EssenceTestCase
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
