<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Open = 'open';
    case Paid = 'paid';
    case Failed = 'failed';
    case Void = 'void';
}
