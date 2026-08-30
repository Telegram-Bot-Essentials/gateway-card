<?php

namespace TelegramBotEssentials\GatewayCard\Telegram\Features\Member;

class CardPaymentFeature
{
    public static string $type = 'CARD_PAYMENT';

    public static function isCardPaymentEnabled(): bool
    {
        if (
            ! settings()->get('billing.gateways.card.status') ||
            ! settings()->get('billing.gateways.card.card_number') ||
            ! settings()->get('billing.gateways.card.card_name') ||
            ! settings()->get('billing.gateways.card.transactions_chat_id')
        ) {
            return false;
        }

        return true;
    }
}
