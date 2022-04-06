<?php

namespace craft\commerce\worldpay\gateways;

use Craft;
use craft\commerce\controllers\PaymentsController;
use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\omnipay\base\CreditCardGateway;
use craft\commerce\worldpay\models\WorldpayPaymentForm;
use craft\commerce\worldpay\WorldpayPaymentBundle;
use craft\web\View;
use Omnipay\Common\AbstractGateway;
use Omnipay\WorldPay\JsonGateway as OmnipayGateway;

/**
 * Gateway represents WorldPay gateway
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since     1.0
 *
 * @property-read null|string $settingsHtml
 */
class Gateway extends CreditCardGateway
{
    /**
     * @var string|null
     */
    public ?string $merchantId = null;

    /**
     * @var string|null
     */
    public ?string $serviceKey = null;

    /**
     * @var string|null
     */
    public ?string $clientKey = null;

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('commerce', 'Worldpay Json');
    }

    /**
     * @inheritdoc
     */
    public function getPaymentFormHtml(array $params): ?string
    {
        $defaults = [
            'gateway' => $this,
            'paymentForm' => $this->getPaymentFormModel(),
            'handle' => $this->handle,
        ];

        $params = array_merge($defaults, $params);

        $view = Craft::$app->getView();

        $previousMode = $view->getTemplateMode();
        $view->setTemplateMode(View::TEMPLATE_MODE_CP);

        $view->registerJsFile('https://cdn.worldpay.com/v1/worldpay.js');
        $view->registerAssetBundle(WorldpayPaymentBundle::class);

        $html = Craft::$app->getView()->renderTemplate('commerce-worldpay/paymentForm', $params);
        $view->setTemplateMode($previousMode);

        return $html;
    }

    /**
     * @inheritdoc
     */
    public function getPaymentFormModel(): BasePaymentForm
    {
        return new WorldpayPaymentForm();
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('commerce-worldpay/gatewaySettings', ['gateway' => $this]);
    }

    /**
     * @inheritdoc
     */
    protected function createGateway(): AbstractGateway
    {
        /** @var OmnipayGateway $gateway */
        $gateway = static::createOmnipayGateway($this->getGatewayClassName());

        $gateway->setMerchantId(Craft::parseEnv($this->merchantId));
        $gateway->setServiceKey(Craft::parseEnv($this->serviceKey));
        $gateway->setClientKey(Craft::parseEnv($this->clientKey));

        return $gateway;
    }

    /**
     * @inheritdoc
     */
    protected function getGatewayClassName(): ?string
    {
        return '\\' . OmnipayGateway::class;
    }
}
