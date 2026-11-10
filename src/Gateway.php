<?php

declare(strict_types=1);

namespace CraftCms\CommerceWorldpay;

use CraftCms\Cms\Cp\SelectOptions;
use CraftCms\Cms\Support\Env;
use CraftCms\Cms\Support\Facades\HtmlStack;
use CraftCms\Cms\Support\Html;
use CraftCms\Cms\Ui\Controls\Text;
use CraftCms\Cms\Ui\Nodes\Field;
use CraftCms\Cms\Ui\Nodes\TemplateContent;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Cms\Ui\UiContext;
use CraftCms\Cms\View\Enums\Position;
use CraftCms\Commerce\Exceptions\NotImplementedException;
use CraftCms\Commerce\Helpers\PaymentForm;
use CraftCms\Commerce\Payment\Currencies;
use CraftCms\Commerce\Payment\Data\PaymentSource;
use CraftCms\Commerce\Payment\Data\Transaction;
use CraftCms\Commerce\Payment\Forms\BasePaymentForm;
use CraftCms\Commerce\Payment\Forms\CreditCardPaymentForm;
use CraftCms\Commerce\Payment\Gateway\Contracts\RequestResponseInterface;
use CraftCms\Commerce\Payment\Gateway\Gateway as BaseGateway;
use CraftCms\CommerceWorldpay\Events\PaymentRequestBuilding;
use CraftCms\CommerceWorldpay\Forms\WorldpayPaymentForm;
use CraftCms\CommerceWorldpay\Responses\PaymentResponse;
use Illuminate\Http\Response;
use InvalidArgumentException;
use Override;

use function CraftCms\Cms\t;

/**
 * Worldpay gateway, using the Worldpay Online Payments JSON API.
 *
 * Worldpay.js tokenizes the card in the browser, and the gateway creates a Worldpay order with the token.
 */
class Gateway extends BaseGateway
{
    /**
     * The URL of Worldpay.js, which renders the card form and tokenizes the card.
     */
    public const string WORLDPAY_JS_URL = 'https://cdn.worldpay.com/v1/worldpay.js';

    /**
     * @var string|null The Worldpay merchant ID. Can be set to an environment variable.
     */
    public ?string $merchantId = null;

    /**
     * @var string|null The Worldpay service key, used to authenticate API requests. Can be set to an environment variable.
     */
    public ?string $serviceKey = null;

    /**
     * @var string|null The Worldpay client key, used by Worldpay.js. Can be set to an environment variable.
     */
    public ?string $clientKey = null;

    #[Override]
    public static function displayName(): string
    {
        return t('Worldpay Json', category: 'commerce-worldpay');
    }

    public function getMerchantId(bool $parse = true): ?string
    {
        return $parse ? Env::parse($this->merchantId) : $this->merchantId;
    }

    public function setMerchantId(?string $merchantId): void
    {
        $this->merchantId = $merchantId;
    }

    public function getServiceKey(bool $parse = true): ?string
    {
        return $parse ? Env::parse($this->serviceKey) : $this->serviceKey;
    }

    public function setServiceKey(?string $serviceKey): void
    {
        $this->serviceKey = $serviceKey;
    }

    public function getClientKey(bool $parse = true): ?string
    {
        return $parse ? Env::parse($this->clientKey) : $this->clientKey;
    }

    public function setClientKey(?string $clientKey): void
    {
        $this->clientKey = $clientKey;
    }

    #[Override]
    public function getRules(): array
    {
        return array_merge(parent::getRules(), [
            'serviceKey' => ['required'],
            'clientKey' => ['required'],
        ]);
    }

