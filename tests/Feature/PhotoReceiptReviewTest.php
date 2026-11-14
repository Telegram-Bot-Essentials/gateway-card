<?php

declare(strict_types=1);

use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use TelegramBotEssentials\Billing\Models\Invoice;
use TelegramBotEssentials\Essence\Models\BotUser;
use TelegramBotEssentials\Essence\Models\MessageMeta;
use TelegramBotEssentials\Essence\Models\TelegramUser;
use TelegramBotEssentials\GatewayCard\Models\ToCardAttempt;
use TelegramBotEssentials\GatewayCard\Services\CardReview;

/*
 * Most receipts are screenshots. The bot copies the photo into the
 * transactions chat, and copyMessage answers with nothing but the new
 * message id - so the review message has to be remembered from what the bot
 * sent, or settling the payment cannot take its buttons away.
 */

const PHOTO_REVIEW_CHAT = -1001234567890;
const PHOTO_REVIEW_MESSAGE = 77;
const PHOTO_REVIEWER = 8811;

beforeEach(function () {
    Storage::fake('local');

    $factory = new Factory;
    $factory->fake([
        '*/copyMessage*' => Http::response(['ok' => true, 'result' => ['message_id' => PHOTO_REVIEW_MESSAGE]]),
        '*/getFile*' => Http::response(['ok' => true, 'result' => ['file_id' => 'big', 'file_unique_id' => 'u-big', 'file_path' => 'photos/receipt.jpg']]),
        '*' => Http::response(['ok' => true, 'result' => true]),
    ]);
    Http::swap($factory);

    $this->bot = $this->makeBot();
    configureCardSettings($this->bot);
    Gate::define('gateway-card.payments.manage', fn (BotUser $user) => (int) $user->telegram_user_peer_id === PHOTO_REVIEWER);
    $this->reviewer = $this->makeBotUser($this->bot, PHOTO_REVIEWER);

    $this->memberPeerId = random_int(100000, 999999999);
    TelegramUser::factory()->create(['peer_id' => $this->memberPeerId]);
    $member = BotUser::factory()->create(['bot_id' => $this->bot->id, 'telegram_user_peer_id' => $this->memberPeerId]);

    $this->invoice = Invoice::create([
        'bot_id' => $this->bot->id,
        'bot_user_id' => $member->id,
        'price' => '365000',
        'payable_type' => BotUser::class,
        'payable_id' => $member->id,
    ]);
    $this->invoice->messageMeta()->save(new MessageMeta([
        'chat_id' => $this->memberPeerId,
        'message_id' => 5,
        'message_text' => 'Invoice',
    ]));

    // The member picks card payment, then sends a screenshot as the receipt.
    $this->postWebhookUpdate($this->bot, $this->makeCallbackQueryUpdate(
        encodeCallback('CARD_PAYMENT', 'toCard', [$this->invoice->id]),
        $this->memberPeerId,
    ))->assertOk();

    $photo = $this->makeMessageUpdate('', $this->memberPeerId);
    unset($photo['message']['text']);
    $photo['message']['photo'] = [
        ['file_id' => 'small', 'file_unique_id' => 'u-small', 'width' => 90, 'height' => 90],
        ['file_id' => 'big', 'file_unique_id' => 'u-big', 'width' => 1280, 'height' => 1280],
    ];
    $this->postWebhookUpdate($this->bot, $photo)->assertOk();

    $this->attempt = $this->invoice->fresh()->paymentAttempt;
});

function lockedReviewMessage(string $emoji): Closure
{
    return fn ($request) => str_ends_with($request->url(), '/editMessageReplyMarkup')
        && (int) $request['chat_id'] === PHOTO_REVIEW_CHAT
        && (int) $request['message_id'] === PHOTO_REVIEW_MESSAGE
        && str_contains((string) json_decode((string) $request['reply_markup'], true)['inline_keyboard'][0][0]['text'], $emoji);
}

it('takes the buttons off a photo receipt the reviewer accepts in the chat', function () {
    expect($this->attempt)->toBeInstanceOf(ToCardAttempt::class)
        ->and($this->attempt->received_at)->not->toBeNull();

    $tap = $this->inGroupChat(
        $this->makeCallbackQueryUpdate(encodeCallback('MANAGE_CARD_PAYMENT', 'acceptCardPayment', [$this->attempt->id]), PHOTO_REVIEWER),
        PHOTO_REVIEW_CHAT,
        'supergroup',
    );
    $this->postWebhookUpdate($this->bot, $tap)->assertOk();

    expect($this->attempt->fresh()->status)->toBe('succeed');
    Http::assertSent(lockedReviewMessage('✅'));
});

it('takes the buttons off a photo receipt accepted from the web panel', function () {
    // The panel taps no button: the lock has only the stored meta to go on.
    expect(app(CardReview::class)->accept($this->attempt->fresh(), $this->reviewer))->toBeTrue();

    Http::assertSent(lockedReviewMessage('✅'));
});

it('takes the buttons off a photo receipt rejected from the web panel', function () {
    expect(app(CardReview::class)->reject($this->attempt->fresh(), $this->reviewer, 'blurry photo'))->toBeTrue();

    Http::assertSent(lockedReviewMessage('❌'));
});
