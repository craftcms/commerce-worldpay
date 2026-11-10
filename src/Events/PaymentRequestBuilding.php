<?php

declare(strict_types=1);

namespace CraftCms\CommerceWorldpay\Events;

use CraftCms\Commerce\Payment\Data\Transaction;
use CraftCms\Commerce\Payment\Forms\BasePaymentForm;

/**
 * Dispatched before an order is created with Worldpay, for purchases and authorizations. Listeners may modify
 * `$request`, which is sent as the JSON body of the `POST /v1/orders` request. Authorizations have
 * `authorizeOnly` set to `true`.
 */
class PaymentRequestBuilding
{
    /**
     * @param array<string, mixed> $request
     */
    public function __construct(
        public readonly Transaction $transaction,
        public readonly BasePaymentForm $form,
        public array $request,
    ) {
    }
}
