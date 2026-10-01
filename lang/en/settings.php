<?php

return [
    'labels' => [
        'billing' => 'Billing',
        'gateways' => 'Gateways',
        'to_card' => 'To card',
        'status' => 'To card status',
        'card_number' => 'Card number',
        'card_name' => 'Card name',
        'transactions_chat_id' => 'Transactions chat ID',
        'sms_bank' => 'SMS auto-verify bank',
        'sms_secret' => 'SMS webhook secret',
        'unique_amount' => 'Unique payment amounts',
    ],

    'options' => [
        'unique_amount' => [
            'hard' => 'Hard - always add a unique amount',
            'soft' => 'Soft - only when another payment needs it',
            'disabled' => 'Disabled - never add a unique amount',
        ],
    ],

    'descriptions' => [
        'billing' => 'Payment and invoicing settings for the bot.',
        'gateways' => 'Enable and configure the payment methods customers can use.',
        'to_card' => 'Accept manual card-to-card transfer payments.',
        'status' => 'Turn card-to-card payment on or off.',
        'card_number' => 'The card number customers should transfer payment to.',
        'card_name' => "The cardholder's name shown to customers.",
        'transactions_chat_id' => 'Chat ID where card payment receipts are sent for review.',
        'sms_bank' => 'Which bank\'s deposit SMS format to recognize for automatic payment verification.',
        'sms_secret' => "Shared secret used to verify SMS forwarded from your phone. Must match the secret configured in the forwarder app.\n\nForwarder app URL: :url",
        'unique_amount' => 'Add a small extra amount to a to-card invoice so payments can be told apart automatically. Hard always adds it, Soft only when another pending payment already needs it, Disabled never adds it. Any extra amount is credited to the member\'s wallet when that feature is available and enabled.',
    ],

    'alerts' => [
        'sms_no_bank' => "A bank SMS reached the card gateway, but no bank is chosen for SMS auto-verify, so card payments are not being confirmed automatically.\nPick the bank in the card gateway settings.",
        'sms_currency' => 'A bank SMS reached the card gateway, but the billing currency is :currency. SMS auto-verify only works with IRR or IRT, so card payments are not being confirmed automatically.',
    ],
];
