<?php

namespace TelegramBotEssentials\GatewayCard\Telegram\StateAnswers\Admin;

use Illuminate\Contracts\Container\BindingResolutionException;
use Telegram\Bot\Exceptions\TelegramSDKException;
use TelegramBotEssentials\Essence\Enums\ChatScope;
use TelegramBotEssentials\Essence\Exceptions\LogicException;
use TelegramBotEssentials\Essence\Telegram\StateAnswers\StateAnswer;
use TelegramBotEssentials\GatewayCard\Models\ToCardAttempt;
use TelegramBotEssentials\GatewayCard\Telegram\Features\Member\CardPaymentFeature;

class ManageCardPaymentAnswer extends StateAnswer
{
    protected string $type = 'MANAGE_CARD_PAYMENT';

    protected ?string $ability = 'gateway-card.payments.manage';

    protected ChatScope $chatScope = ChatScope::Any;

    /**
     * @throws BindingResolutionException
     * @throws LogicException
     * @throws TelegramSDKException
     */
    public function rejectReason(ToCardAttempt $toCardAttempt): void
    {
        $toCardAttempt->attemptFailed();

        $toCardAttempt->reject_reason = wHook()->update()->message->text;
        $toCardAttempt->save();

        tbeLog('gateway-card')->audit('Rejected card payment #{attempt_id} of {amount}: {reject_reason}', [
            'attempt_id' => $toCardAttempt->getKey(),
            'amount' => $toCardAttempt->amount,
            'reject_reason' => $toCardAttempt->reject_reason,
        ]);
        wHook()->conversation()->changeState();

        // Reviewing from a channel, the reason comes from the private chat, and
        // a reply cannot point at a message in another chat.
        $inReviewChat = (int) $toCardAttempt->messageMeta->chat_id === (int) wHook()->update()->message->chat->id;

        wHook()->api()->sendMessage([
            ...wHook()->replyTo(),
            'text' => __('tbe-gateway-card::invoice.to_card.text.admin-payment_rejected'),
            ...($inReviewChat ? ['reply_to_message_id' => $toCardAttempt->messageMeta->message_id] : []),
            'reply_markup' => replyKeyboard(),
        ]);

        $text = __('tbe-gateway-card::invoice.to_card.text.user-payment_rejected', [
            'rejectionReason' => $toCardAttempt->reject_reason,
        ]);

        wHook()->api()->sendMessage([
            'chat_id' => $toCardAttempt->invoice->botUser->telegramUser->peer_id,
            'text' => $text,
            ...$toCardAttempt->invoice->replyToView(),
        ]);

        $toCardAttempt->messageMeta->lockAction(__('tbe-gateway-card::invoice.to_card.lock-keys.admin-payment_rejected_by', [
            'adminName' => wHook()->user()->telegramUser->full_name]), customEmoji: '❌');
        $toCardAttempt->invoice->messageMeta->lockAction(__('tbe-gateway-card::invoice.to_card.lock-keys.user-payment_rejected'), '❌');
    }

    public function cancel(): void
    {
        $toCardAttempt = ToCardAttempt::findOrFail($this->params['toCardAttempt']);
        $toCardAttempt->messageMeta->revertAction();
    }

    public function isEnabled(): bool
    {
        return CardPaymentFeature::isCardPaymentEnabled();
    }
}
