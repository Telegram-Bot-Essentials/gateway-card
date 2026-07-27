<?php

namespace TelegramBotEssentials\GatewayCard;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\ServiceProvider;
use Telegram\Bot\Keyboard\Keyboard;
use TelegramBotEssentials\Billing\DTOs\Gateway;
use TelegramBotEssentials\Billing\Models\Invoice;
use TelegramBotEssentials\Essence\Exceptions\LogicException;
use TelegramBotEssentials\GatewayCard\Telegram\CallbackQueries\Admin\ManageCardPaymentQuery;
use TelegramBotEssentials\GatewayCard\Telegram\CallbackQueries\Member\CardPaymentQuery;
use TelegramBotEssentials\GatewayCard\Telegram\Features\Member\CardPaymentFeature;
use TelegramBotEssentials\GatewayCard\Telegram\StateAnswers\Admin\ManageCardPaymentAnswer;
use TelegramBotEssentials\GatewayCard\Telegram\StateAnswers\Member\CardPaymentAnswer;
use TelegramBotEssentials\Settings\DTOs\Setting;
use TelegramBotEssentials\Settings\Enums\SettingType;

class TbeGatewayCardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/tbe-gateway-card.php', 'tbe-gateway-card');
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
            ManageCardPaymentQuery::class,
            CardPaymentQuery::class
        ]);

        stateAnswerBus()->addStateAnswers([
            ManageCardPaymentAnswer::class,
            CardPaymentAnswer::class,
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

            $this->publishes([
                __DIR__ . '/../config/tbe-gateway-card.php' => config_path('tbe-gateway-card.php'),
            ], 'tbe-gateway-card-config');
        }
    }

    private function addSettings(): void
    {
        settings()->addSetting(new Setting(
            key: 'billing',
            label: fn () => __('tbe-gateway-card::settings.labels.billing'),
            type: SettingType::DIRECTORY,
        ));

        settings()->addSetting(new Setting(
            key: 'billing.gateways',
            label: fn () => __('tbe-gateway-card::settings.labels.gateways'),
            type: SettingType::DIRECTORY,
        ));

        settings()->addSetting(new Setting(
            key: 'billing.gateways.card',
            label: fn () => __('tbe-gateway-card::settings.labels.to_card'),
            type: SettingType::DIRECTORY,
        ));

        settings()->addSetting(new Setting(
            key: 'billing.gateways.card.status',
            label: fn () => __('tbe-gateway-card::settings.labels.status'),
            type: SettingType::CHECKBOX,
            default: false,
        ));

        settings()->addSetting(new Setting(
            key: 'billing.gateways.card.card_number',
            label: fn () => __('tbe-gateway-card::settings.labels.card_number'),
            type: SettingType::TEXT,
        ));

        settings()->addSetting(new Setting(
            key: 'billing.gateways.card.card_name',
            label: fn () => __('tbe-gateway-card::settings.labels.card_name'),
            type: SettingType::TEXT,
        ));

        settings()->addSetting(new Setting(
            key: 'billing.gateways.card.transactions_chat_id',
            label: fn () => __('tbe-gateway-card::settings.labels.transactions_chat_id'),
            type: SettingType::TEXT,
        ));
    }

    private function registerToBilling(): void
    {
        gateways()->addGateway(new Gateway(
            key: 'card',
            label: __('tbe-gateway-card::invoice.to_card.labels.gateway'),
            inlineButtonGenerator: function (Invoice $invoice) {
                if(!CardPaymentFeature::isCardPaymentEnabled()) return null;
                return Keyboard::inlineButton([
                    'text' => __('tbe-gateway-card::invoice.to_card.keys.member-card_payment'),
                    'callback_data' => encodeCallback('CARD_PAYMENT', 'toCard', [$invoice->id])
                ]);
            }
        ));
    }
}
