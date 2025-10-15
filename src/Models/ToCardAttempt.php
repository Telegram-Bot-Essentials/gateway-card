<?php

namespace TelegramBotEssentials\GatewayCard\Models;

use TelegramBotEssentials\Billing\Models\Abstract\PaymentAttempt;
use TelegramBotEssentials\Essence\Traits\HasMessageMeta;

class ToCardAttempt extends PaymentAttempt
{
    use HasMessageMeta;
    protected $guarded = [
        'id',
        'updated_at',
        'deleted_at',
        'created_at',
    ];

    protected function attemptSucceedHook(): void
    {
        // TODO: Implement attemptSucceedHook() method.
    }

    protected function attemptFailedHook(): void
    {
        $this->setAttribute('rejected_at', now());
        $this->save();
    }
}
