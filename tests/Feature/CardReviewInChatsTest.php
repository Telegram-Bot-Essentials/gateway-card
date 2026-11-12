<?php

declare(strict_types=1);

use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use TelegramBotEssentials\Essence\Models\BotUser;
use TelegramBotEssentials\Essence\Models\Conversation;
use TelegramBotEssentials\Essence\Models\MessageMeta;

/*
 * Receipts go to the transactions chat, which can be a private chat, a group
 * or a channel. Reviewing has to work from each, and only for someone with the
 * ability: anyone in the group can see the buttons.
 */

const REVIEW_CHAT = -100333444;
const REVIEWER = 8801;
const BYSTANDER = 8802;

beforeEach(function () {
    fakeTelegram();

    $this->bot = $this->makeBot();
    configureCardSettings($this->bot);
    Gate::define('gateway-card.payments.manage', fn (BotUser $user) => (int) $user->telegram_user_peer_id === REVIEWER);

    $this->reviewer = $this->makeBotUser($this->bot, REVIEWER);
    $this->attempt = makePendingAttempt($this->bot, '365000', now());

    // The receipt message makePendingAttempt() recorded, in the review chat.
    $this->meta = $this->attempt->messageMeta;
    $this->meta->forceFill(['chat_id' => REVIEW_CHAT, 'message_id' => 77])->save();
});

function reviewButton(string $method, $attempt, int $chatId, string $type, int $peerId = REVIEWER)
{
    $update = test()->makeCallbackQueryUpdate(encodeCallback('MANAGE_CARD_PAYMENT', $method, [$attempt->id]), $peerId);

    if ($type !== 'private') {
        $update = test()->inGroupChat($update, $chatId, $type);
    }

    return test()->postWebhookUpdate(test()->bot, $update)->assertOk();
}

function sendsTo(int $chatId): Closure
{
    return fn ($request) => str_ends_with($request->url(), '/sendMessage') && (int) $request['chat_id'] === $chatId;
}

it('accepts a payment from a group or a channel', function (string $type) {
    reviewButton('acceptCardPayment', $this->attempt, REVIEW_CHAT, $type);

    expect($this->attempt->fresh()->status)->toBe('succeed')
        ->and($this->attempt->fresh()->invoice->status)->toBe('paid');
})->with(['supergroup', 'group', 'channel']);

it('accepts a payment from a private chat, as before', function () {
    reviewButton('acceptCardPayment', $this->attempt, REVIEWER, 'private');

    expect($this->attempt->fresh()->status)->toBe('succeed');
});

it('lets nobody else review, whatever the chat', function (string $method, string $type) {
    reviewButton($method, $this->attempt, REVIEW_CHAT, $type, BYSTANDER);

    expect($this->attempt->fresh()->status)->toBeNull()
        ->and($this->attempt->fresh()->invoice->status)->not->toBe('paid');

    tenancy()->initialize($this->bot);
    expect(Conversation::whereNotNull('state')->exists())->toBeFalse();
    tenancy()->end();
})->with([
    ['acceptCardPayment', 'supergroup'],
    ['acceptCardPayment', 'channel'],
    ['rejectCardPayment', 'supergroup'],
    ['rejectCardPayment', 'channel'],
]);

it('asks for the rejection reason in the group and takes it there', function () {
    reviewButton('rejectCardPayment', $this->attempt, REVIEW_CHAT, 'supergroup');

    Http::assertSent(fn ($request) => sendsTo(REVIEW_CHAT)($request) && (int) $request['reply_to_message_id'] === 77);
    Http::assertNotSent(sendsTo(REVIEWER));

    $this->postWebhookUpdate($this->bot, $this->inGroupChat($this->makeMessageUpdate('blurry photo', REVIEWER), REVIEW_CHAT))->assertOk();

    $attempt = $this->attempt->fresh();

    expect($attempt->status)->toBe('failed')
        ->and($attempt->reject_reason)->toBe('blurry photo');
    Http::assertSent(sendsTo((int) $attempt->invoice->botUser->telegram_user_peer_id));
    Http::assertNotSent(sendsTo(REVIEWER));
});

it('asks for the rejection reason privately from a channel, which cannot answer', function () {
    reviewButton('rejectCardPayment', $this->attempt, REVIEW_CHAT, 'channel');

    Http::assertSent(fn ($request) => sendsTo(REVIEWER)($request) && ! isset($request['reply_to_message_id']));

    $this->postWebhookUpdate($this->bot, $this->makeMessageUpdate('blurry photo', REVIEWER))->assertOk();

    $attempt = $this->attempt->fresh();

    expect($attempt->status)->toBe('failed')
        ->and($attempt->reject_reason)->toBe('blurry photo');
    Http::assertNotSent(fn ($request) => sendsTo(REVIEWER)($request) && isset($request['reply_to_message_id']));
});

