<?php

declare(strict_types=1);

namespace BornMT\MitaGpg\Enums;

/**
 * Malta GPG Transaction Types
 *
 * @see https://gpgapi.redoc.ly/
 */
enum TransactionType: string
{
    /**
     * SALE - Immediate payment (authorization + capture in one step)
     */
    case SALE = 'SALE';

    /**
     * AUTH - Pre-authorization (hold funds without capturing)
     * Useful for hotels, car rentals, reservations
     */
    case AUTH = 'AUTH';

    /**
     * CAPT - Capture a previously authorized payment
     * Can be full or partial amount
     */
    case CAPTURE = 'CAPT';

    /**
     * REFUND - Refund a processed payment
     * Can be full or partial amount
     */
    case REFUND = 'REFUND';

    /**
     * VOID - Cancel a pending/authorized transaction
     */
    case VOID = 'VOID';
}