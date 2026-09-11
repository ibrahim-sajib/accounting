<?php

namespace App\Support\Enums;

enum StockMovementType: string
{
    case Opening = 'opening';
    case PurchaseReceived = 'purchase_received';
    case SalesIssued = 'sales_issued';
    case Adjustment = 'adjustment';
    case TransferOut = 'transfer_out';
    case TransferIn = 'transfer_in';
}