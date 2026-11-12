<?php

namespace TelegramBotEssentials\GatewayCard\Support;

use Closure;

/**
 * Admins type the card number and review chat id by hand, often with Persian
 * digits and the spacing printed on the card. Both are normalized before they
 * are checked and stored, so a value that cannot work is refused on save
 * instead of failing when a member pays.
 */
final class CardSettingValues
{
    private const DIGITS = [
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
    ];

    public static function cardNumber(mixed $value): ?string
    {
        return $value === null ? null : (string) preg_replace('/[\s\-]+/u', '', self::latinDigits($value));
    }

    public static function chatId(mixed $value): ?string
    {
        return $value === null ? null : (string) preg_replace('/\s+/u', '', self::latinDigits($value));
    }

    /**
     * @return list<Closure>
     */
    public static function cardNumberRules(): array
    {
        return [static function (string $attribute, mixed $value, Closure $fail): void {
            if ($value !== null && ! preg_match('/^\d{16}$/', (string) self::cardNumber($value))) {
                $fail(__('tbe-gateway-card::settings.validation.card_number'));
            }
        }];
    }

    /**
     * @return list<Closure>
     */
    public static function chatIdRules(): array
    {
        return [static function (string $attribute, mixed $value, Closure $fail): void {
            if ($value !== null && ! preg_match('/^-?\d+$/', (string) self::chatId($value))) {
                $fail(__('tbe-gateway-card::settings.validation.transactions_chat_id'));
            }
        }];
    }

    private static function latinDigits(mixed $value): string
    {
        return strtr(trim(is_scalar($value) ? (string) $value : ''), self::DIGITS);
    }
}
