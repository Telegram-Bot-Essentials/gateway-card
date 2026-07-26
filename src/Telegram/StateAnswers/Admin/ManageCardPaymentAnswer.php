<?php

namespace TelegramBotEssentials\GatewayCard\Telegram\StateAnswers\Admin;

use Illuminate\Contracts\Container\BindingResolutionException;
use Telegram\Bot\Exceptions\TelegramSDKException;
use TelegramBotEssentials\Essence\Enums\Roles;
use TelegramBotEssentials\Essence\Exceptions\LogicException;
use TelegramBotEssentials\Essence\Telegram\StateAnswers\StateAnswer;
use TelegramBotEssentials\GatewayCard\Models\ToCardAttempt;
use TelegramBotEssentials\GatewayCard\Telegram\Features\Member\CardPaymentFeature;

class ManageCardPaymentAnswer extends StateAnswer
{
    protected string $type = 'MANAGE_CARD_PAYMENT';
    protected int $perm = Roles::ADMIN->value;

    /**
     * @throws BindingResolutionException
     * @throws LogicException
     * @throws TelegramSDKException
     */
    function rejectReason(ToCardAttempt $toCardAttempt): void
    {
        $toCardAttempt->attemptFailed();

        $toCardAttempt->reject_reason = wHook()->update()->message->text;
        $toCardAttempt->save();

        tbeLog('gateway-card')->warning('Card payment rejected by admin', [
            'attempt_id' => $toCardAttempt->getKey(),
            'amount' => $toCardAttempt->amount,
            'reject_reason' => $toCardAttempt->reject_reason,
        ]);
        wHook()->user()->changeState();

        wHook()->api()->sendMessage([
            'chat_id' => wHook()->update()->message->chat->id,
            'text' => __('tbe-gateway-card::invoice.to_card.text.admin-payment_rejected'),
            'reply_to_message_id' => $toCardAttempt->messageMeta->message_id,
            'reply_markup' => wHook()->user()->getKeyboard(),
        ]);

        $text = __('tbe-gateway-card::invoice.to_card.text.user-payment_rejected', [
            'rejectionReason' => $toCardAttempt->reject_reason
        ]);

        wHook()->api()->sendMessage([
            'chat_id' => $toCardAttempt->invoice->botUser->telegramUser->peer_id,
            'text' => $text,
        ]);

        $toCardAttempt->messageMeta->lockAction(__('tbe-gateway-card::invoice.to_card.lock-keys.admin-payment_rejected_by', [
            'adminName' => wHook()->user()->telegramUser->full_name]), customEmoji: "❌");
        $toCardAttempt->invoice->messageMeta->lockAction(__('tbe-gateway-card::invoice.to_card.lock-keys.user-payment_rejected'), '❌');
    }

    function cancel(): void
    {
        $toCardAttempt = ToCardAttempt::findOrFail($this->params['toCardAttempt']);
        $toCardAttempt->messageMeta->revertAction();
    }

    function isEnabled(): bool
    {
        return CardPaymentFeature::isCardPaymentEnabled();
    }
}
