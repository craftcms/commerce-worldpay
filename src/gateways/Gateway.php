<?php

namespace craft\commerce\worldpay\gateways;

use Craft;
use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\omnipay\base\CreditCardGateway;
use craft\commerce\worldpay\models\WorldpayPaymentForm;
use craft\commerce\worldpay\WorldpayPaymentBundle;
use craft\helpers\App;
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
    private ?string $_merchantId = null;

    /**
     * @var string|null
     */
    private ?string $_serviceKey = null;

    /**
     * @var string|null
     */
    private ?string $_clientKey = null;

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
    public function getSettings(): array
    {
        $settings = parent::getSettings();
        $settings['merchantId'] = $this->getMerchantId(false);
        $settings['serviceKey'] = $this->getServiceKey(false);
        $settings['clientKey'] = $this->getClientKey(false);

        return $settings;
    }

    /**
     * @param bool $parse
     * @return string|null
     * @since 4.0.0
     */
    public function getMerchantId(bool $parse = true): ?string
    {
        return $parse ? App::parseEnv($this->_merchantId) : $this->_merchantId;
    }

    /**
     * @param string|null $merchantId
     * @return void
     * @since 4.0.0
     */
    public function setMerchantId(?string $merchantId): void
    {
        $this->_merchantId = $merchantId;
    }

    /**
     * @param bool $parse
     * @return string|null
     * @since 4.0.0
     */
    public function getServiceKey(bool $parse = true): ?string
    {
        return $parse ? App::parseEnv($this->_serviceKey) : $this->_serviceKey;
    }

    /**
     * @param string|null $serviceKey
     * @return void
     * @since 4.0.0
     */
    public function setServiceKey(?string $serviceKey): void
    {
        $this->_serviceKey = $serviceKey;
    }

    /**
     * @param bool $parse
     * @return string|null
     * @since 4.0.0
     */
    public function getClientKey(bool $parse = true): ?string
    {
        return $parse ? App::parseEnv($this->_clientKey) : $this->_clientKey;
    }

    /**
     * @param string|null $clientKey
     * @return void
     * @since 4.0.0
     */
    public function setClientKey(?string $clientKey): void
    {
        $this->_clientKey = $clientKey;
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

        $gateway->setMerchantId($this->getMerchantId());
        $gateway->setServiceKey($this->getServiceKey());
        $gateway->setClientKey($this->getClientKey());

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
