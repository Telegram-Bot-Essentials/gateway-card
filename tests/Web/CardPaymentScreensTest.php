<?php

declare(strict_types=1);

use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use TelegramBotEssentials\Billing\Models\Invoice;
use TelegramBotEssentials\Essence\Models\Bot;
use TelegramBotEssentials\Essence\Models\BotUser;
use TelegramBotEssentials\Essence\Models\MessageMeta;
use TelegramBotEssentials\Essence\Web\WebAccounts;
use TelegramBotEssentials\GatewayCard\Models\ToCardAttempt;
use TelegramBotEssentials\GatewayCard\Models\UnmatchedCardSms;
use TelegramBotEssentials\GatewayCard\Services\CardReview;
use TelegramBotEssentials\GatewayCard\Web\Manage\ViewCardPayment;
use TelegramBotEssentials\GatewayCard\Web\Widgets\CardPaymentStats;

/** A card payment whose member sent proof, waiting for review, as the bot leaves it. */
function cardAttemptFor(BotUser $member, string $amount, string $receipt = 'Paid from my card ending 1234'): ToCardAttempt
{
    $invoice = Invoice::create([
        'bot_id' => $member->bot_id, 'bot_user_id' => $member->id, 'price' => $amount,
        'payable_type' => BotUser::class, 'payable_id' => $member->id,
    ]);
    $attempt = ToCardAttempt::create(['card_number' => '6037990000000000', 'amount' => $amount, 'received_at' => now(), 'info_text' => $receipt]);
    billing()->attemptPayment($invoice, $attempt);
    $attempt->messageMeta()->save(new MessageMeta(['chat_id' => -1001234567890, 'message_id' => 55, 'message_text' => 'New card payment']));

    return $attempt->fresh();
}

/** Telegram messages sent to $chatId. */
function messagesTo(int|string $chatId): array
{
    return Http::recorded(fn ($request) => str_ends_with((string) $request->url(), '/sendMessage') && (string) ($request->data()['chat_id'] ?? '') === (string) $chatId)->all();
}

beforeEach(function () {
    $factory = new Factory;
    $factory->fake(['*' => Http::response(['ok' => true, 'result' => true])]);
    Http::swap($factory);

    $this->bot = $this->makeBot(['bot_owner_peer_id' => 9301]);
    wHook()->setBot($this->bot);
    settings()->set('billing.gateways.card.status', true);
    settings()->set('billing.gateways.card.card_number', '6037-9900-0000-0000');
    settings()->set('billing.gateways.card.card_name', 'Jane Doe');
    settings()->set('billing.gateways.card.transactions_chat_id', '-1001234567890');

    $this->admin = $this->grant($this->makeBotUser($this->bot, 9301), 'gateway-card.payments.manage', 'web.manage');
    $this->member = $this->makeBotUser($this->bot, 9302);
    $this->member->telegramUser->update(['username' => 'payer']);
    $this->account = $this->panelAccount($this->admin);
    Auth::guard(WebAccounts::guard())->login($this->account);
    $this->attempt = cardAttemptFor($this->member, '365000');
    $this->url = '/manage/'.$this->bot->unique_id.'/card-payments';
});

it('lists the payments waiting for review', function () {
    $other = $this->makeBot(['unique_id' => 'other-bot']);
    cardAttemptFor($this->makeBotUser($other, 9900), '777000', 'Someone else’s receipt');

    $this->get($this->url)
        ->assertOk()
        ->assertSee('@payer')
        ->assertSee('Paid from my card')
        ->assertSee(__('tbe-gateway-card::web.stats.waiting'))
        ->assertDontSee('Someone else’s receipt');
});

it('keeps the screens from anyone without the ability', function () {
    $limited = $this->grant($this->makeBotUser($this->bot, 9303), 'web.manage');
    Auth::guard(WebAccounts::guard())->login($this->panelAccount($limited));

    $this->get($this->url)->assertForbidden();
    $this->get($this->url.'/'.$this->attempt->id)->assertForbidden();
});

