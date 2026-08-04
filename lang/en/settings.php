<?php

return [
    'labels' => [
        'billing' => 'Billing',
        'gateways' => 'Gateways',
        'to_card' => 'To Card',
        'status' => 'To Card Status',
        'card_number' => 'Card Number',
        'card_name' => 'Card Name',
        'transactions_chat_id' => 'Transactions Chat ID',
    ],

    'descriptions' => [
        'billing' => 'Payment and invoicing settings for the bot.',
        'gateways' => 'Enable and configure the payment methods customers can use.',
        'to_card' => 'Accept manual card-to-card transfer payments.',
        'status' => 'Turn card-to-card payment on or off.',
        'card_number' => 'The card number customers should transfer payment to.',
        'card_name' => "The cardholder's name shown to customers.",
        'transactions_chat_id' => 'Chat ID where card payment receipts are sent for review.',
    ],
];
