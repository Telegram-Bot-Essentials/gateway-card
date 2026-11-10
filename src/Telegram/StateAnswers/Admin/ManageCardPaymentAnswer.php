<?php

namespace TelegramBotEssentials\GatewayCard\Telegram\StateAnswers\Admin;

use Illuminate\Contracts\Container\BindingResolutionException;
use Telegram\Bot\Exceptions\TelegramSDKException;
use TelegramBotEssentials\Essence\Enums\ChatScope;
use TelegramBotEssentials\Essence\Exceptions\LogicException;
use TelegramBotEssentials\Essence\Telegram\StateAnswers\StateAnswer;
use TelegramBotEssentials\GatewayCard\Models\ToCardAttempt;
use TelegramBotEssentials\GatewayCard\Services\CardReview;
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
        $rejected = app(CardReview::class)->reject($toCardAttempt, wHook()->user(), (string) wHook()->update()->message->text);
        wHook()->conversation()->changeState();

        if (! $rejected) {
            wHook()->api()->sendMessage([
                ...wHook()->replyTo(),
                'text' => __('tbe-gateway-card::invoice.to_card.answers.admin-already_handled'),
                'reply_markup' => replyKeyboard(),
            ]);

            return;
        }

        // Reviewing from a channel, the reason comes from the private chat, and
        // a reply cannot point at a message in another chat.
        $inReviewChat = (int) $toCardAttempt->messageMeta->chat_id === (int) wHook()->update()->message->chat->id;

        wHook()->api()->sendMessage([
            ...wHook()->replyTo(),
            'text' => __('tbe-gateway-card::invoice.to_card.text.admin-payment_rejected'),
            ...($inReviewChat ? ['reply_to_message_id' => $toCardAttempt->messageMeta->message_id] : []),
            'reply_markup' => replyKeyboard(),
        ]);
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
