<?php

declare(strict_types=1);

use TelegramBotEssentials\GatewayCard\Telegram\Features\Member\CardPaymentFeature;
use TelegramBotEssentials\Settings\Services\Settings;

// isCardPaymentEnabled() reads four per-bot settings; settings() resolves
// the bot from the webhook context, so point one at a fresh bot first.
beforeEach(function () {
    wHook()->setBot($this->makeBot());
});

it('is disabled until every card setting is filled in', function () {
    expect(CardPaymentFeature::isCardPaymentEnabled())->toBeFalse();

    $settings = app(Settings::class);
    $settings->set('billing.gateways.card.status', true);
    $settings->set('billing.gateways.card.card_number', '6037-9900-0000-0000');
    $settings->set('billing.gateways.card.card_name', 'Jane Doe');

    // Still missing the transactions chat id.
    expect(CardPaymentFeature::isCardPaymentEnabled())->toBeFalse();

    $settings->set('billing.gateways.card.transactions_chat_id', '-1001234567890');

    expect(CardPaymentFeature::isCardPaymentEnabled())->toBeTrue();
});

it('is disabled again when the toggle is switched off', function () {
    $settings = app(Settings::class);
    $settings->set('billing.gateways.card.status', true);
    $settings->set('billing.gateways.card.card_number', '6037-9900-0000-0000');
    $settings->set('billing.gateways.card.card_name', 'Jane Doe');
    $settings->set('billing.gateways.card.transactions_chat_id', '-1001234567890');
    expect(CardPaymentFeature::isCardPaymentEnabled())->toBeTrue();

    $settings->set('billing.gateways.card.status', false);

    expect(CardPaymentFeature::isCardPaymentEnabled())->toBeFalse();
});
