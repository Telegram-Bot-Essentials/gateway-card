<?php

namespace TelegramBotEssentials\GatewayCard\Models;

use Illuminate\Support\Facades\Storage;
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

    public function getInfoPhotoAttribute(): ?string
    {
        if (!$this->info_photo_path) {
            return null;
        }

        $disk = Storage::disk(config('tbe-gateway-card.disk', 'local'));

        return $disk->exists($this->info_photo_path) ? $disk->get($this->info_photo_path) : null;
    }
}
