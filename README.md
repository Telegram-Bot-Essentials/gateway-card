# Telegram Bot Essentials — Gateway: Card

[![Latest Version](https://img.shields.io/packagist/v/telegram-bot-essentials/gateway-card.svg)](https://packagist.org/packages/telegram-bot-essentials/gateway-card)
[![tests](https://github.com/Telegram-Bot-Essentials/gateway-card/actions/workflows/tests.yml/badge.svg)](https://github.com/Telegram-Bot-Essentials/gateway-card/actions/workflows/tests.yml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

A manual "pay by bank card transfer" gateway for
[`telegram-bot-essentials/billing`](https://github.com/Telegram-Bot-Essentials/billing).
There's no external payment processor: the buyer transfers money to a card number shown in
the bot, sends a receipt back, and an admin manually accepts or rejects the payment.

This is the simplest possible gateway implementation and a good reference for writing your
own manual/offline gateway.

## Installation

```bash
composer require telegram-bot-essentials/gateway-card
php artisan migrate
```

No config file — everything is per-bot [Settings](https://github.com/Telegram-Bot-Essentials/settings):

| Setting key | Type | Purpose |
|---|---|---|
| `billing.gateways.card.status` | `CHECKBOX` | Master on/off switch |
| `billing.gateways.card.card_number` | `TEXT` | Card number shown to buyers |
| `billing.gateways.card.card_name` | `TEXT` | Cardholder name shown to buyers |
| `billing.gateways.card.transactions_chat_id` | `TEXT` | Chat where admins review receipts |

All four must be set for the gateway to appear on the invoice screen.

## Flow

1. Buyer picks "Card" on the Billing invoice screen → a `ToCardAttempt` is created and the
   card details are shown.
2. Buyer sends a text/photo receipt → it's forwarded to `transactions_chat_id` with
   Accept / Reject buttons.
3. Admin accepts → `attemptSucceed()` → `Invoice::markAsPaid()` → Billing's paid hooks fire.
   Admin rejects → prompts for a reason → `attemptFailed()` → `Invoice::markAsFailed()`.

## Documentation

Full documentation, including the `ToCardAttempt` model and a step-by-step template for
building your own manual gateway, lives on the Telegram Bot Essentials documentation site
under **Modules → Gateway: Card**.

## License

[MIT](LICENSE).