it('does not open another bot\'s payment', function () {
    $other = $this->makeBot(['unique_id' => 'other-bot']);

    $this->get($this->url.'/'.cardAttemptFor($this->makeBotUser($other, 9901), '1000')->id)->assertNotFound();
});

it('accepts a payment, paying the invoice, as the admin', function () {
    $actor = $this->enterPanel('manage', $this->bot, $this->account);

    Livewire::test(ViewCardPayment::class, ['record' => $this->attempt->id])->callAction('accept');

    expect($this->attempt->fresh()->status)->toBe('succeed')
        ->and($this->attempt->fresh()->invoice->status)->toBe('paid')
        ->and(wHook()->user()->id)->toBe($actor->id);
});

it('settles a payment once, whoever accepts it second', function () {
    $actor = $this->enterPanel('manage', $this->bot, $this->account);

    expect(app(CardReview::class)->accept($this->attempt, $actor))->toBeTrue()
        ->and(app(CardReview::class)->accept($this->attempt->fresh(), $actor))->toBeFalse()
        ->and(app(CardReview::class)->reject($this->attempt->fresh(), $actor, 'late'))->toBeFalse();

    Livewire::test(ViewCardPayment::class, ['record' => $this->attempt->id])
        ->assertActionHidden('accept')
        ->assertActionHidden('reject');
});

it('rejects with a reason the member is told', function () {
    $this->enterPanel('manage', $this->bot, $this->account);

    Livewire::test(ViewCardPayment::class, ['record' => $this->attempt->id])
        ->callAction('reject', ['reason' => ''])
        ->assertHasActionErrors(['reason']);
    expect($this->attempt->fresh()->status)->toBeNull();

    Livewire::test(ViewCardPayment::class, ['record' => $this->attempt->id])
        ->callAction('reject', ['reason' => 'No such transfer'])
        ->assertHasNoActionErrors();

    expect($this->attempt->fresh()->status)->toBe('failed')
        ->and($this->attempt->fresh()->reject_reason)->toBe('No such transfer')
        ->and($this->attempt->fresh()->invoice->status)->toBe('failed')
        ->and(collect(messagesTo(9302))->filter(fn ($pair) => str_contains((string) ($pair[0]->data()['text'] ?? ''), 'No such transfer')))->toHaveCount(1);
});

it('lists this bot\'s unmatched bank SMS only', function () {
    UnmatchedCardSms::create(['bot_id' => $this->bot->id, 'amount' => '12000', 'raw_text' => 'Deposit 120,000 rial', 'received_at' => now()]);
    UnmatchedCardSms::create(['bot_id' => $this->makeBot(['unique_id' => 'other-bot'])->id, 'amount' => '9', 'raw_text' => 'Elsewhere SMS', 'received_at' => now()]);

    $this->get('/manage/'.$this->bot->unique_id.'/card-sms')
        ->assertOk()
        ->assertSee('Deposit 120,000 rial')
        ->assertDontSee('Elsewhere SMS');
});

// One Livewire update per test: Livewire skips persistent middleware it already ran for the route.
it('opens the rejection over a real request', function () {
    $this->livewireRequest($this->url.'/'.$this->attempt->id, 'ViewCardPayment', 'mountAction', ['reject'])->assertOk();
});

it('refuses review updates once signed out', function () {
    $this->livewireRequest($this->url.'/'.$this->attempt->id, 'ViewCardPayment', 'mountAction', ['reject'],
        fn () => Auth::guard(WebAccounts::guard())->logout())->assertUnauthorized();
});

it('hides the payment cards from who may not review payments', function () {
    $limited = $this->grant($this->makeBotUser($this->bot, 9304), 'web.manage');
    $this->enterPanel('manage', $this->bot, $this->panelAccount($limited));

    expect(CardPaymentStats::canView())->toBeFalse();
});
