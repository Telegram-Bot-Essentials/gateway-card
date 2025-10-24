<?php

namespace TelegramBotEssentials\GatewayCard\Telegram\CallbackQueries\Admin;

use Illuminate\Contracts\Container\BindingResolutionException;
use Telegram\Bot\Exceptions\TelegramSDKException;
use TelegramBotEssentials\Essence\Enums\Roles;
use TelegramBotEssentials\Essence\Exceptions\LogicException;
use TelegramBotEssentials\Essence\Telegram\CallbackQueries\CallbackQuery;
use TelegramBotEssentials\GatewayCard\Models\ToCardAttempt;
use TelegramBotEssentials\GatewayCard\Telegram\Features\Member\CardPaymentFeature;

class ManageCardPaymentQuery extends CallbackQuery
{
    protected string $type = 'MANAGE_CARD_PAYMENT';
    protected int $perm = Roles::ADMIN->value;

    function acceptCardPayment(ToCardAttempt $toCardAttempt): void
    {
        $toCardAttempt->attemptSucceed();
        $toCardAttempt->messageMeta->lockAction(__('tbe-gateway-card::invoice.to_card.lock-keys.admin-payment_accepted_by', [
            'adminName' => wHook()->user()->telegramUser->full_name]), customEmoji: "✅");
        $this->answer(__('tbe-gateway-card::invoice.to_card.answers.admin-payment_accepted'));
    }

    /**
     * @param ToCardAttempt $toCardAttempt
     * @throws BindingResolutionException
     * @throws TelegramSDKException
     * @throws LogicException
     */
    function rejectCardPayment(ToCardAttempt $toCardAttempt): void
    {
        wHook()->user()->changeState(
            encodeAnswerState(
                $this->type,
                "reject_reason",
                [
                    "toCardAttempt" => $toCardAttempt->id
                ]
            )
        );
        $toCardAttempt->messageMeta->lockAction(__('tbe-gateway-card::invoice.to_card.lock-keys.admin-rejecting_payment'));

        $text = __('tbe-gateway-card::invoice.to_card.text.admin_payment_rejection', [
            'toCardAttemptId' => $toCardAttempt->id,
        ]);

        wHook()->api()->sendMessage([
            'chat_id' => wHook()->user()->telegramUser->peer_id,
            'text' => $text,
            'reply_markup' => wHook()->user()->getKeyboard(),
            'reply_to_message_id' => $toCardAttempt->messageMeta->message_id,
        ]);
        $this->answer(__('tbe-gateway-card::invoice.to_card.answers.admin-rejecting_payment'));
    }

    function isEnabled(): bool
    {
        return CardPaymentFeature::isCardPaymentEnabled();
    }
}
