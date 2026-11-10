<x-filament-panels::page>
    @php($attempt = $this->attempt())
    @php($invoice = \TelegramBotEssentials\GatewayCard\Web\Manage\CardPaymentResource::invoiceOf($attempt))
    @php($R = \TelegramBotEssentials\GatewayCard\Web\Manage\CardPaymentResource::class)
    <div class="grid gap-6">
        <x-filament::section>
            <dl style="display:grid;grid-template-columns:repeat(auto-fit,minmax(11rem,1fr));gap:1rem;">
                <div><dt style="font-size:.875rem;opacity:.7;">{{ __('tbe-gateway-card::web.status') }}</dt><dd><x-filament::badge :color="$R::statusColor($attempt)">{{ $R::statusLabel($attempt) }}</x-filament::badge></dd></div>
                <div><dt style="font-size:.875rem;opacity:.7;">{{ __('tbe-gateway-card::web.member') }}</dt><dd style="font-weight:600;">{{ $R::memberName(\TelegramBotEssentials\GatewayCard\Services\CardReview::buyerOf($attempt)) }}</dd></div>
                <div><dt style="font-size:.875rem;opacity:.7;">{{ __('tbe-gateway-card::web.amount') }}</dt><dd style="font-weight:600;">{{ $R::money($attempt->amount) }}</dd></div>
                <div><dt style="font-size:.875rem;opacity:.7;">{{ __('tbe-gateway-card::web.invoice') }}</dt><dd style="font-weight:600;">#{{ $invoice?->id }} · {{ $R::money($invoice?->price) }}</dd></div>
                <div><dt style="font-size:.875rem;opacity:.7;">{{ __('tbe-gateway-card::web.received_amount') }}</dt><dd style="font-weight:600;">{{ $attempt->received_amount !== null ? $R::money($attempt->received_amount) : '—' }}</dd></div>
                <div><dt style="font-size:.875rem;opacity:.7;">{{ __('tbe-gateway-card::web.sent') }}</dt><dd style="font-weight:600;">{{ $attempt->received_at ? \Illuminate\Support\Carbon::parse($attempt->received_at)->format('Y-m-d H:i') : '—' }}</dd></div>
                @if ($attempt->reject_reason)
                    <div style="grid-column:1/-1;"><dt style="font-size:.875rem;opacity:.7;">{{ __('tbe-gateway-card::web.reason') }}</dt><dd>{{ $attempt->reject_reason }}</dd></div>
                @endif
            </dl>
        </x-filament::section>
        <x-filament::section>
            <x-slot name="heading">{{ __('tbe-gateway-card::web.receipt') }}</x-slot>
            @if ($photo = $this->photo())
                <img alt="{{ __('tbe-gateway-card::web.receipt') }}" src="{{ $photo }}" style="max-width:100%;max-height:32rem;border-radius:.5rem;">
            @endif
            <p style="white-space:pre-wrap;margin-top:.75rem;">{{ $attempt->info_text ?: __('tbe-gateway-card::web.no_text') }}</p>
        </x-filament::section>
    </div>
</x-filament-panels::page>
