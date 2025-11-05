<?php

declare(strict_types=1);

namespace BornMT\MitaGpg\Enums;

/**
 * Malta GPG Transaction Status
 *
 * @see https://gpgapi.redoc.ly/
 */
enum TransactionStatus: string
{
    /**
     * Payment has been initiated but not yet processed
     */
    case PENDING = 'PENDING';

    /**
     * Payment successfully processed
     */
    case PROCESSED = 'PROCESSED';

    /**
     * Payment was declined by the bank or gateway
     */
    case DECLINED = 'DECLINED';

    /**
     * Transaction was cancelled/voided
     */
    case CANCELLED = 'CANCELLED';

    /**
     * Transaction failed due to technical error
     */
    case FAILED = 'FAILED';

    /**
     * Pre-authorized transaction (funds held)
     */
    case AUTHORIZED = 'AUTHORIZED';

    /**
     * Payment has been refunded
     */
    case REFUNDED = 'REFUNDED';
}