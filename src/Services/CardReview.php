<?php

namespace TelegramBotEssentials\GatewayCard\Services;

use Illuminate\Support\Facades\DB;
use Telegram\Bot\Exceptions\TelegramSDKException;
use TelegramBotEssentials\Essence\Models\BotUser;
use TelegramBotEssentials\Essence\Models\MessageMeta;
use TelegramBotEssentials\Essence\Models\TelegramUser;
use TelegramBotEssentials\GatewayCard\Models\ToCardAttempt;

/**
 * A reviewer accepts or rejects a card payment, from the transactions chat
 * or the web panel. The attempt row is locked and must still be pending, so
 * two reviewers (or a reviewer and a matching bank SMS) settle it once.
 */
class CardReview
{
    /** Waiting for a reviewer: proof sent, not yet accepted, rejected or closed. */
    public function isPending(ToCardAttempt $attempt): bool
    {
        return $attempt->status === null && $attempt->received_at !== null;
    }

    /** False when someone already handled it. */
    public function accept(ToCardAttempt $attempt, BotUser $reviewer): bool
    {
        $accepted = DB::transaction(function () use ($attempt): bool {
            $locked = $this->lockPending($attempt);
            if ($locked === null) {
                return false;
            }

            $locked->attemptSucceed();

            return true;
        });

        if (! $accepted) {
            return false;
        }

        $attempt->refresh();

        tbeLog('gateway-card')->audit('Accepted card payment #{attempt_id} of {amount}', [
            'attempt_id' => $attempt->getKey(),
            'amount' => $attempt->amount,
        ]);

        inBotLocale(fn () => $this->lock($attempt->messageMeta, __('tbe-gateway-card::invoice.to_card.lock-keys.admin-payment_accepted_by', [
            'adminName' => self::fullName($reviewer),
        ]), '✅'));

        return true;
    }

    /** Fails the invoice and tells the member why. False when someone already handled it. */
    public function reject(ToCardAttempt $attempt, BotUser $reviewer, string $reason): bool
    {
        $rejected = DB::transaction(function () use ($attempt, $reason): bool {
            $locked = $this->lockPending($attempt);
            if ($locked === null) {
                return false;
            }

            $locked->attemptFailed();
            $locked->reject_reason = $reason;
            $locked->save();

            return true;
        });

        if (! $rejected) {
            return false;
        }

        $attempt->refresh();

        tbeLog('gateway-card')->audit('Rejected card payment #{attempt_id} of {amount}: {reject_reason}', [
            'attempt_id' => $attempt->getKey(),
            'amount' => $attempt->amount,
            'reject_reason' => $reason,
        ]);

        inBotLocale(function () use ($attempt, $reviewer, $reason): void {
            $buyer = self::buyerOf($attempt);
            $peerId = $buyer?->telegramUser instanceof TelegramUser ? $buyer->telegramUser->peer_id : null;
            if ($peerId !== null) {
                try {
                    wHook()->api()->sendMessage([
                        'chat_id' => $peerId,
                        'text' => __('tbe-gateway-card::invoice.to_card.text.user-payment_rejected', ['rejectionReason' => $reason]),
                    ]);
                } catch (TelegramSDKException $e) {
                    // The member blocked the bot: the rejection stands.
                    tbeLog('gateway-card')->warning('Could not tell the member about a rejected card payment', ['attempt_id' => $attempt->getKey(), 'exception' => $e]);
                }
            }

            $this->lock($attempt->messageMeta, __('tbe-gateway-card::invoice.to_card.lock-keys.admin-payment_rejected_by', [
                'adminName' => self::fullName($reviewer),
            ]), '❌');
            $this->lock($attempt->invoice?->getRelationValue('messageMeta'), __('tbe-gateway-card::invoice.to_card.lock-keys.user-payment_rejected'), '❌');
        });

        return true;
    }

    /** Who pays the attempt's invoice. */
    public static function buyerOf(ToCardAttempt $attempt): ?BotUser
    {
        $buyer = $attempt->invoice?->getRelationValue('botUser');

        return $buyer instanceof BotUser ? $buyer : null;
    }

    public static function fullName(?BotUser $user): string
    {
        $telegramUser = $user?->telegramUser;
        $name = $telegramUser instanceof TelegramUser ? $telegramUser->getAttribute('full_name') : null;

        return is_string($name) ? $name : '';
    }

    /**
     * The attempt, locked, while it is still pending; null once handled. Call
     * it inside a transaction, which holds the lock until the change is saved.
     */
    public function lockPending(ToCardAttempt $attempt): ?ToCardAttempt
    {
        $locked = ToCardAttempt::query()->whereKey($attempt->getKey())->lockForUpdate()->first();

        if ($locked === null || $locked->status !== null || $locked->invoice?->getAttribute('status') === 'paid') {
            return null;
        }

        return $locked;
    }

    /** Puts the review or invoice message's buttons away; a message Telegram no longer has is left be. */
    private function lock(mixed $messageMeta, string $text, string $emoji): void
    {
        // Without all three, lockAction() would read them from a button tap the panel never has.
        if (! $messageMeta instanceof MessageMeta || empty($messageMeta->chat_id) || empty($messageMeta->message_id) || empty($messageMeta->message_text)) {
            return;
        }

        try {
            $messageMeta->lockAction($text, customEmoji: $emoji);
        } catch (TelegramSDKException $e) {
            tbeLog('gateway-card')->warning('Could not lock a card payment message', ['message_meta_id' => $messageMeta->getKey(), 'exception' => $e]);
        }
    }
}
