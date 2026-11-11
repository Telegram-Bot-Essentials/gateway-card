<x-filament-panels::page>
    @php($attempt = $this->attempt())
    @php($invoice = \TelegramBotEssentials\GatewayCard\Web\Manage\CardPaymentResource::invoiceOf($attempt))
    @php($R = \TelegramBotEssentials\GatewayCard\Web\Manage\CardPaymentResource::class)
    @php($mismatch = $attempt->received_amount !== null && (string) $attempt->received_amount !== (string) $attempt->amount)
    <div class="grid gap-6">
        <x-filament::section>
            <x-tbe::facts>
                <x-tbe::fact :label="__('tbe-gateway-card::web.status')">
                    <x-filament::badge :color="$R::statusColor($attempt)">{{ $R::statusLabel($attempt) }}</x-filament::badge>
                </x-tbe::fact>
                <x-tbe::fact :label="__('tbe-gateway-card::web.member')" :value="$R::memberName(\TelegramBotEssentials\GatewayCard\Services\CardReview::buyerOf($attempt))" />
                <x-tbe::fact :label="__('tbe-gateway-card::web.amount')" :value="$R::money($attempt->amount)" />
                <x-tbe::fact :label="__('tbe-gateway-card::web.invoice')" :value="'#'.$invoice?->id.' · '.$R::money($invoice?->price)" />
                <x-tbe::fact :label="__('tbe-gateway-card::web.received_amount')">
                    @if ($attempt->received_amount === null)
                        —
                    @else
                        <span @style(['color:var(--danger-600, #dc2626);' => $mismatch])>{{ $R::money($attempt->received_amount) }}</span>
                    @endif
                </x-tbe::fact>
                <x-tbe::fact :label="__('tbe-gateway-card::web.sent')"><x-tbe::when :at="$attempt->received_at" exact /></x-tbe::fact>
                @if ($attempt->reject_reason)
                    <x-tbe::fact :label="__('tbe-gateway-card::web.reason')" :value="$attempt->reject_reason" wide />
                @endif
            </x-tbe::facts>
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
