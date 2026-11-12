<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;

/*
 * A card number or review chat that cannot work is refused when the admin
 * saves it, not discovered when a member tries to pay.
 */

beforeEach(function () {
    $this->bot = $this->makeBot();
});

it('stores a card number typed with Persian digits, spaces and dashes as plain digits', function (string $input) {
    settings()->set('billing.gateways.card.card_number', $input, $this->bot);

    expect(settings()->get('billing.gateways.card.card_number', $this->bot))->toBe('6037997512345678');
})->with([
    'plain' => '6037997512345678',
    'spaced' => '6037 9975 1234 5678',
    'dashed' => '6037-9975-1234-5678',
    'persian' => '۶۰۳۷۹۹۷۵۱۲۳۴۵۶۷۸',
    'arabic-indic' => '٦٠٣٧٩٩٧٥١٢٣٤٥٦٧٨',
]);

it('refuses a card number that is not 16 digits', function (string $input) {
    expect(fn () => settings()->set('billing.gateways.card.card_number', $input, $this->bot))
        ->toThrow(ValidationException::class);

    expect(settings()->get('billing.gateways.card.card_number', $this->bot))->toBeNull();
})->with([
    'letters' => 'afasdasdgf',
    'too short' => '603799751234567',
    'too long' => '60379975123456789',
]);

it('stores a chat id, a negative group or channel id included', function (string $input, string $stored) {
    settings()->set('billing.gateways.card.transactions_chat_id', $input, $this->bot);

    expect(settings()->get('billing.gateways.card.transactions_chat_id', $this->bot))->toBe($stored);
})->with([
    'private' => ['123456789', '123456789'],
    'channel' => ['-1001234567890', '-1001234567890'],
    'padded persian' => [' -۱۰۰۱۲۳ ', '-100123'],
]);

it('refuses a chat id that is not a number', function (string $input) {
    expect(fn () => settings()->set('billing.gateways.card.transactions_chat_id', $input, $this->bot))
        ->toThrow(ValidationException::class);
})->with([
    'text' => 'my channel',
    'username' => '@mychannel',
    'decimal' => '12.5',
]);
