<?php

declare(strict_types=1);

namespace CraftCms\CommerceWorldpay\Forms;

use CraftCms\Commerce\Payment\Forms\CreditCardPaymentForm;
use Override;

/**
 * The Worldpay payment form. Worldpay.js tokenizes the card and posts the token as `worldpayToken`.
 */
class WorldpayPaymentForm extends CreditCardPaymentForm
{
    #[Override]
    public function setAttributes($values): void
    {
        parent::setAttributes($values);

        if (isset($values['worldpayToken'])) {
            $this->token = $values['worldpayToken'];
        }
    }

    #[Override]
    public function getRules(): array
    {
        if (empty($this->token)) {
            return parent::getRules();
        }

        return [];
    }
}