    #[Override]
    public function settingsUi(UiContext $context = new UiContext()): ?Ui
    {
        return Ui::make([
            Field::make(t('Merchant ID', category: 'commerce-worldpay'))
                ->control(Text::make('merchantId')
                    ->value($this->merchantId)
                    ->textExpanderTriggers(SelectOptions::getEnvTextExpanderTriggers())),
            Field::make(t('Service key', category: 'commerce-worldpay'))
                ->required()
                ->control(Text::make('serviceKey')
                    ->value($this->serviceKey)
                    ->textExpanderTriggers(SelectOptions::getEnvTextExpanderTriggers())),
            Field::make(t('Client key', category: 'commerce-worldpay'))
                ->required()
                ->control(Text::make('clientKey')
                    ->value($this->clientKey)
                    ->textExpanderTriggers(SelectOptions::getEnvTextExpanderTriggers())),
        ]);
    }

    #[Override]
    public function getPaymentFormModel(): BasePaymentForm
    {
        return new WorldpayPaymentForm();
    }

    /**
     * Worldpay.js renders the card fields in its own iframe, so the form is the container it renders into, with the
     * client key and the payment form namespace that `resources/js/paymentForm.js` reads.
     */
    #[Override]
    public function paymentFormUi(BasePaymentForm $form, array $params = []): ?Ui
    {
        HtmlStack::jsFile(self::WORLDPAY_JS_URL);
        HtmlStack::js((string)file_get_contents(dirname(__DIR__) . '/resources/js/paymentForm.js'), Position::BodyEnd, 'commerce-worldpay-payment-form');

        $html = Html::tag('div', Html::tag('div', '', ['id' => 'payment-section']), [
            'class' => 'worldpay-form',
            'data' => [
                'payment-form-namespace' => PaymentForm::getPaymentFormNamespace((string)$this->handle),
                'clientkey' => (string)$this->getClientKey(),
            ],
        ]);

        return Ui::make([
            TemplateContent::make('worldpay-form', $html)->trusted(),
        ]);
    }

