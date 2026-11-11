<?php

namespace App\Enums;

/**
 * How a payment was made. Card payments go through the online provider;
 * the others are recorded by a platform admin after checking them.
 */
enum PaymentMethod: string
{
    case Card = 'card';
    case BankTransfer = 'bank_transfer';
    case Cash = 'cash';
    case MobileWallet = 'mobile_wallet';
    case Other = 'other';
}
