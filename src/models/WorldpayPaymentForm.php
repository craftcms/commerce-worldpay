<?php

namespace craft\commerce\worldpay\models;

use craft\commerce\models\payments\CreditCardPaymentForm;

/**
 * WorldPay Payment form model.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since     1.0
 */
class WorldpayPaymentForm extends CreditCardPaymentForm
{
    /**
     * @inheritdoc
     */
    public function setAttributes($values, $safeOnly = true)
    {
        parent::setAttributes($values, $safeOnly);

        if (isset($values['worldpayToken'])) {
            $this->token = $values['worldpayToken'];
        }
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        if (empty($this->token)) {
            return parent::rules();
        }

        return [];
    }
}