    #[Override]
    public function purchase(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface
    {
        return $this->createOrder($transaction, $form, false);
    }

    #[Override]
    public function authorize(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface
    {
        return $this->createOrder($transaction, $form, true);
    }

    #[Override]
    public function capture(Transaction $transaction, string $reference): RequestResponseInterface
    {
        $response = $this->client()->captureOrder($reference, $this->amount($transaction->paymentAmount, (string)$transaction->paymentCurrency));

        return PaymentResponse::fromHttpResponse($response);
    }

    #[Override]
    public function refund(Transaction $transaction): RequestResponseInterface
    {
        // Refund transactions start with their parent’s reference, which is the Worldpay order code.
        $response = $this->client()->refundOrder((string)$transaction->reference, $this->amount($transaction->paymentAmount, (string)$transaction->paymentCurrency));

        return PaymentResponse::fromHttpResponse($response);
    }

    /**
     * Creates a Worldpay order with the token from Worldpay.js.
     */
    protected function createOrder(Transaction $transaction, BasePaymentForm $form, bool $authorizeOnly): RequestResponseInterface
    {
        $request = $this->buildOrderRequest($transaction, $form);

        if ($authorizeOnly) {
            $request['authorizeOnly'] = true;
        }

        $event = new PaymentRequestBuilding($transaction, $form, $request);
        event($event);

        return PaymentResponse::fromHttpResponse(
            $this->client()->createOrder($event->request),
            $authorizeOnly ? 'AUTHORIZED' : 'SUCCESS',
        );
    }

    /**
     * Builds the body of the Worldpay “Create order” request.
     *
     * @return array<string, mixed>
     */
    protected function buildOrderRequest(Transaction $transaction, BasePaymentForm $form): array
    {
        if (!$form instanceof CreditCardPaymentForm || !$form->token) {
            throw new InvalidArgumentException('The token parameter is required');
        }

        $order = $transaction->getOrder() ?? throw new InvalidArgumentException('The transaction has no order.');
        $currency = (string)$transaction->paymentCurrency;
        $billingAddress = $order->getBillingAddress();
        $shippingAddress = $order->getShippingAddress();

        // Names on the payment form take precedence, then the billing address, as with the Omnipay credit card.
        $firstName = $form->firstName ?: $billingAddress?->getGivenName();
        $lastName = $form->lastName ?: $billingAddress?->getFamilyName();
        $billingFirstName = $billingAddress?->getGivenName() ?: $firstName;
        $billingLastName = $billingAddress?->getFamilyName() ?: $lastName;

        $clientIp = (string)request()->ip();

        return [
            'token' => $form->token,
            'amount' => $this->amount($transaction->paymentAmount, $currency),
            'currencyCode' => $currency,
            'orderDescription' => t('Order', category: 'commerce') . ' #' . $transaction->orderId,
            'customerOrderCode' => $transaction->hash,
            'currency' => $currency,
            'billingAddress' => [
                'address1' => $billingAddress?->getAddressLine1(),
                'address2' => $billingAddress?->getAddressLine2(),
                'city' => $billingAddress?->getLocality(),
                'state' => $billingAddress?->getAdministrativeArea() ?: null,
                'countryCode' => $billingAddress?->getCountryCode() ?: null,
                'postalCode' => $billingAddress?->getPostalCode(),
                'telephoneNumber' => null,
            ],
            'name' => trim($billingFirstName . ' ' . $billingLastName),
            'deliveryAddress' => [
                'firstName' => $shippingAddress ? $shippingAddress->getGivenName() : $firstName,
                'lastName' => $shippingAddress ? $shippingAddress->getFamilyName() : $lastName,
                'address1' => $shippingAddress?->getAddressLine1(),
                'address2' => $shippingAddress?->getAddressLine2(),
                'city' => $shippingAddress?->getLocality(),
                'state' => $shippingAddress?->getAdministrativeArea() ?: null,
                'countryCode' => $shippingAddress?->getCountryCode() ?: null,
                'postalCode' => $shippingAddress?->getPostalCode(),
                'telephoneNumber' => null,
            ],
            'shopperEmailAddress' => $order->getEmail(),
            // Worldpay doesn’t accept the IPv6 loopback address.
            'shopperIpAddress' => $clientIp === '::1' ? '127.0.0.1' : $clientIp,
            'orderType' => 'ECOM',
        ];
    }

    /**
     * Returns the amount as an integer in the currency’s minor unit, such as cents.
     */
    protected function amount(float $amount, string $currency): int
    {
        return (int)app(Currencies::class)->getTeller($currency)->convertToMoney($amount)->getAmount();
    }

    protected function client(): Client
    {
        return new Client((string)$this->getServiceKey());
    }

    #[Override]
    public function completeAuthorize(Transaction $transaction): RequestResponseInterface
    {
        throw new NotImplementedException(t('Completing authorization is not supported by this gateway', category: 'commerce'));
    }

    #[Override]
    public function completePurchase(Transaction $transaction): RequestResponseInterface
    {
        throw new NotImplementedException(t('Completing purchase is not supported by this gateway', category: 'commerce'));
    }

    #[Override]
    public function createPaymentSource(BasePaymentForm $sourceData, int $customerId): PaymentSource
    {
        throw new NotImplementedException(t('Payment sources are not supported by this gateway', category: 'commerce'));
    }

    #[Override]
    public function deletePaymentSource(string $token): bool
    {
        throw new NotImplementedException(t('Payment sources are not supported by this gateway', category: 'commerce'));
    }

    #[Override]
    public function processWebHook(): Response
    {
        throw new NotImplementedException(t('Webhooks are not supported by this gateway', category: 'commerce-worldpay'));
    }

    #[Override]
    public function supportsAuthorize(): bool
    {
        return true;
    }

    #[Override]
    public function supportsCapture(): bool
    {
        return true;
    }

    #[Override]
    public function supportsCompleteAuthorize(): bool
    {
        return false;
    }

    #[Override]
    public function supportsCompletePurchase(): bool
    {
        return false;
    }

    #[Override]
    public function supportsPaymentSources(): bool
    {
        return false;
    }

    #[Override]
    public function supportsPurchase(): bool
    {
        return true;
    }

    #[Override]
    public function supportsRefund(): bool
    {
        return true;
    }

    #[Override]
    public function supportsPartialRefund(): bool
    {
        return true;
    }

    #[Override]
    public function supportsWebhooks(): bool
    {
        return false;
    }
}