it('rejects from a private chat, as before', function () {
    $this->meta->forceFill(['chat_id' => REVIEWER])->save();

    reviewButton('rejectCardPayment', $this->attempt, REVIEWER, 'private');
    Http::assertSent(fn ($request) => sendsTo(REVIEWER)($request) && (int) $request['reply_to_message_id'] === 77);

    $this->postWebhookUpdate($this->bot, $this->makeMessageUpdate('blurry photo', REVIEWER))->assertOk();

    expect($this->attempt->fresh()->reject_reason)->toBe('blurry photo');
});

it('sends the member the rejection reason as a reply to their invoice message', function () {
    $invoice = $this->attempt->invoice;
    $member = (int) $invoice->botUser->telegram_user_peer_id;
    $view = new MessageMeta;
    $view->chat_id = $member;
    $view->message_id = 55;
    $view->message_text = 'Invoice';
    $view->tag = 'invoice_view';
    $view->action()->associate($invoice);
    $view->save();

    reviewButton('rejectCardPayment', $this->attempt, REVIEW_CHAT, 'supergroup');
    $this->postWebhookUpdate($this->bot, $this->inGroupChat($this->makeMessageUpdate('blurry photo', REVIEWER), REVIEW_CHAT))->assertOk();

    Http::assertSent(fn ($request) => sendsTo($member)($request)
        && str_contains((string) $request['text'], 'blurry photo')
        && ($request['reply_parameters']['message_id'] ?? null) === 55
        && ($request['reply_parameters']['allow_sending_without_reply'] ?? null) === true);
});

it('finishes the admin\'s review when the member cannot be reached', function () {
    $member = (int) $this->attempt->invoice->botUser->telegram_user_peer_id;
    $factory = new Factory;
    $factory->fake(function ($request) use ($member) {
        return (int) ($request['chat_id'] ?? 0) === $member
            ? Http::response(['ok' => false, 'error_code' => 403, 'description' => 'Forbidden: bot was blocked by the user'], 403)
            : Http::response(['ok' => true, 'result' => ['message_id' => 1, 'date' => 1, 'chat' => ['id' => 1, 'type' => 'private']]]);
    });
    Http::swap($factory);

    reviewButton('acceptCardPayment', $this->attempt, REVIEWER, 'private');

    expect($this->attempt->fresh()->invoice->status)->toBe('paid');
    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/answerCallbackQuery'));
    Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/sendMessage')
        && str_contains((string) $request['text'], 'Something went wrong'));
});

it('puts the review buttons back and keeps the payment pending when the reviewer cancels the rejection', function (string $type, int $answerChat) {
    $this->meta->forceFill(['message_text' => 'Receipt', 'message_reply_markup' => '{"inline_keyboard":[]}'])->save();
    if ($type === 'private') {
        $this->meta->forceFill(['chat_id' => REVIEWER])->save();
    }

    reviewButton('rejectCardPayment', $this->attempt, $type === 'private' ? REVIEWER : REVIEW_CHAT, $type);

    $cancel = $this->makeMessageUpdate('/cancel', REVIEWER);
    if ($answerChat !== REVIEWER) {
        $cancel = $this->inGroupChat($cancel, $answerChat);
    }
    $this->postWebhookUpdate($this->bot, $cancel)->assertOk();

    expect($this->attempt->fresh()->status)->toBe($this->attempt->status)
        ->and($this->attempt->fresh()->reject_reason)->toBeNull();
    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/editMessageText')
        && (int) $request['chat_id'] === (int) $this->meta->fresh()->chat_id
        && (int) $request['message_id'] === 77);
    Http::assertNotSent(fn ($request) => str_contains((string) ($request['text'] ?? ''), 'Something went wrong'));

    // The reviewer can start over afterwards.
    reviewButton('rejectCardPayment', $this->attempt, $type === 'private' ? REVIEWER : REVIEW_CHAT, $type);
    $reason = $this->makeMessageUpdate('blurry photo', REVIEWER);
    if ($answerChat !== REVIEWER) {
        $reason = $this->inGroupChat($reason, $answerChat);
    }
    $this->postWebhookUpdate($this->bot, $reason)->assertOk();

    expect($this->attempt->fresh()->reject_reason)->toBe('blurry photo');
})->with([
    'private chat' => ['private', REVIEWER],
    'group' => ['supergroup', REVIEW_CHAT],
    'channel, answered privately' => ['channel', REVIEWER],
]);
