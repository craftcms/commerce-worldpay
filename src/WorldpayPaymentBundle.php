<?php
/**
 * @link      https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license   https://craftcms.com/license
 */

namespace craft\commerce\worldpay;

use yii\web\AssetBundle;

/**
 * Asset bundle for the Dashboard
 */
class WorldpayPaymentBundle extends AssetBundle
{
    /**
     * @inheritdoc
     */
    public function init(): void
    {
        $this->sourcePath = '@craft/commerce/worldpay/resources';

        $this->js = [
            'js/paymentForm.js',
        ];

        parent::init();
    }
}
