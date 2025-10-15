<?php

namespace TelegramBotEssentials\GatewayCard;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Telegram\Bot\Keyboard\Keyboard;
use TelegramBotEssentials\Billing\DTOs\Gateway;
use TelegramBotEssentials\Billing\Models\Invoice;
use TelegramBotEssentials\Essence\Exceptions\LogicException;
use TelegramBotEssentials\Settings\DTOs\Setting;
use TelegramBotEssentials\Settings\Enums\SettingType;

class TbeGatewayCardServiceProvider extends ServiceProvider
{
    public function register(): void
    {

    }

    /**
     * @throws LogicException
     * @throws BindingResolutionException
     */
    public function boot(): void
    {
        $this->registerPublishing();

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadTranslationsFrom(__DIR__ . '/../lang', 'tbe-gateway-card');

        callbackQueryBus()->addCallbackQueries([

        ]);

        stateAnswerBus()->addStateAnswers([

        ]);

        $this->addSettings();
        $this->registerToBilling();
    }

    protected function registerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../lang' => resource_path('lang/vendor/tbe-gateway-card'),
            ], 'tbe-gateway-card-translations');
        }
    }

    private function addSettings(): void
    {
        settings()->addSetting(new Setting(
            key: 'billing',
            label: 'Billing',
            type: SettingType::DIRECTORY,
        ));

        settings()->addSetting(new Setting(
            key: 'billing.gateways',
            label: 'Gateways',
            type: SettingType::DIRECTORY,
        ));

        settings()->addSetting(new Setting(
            key: 'billing.gateways.card',
            label: 'To Card',
            type: SettingType::DIRECTORY,
        ));

        settings()->addSetting(new Setting(
            key: 'billing.gateways.card.status',
            label: 'To Card Status',
            type: SettingType::CHECKBOX,
            default: false,
        ));

        settings()->addSetting(new Setting(
            key: 'billing.gateways.card.card_number',
            label: 'Card Number',
            type: SettingType::TEXT,
        ));

        settings()->addSetting(new Setting(
            key: 'billing.gateways.card.card_name',
            label: 'Card Name',
            type: SettingType::TEXT,
        ));

        settings()->addSetting(new Setting(
            key: 'billing.gateways.card.transactions_chat_id',
            label: 'Transactions Chat ID',
            type: SettingType::TEXT,
        ));
    }

    private function registerToBilling(): void
    {
        gateways()->addGateway(new Gateway(
            key: 'card',
            label: 'Card',
            inlineButtonGenerator: function (Invoice $invoice) {
                return Keyboard::inlineButton([
                    'text' => 'Card payment',
                    'callback_data' => encodeCallback('CARD_PAYMENT', 'toCard', [$invoice->id])
                ]);
            }
        ));
    }
}
