<?php

return [
    'to_card' => [
        'text' => [
            'admin-payment_result' => '#⃣ Invoice :invoiceId'
                ."\r\n🏷 New card payment"
                ."\r\n"
                ."\r\n👤 User: <a href=\"tg://user?id=:userPeerId\">:userFullName</a>"
                ."\r\n💲 Amount: :invoiceAmount"
                ."\r\n"
                ."\r\n📝 Order description: \r\n:invoiceDescription"
                ."\r\n"
                ."\r\n📝 User's payment description: \r\n:paymentDescription",
            'admin_payment_rejection' => '🔏 Rejecting card payment :toCardAttemptId'
                ."\r\n"
                ."\r\nSend the rejection reason:",
            'admin-payment_rejected' => '🛑 Reason sent and payment rejected.',
            'user-payment_result' => 'Payment submitted — we\'ll review it shortly.',
            'user-pay_message' => 'Transfer the amount to this card, then send the receipt here'
                ."\r\n"
                ."\r\n💲 Amount: :amount"
                ."\r\n🔸 <code>:cardNumber</code>"
                ."\r\n👤 :cardName",
            'unique_amount_notice' => '⚠️ Double-check the amount and the card, and pay this exact amount to be verified instantly.',
            'unique_amount_wallet_notice' => 'The extra <code>:extraAmount</code> will be added directly to your wallet balance.',
            'user-payment_rejected' => '❌ Your payment was rejected. Reason:'
                ."\r\n:rejectionReason",
            'admin-sms_unmatched' => '⚠️ Received a bank SMS for :amount, but it matched :candidateCount pending card payment(s) instead of exactly one. Left for manual review.',
        ],
        'answers' => [
            'admin-rejecting_payment' => 'Send the rejection reason',
            'admin-payment_accepted' => 'Payment accepted',
            'attempting' => 'Setting up your card payment…',
        ],
        'keys' => [
            'admin-accept_payment' => '✅ Accept payment',
            'admin-reject_payment' => '❌ Reject payment',
            'member-card_payment' => 'Card payment',
        ],
        'lock-keys' => [
            'admin-rejecting_payment' => 'Waiting for the rejection reason',
            'admin-payment_accepted_by' => 'Payment accepted by :adminName',
            'admin-payment_rejected_by' => 'Payment rejected by :adminName',
            'user-payment_accepted' => 'Payment accepted',
            'user-payment_rejected' => 'Payment rejected',
            'user-waiting_for_payment' => 'Waiting for payment',
            'user-wait_for_payment_processing' => 'Waiting for payment processing',
            'auto_verified_by_sms' => 'Auto-verified via SMS',
        ],
        'labels' => [
            'gateway' => 'Card',
        ],
    ],
];
