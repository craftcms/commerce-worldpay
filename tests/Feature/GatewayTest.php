<?php

declare(strict_types=1);

use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\HtmlStack;
use CraftCms\Cms\Support\Html;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\LineItem\Enums\LineItemType;
use CraftCms\Commerce\Order\LineItem\LineItems;
use CraftCms\Commerce\Payment\Data\Transaction;
use CraftCms\Commerce\Payment\Gateway\Gateways;
use CraftCms\Commerce\Payment\Gateway\GatewayTypes;
use CraftCms\Commerce\Payment\Models\Transaction as TransactionModel;
use CraftCms\Commerce\Payment\Payments;
use CraftCms\Commerce\Payment\Transactions;
use CraftCms\Commerce\Shipping\ShippingCategories;
use CraftCms\Commerce\Tax\TaxCategories;
use CraftCms\CommerceWorldpay\Events\PaymentRequestBuilding;
use CraftCms\CommerceWorldpay\Forms\WorldpayPaymentForm;
use CraftCms\CommerceWorldpay\Gateway;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

const WORLDPAY_ORDER_CODE = 'e0bf69e8-8c98-4e01-893b-d040fa41dd9b';

function worldpayGateway(array $settings = ['merchantId' => 'merchant', 'serviceKey' => 'T_S_service', 'clientKey' => 'T_C_client'], string $paymentType = 'purchase'): Gateway
{
    /** @var Gateway $gateway */
    $gateway = app(Gateways::class)->createGateway([
        'type' => Gateway::class,
        'name' => 'Worldpay',
        'handle' => 'worldpay',
        'paymentType' => $paymentType,
        'settings' => $settings,
    ]);

    return $gateway;
}

/**
 * Saves a gateway and an order, and returns a transaction for it.
 */
function worldpayTransaction(float $amount = 12.3, string $type = TransactionModel::TYPE_PURCHASE): Transaction
{
    $gateway = worldpayGateway(paymentType: $type === TransactionModel::TYPE_AUTHORIZE ? 'authorize' : 'purchase');
    app(Gateways::class)->saveGateway($gateway);

    $order = new Order();
    $order->email = 'customer@example.com';
    $order->gatewayId = $gateway->id;
    Elements::saveElement($order);

    $transaction = app(Transactions::class)->createTransaction($order, null, $type);
    $transaction->paymentAmount = $amount;
    $transaction->amount = $amount;

    return $transaction;
}

function worldpayForm(): WorldpayPaymentForm
{
    $form = new WorldpayPaymentForm();
    $form->setAttributes(['worldpayToken' => 'TEST_RU_7a22d2ec-6725-48b7-b8e7-243f03914b27']);

    return $form;
}

/**
 * A Worldpay order, as in the API responses recorded by omnipay/worldpay’s test suite.
 */
function worldpayOrder(array $attributes = []): array
{
    return array_merge([
        'orderCode' => WORLDPAY_ORDER_CODE,
        'token' => 'TEST_RU_7a22d2ec-6725-48b7-b8e7-243f03914b27',
        'orderDescription' => 'Order #4',
        'amount' => 500,
        'currencyCode' => 'GBP',
        'paymentStatus' => 'SUCCESS',
        'paymentResponse' => ['type' => 'ObfuscatedCard', 'name' => 'Jenny Andrews', 'maskedCardNumber' => '**** **** **** 4444'],
        'environment' => 'TEST',
    ], $attributes);
}

it('registers the gateway type', function() {
    expect(app(GatewayTypes::class)->types()->all())->toContain(Gateway::class);
});

it('loads gateways saved with the Craft Commerce 5 class name', function() {
    $gateway = app(Gateways::class)->createGateway([
        'type' => 'craft\\commerce\\worldpay\\gateways\\Gateway',
        // Commerce 5 also saved Omnipay’s `sendCartInfo` setting, which the gateway ignores now.
        'settings' => ['merchantId' => '$WORLDPAY_MERCHANT_ID', 'serviceKey' => '$WORLDPAY_SERVICE_KEY', 'clientKey' => '$WORLDPAY_CLIENT_KEY', 'sendCartInfo' => false],
    ]);

    expect($gateway)->toBeInstanceOf(Gateway::class)
        ->and($gateway->getSettings())->toBe(['merchantId' => '$WORLDPAY_MERCHANT_ID', 'serviceKey' => '$WORLDPAY_SERVICE_KEY', 'clientKey' => '$WORLDPAY_CLIENT_KEY'])
        ->and($gateway->getConfig()['type'])->toBe(Gateway::class);
});

it('parses environment variables in the settings', function() {
    putenv('WORLDPAY_TEST_SERVICE_KEY=T_S_from_env');
    $gateway = worldpayGateway(['serviceKey' => '$WORLDPAY_TEST_SERVICE_KEY']);

    expect($gateway->getServiceKey())->toBe('T_S_from_env')
        ->and($gateway->getServiceKey(false))->toBe('$WORLDPAY_TEST_SERVICE_KEY');
    putenv('WORLDPAY_TEST_SERVICE_KEY');
});

it('requires the service key and client key', function() {
    $gateway = worldpayGateway(['merchantId' => '', 'serviceKey' => '', 'clientKey' => '']);

    expect($gateway->validate())->toBeFalse()
        ->and($gateway->errors()->keys())->toContain('serviceKey', 'clientKey')
        ->not->toContain('merchantId');

    expect(worldpayGateway()->validate())->toBeTrue();
});

it('renders the settings', function() {
    $html = worldpayGateway(['merchantId' => '$WORLDPAY_MERCHANT_ID', 'serviceKey' => '$WORLDPAY_SERVICE_KEY', 'clientKey' => '$WORLDPAY_CLIENT_KEY'])->getSettingsHtml();

    expect($html)->toContain('merchantId')->toContain('$WORLDPAY_MERCHANT_ID')
        ->toContain('serviceKey')->toContain('$WORLDPAY_SERVICE_KEY')
        ->toContain('clientKey')->toContain('$WORLDPAY_CLIENT_KEY');
});

it('supports purchase, authorize, capture and partial refunds', function() {
    $gateway = worldpayGateway();

    expect($gateway->supportsPurchase())->toBeTrue()
        ->and($gateway->supportsAuthorize())->toBeTrue()
        ->and($gateway->supportsCapture())->toBeTrue()
        ->and($gateway->supportsRefund())->toBeTrue()
        ->and($gateway->supportsPartialRefund())->toBeTrue()
        ->and($gateway->supportsCompletePurchase())->toBeFalse()
        ->and($gateway->supportsCompleteAuthorize())->toBeFalse()
        ->and($gateway->supportsPaymentSources())->toBeFalse()
        ->and($gateway->supportsWebhooks())->toBeFalse()
        ->and(array_keys($gateway->getPaymentTypeOptions()))->toBe(['authorize', 'purchase']);
});

it('creates an order with the Worldpay.js token', function() {
    Http::fake(['api.worldpay.com/v1/orders' => Http::response(worldpayOrder())]);
    $transaction = worldpayTransaction(12.3);
    $order = $transaction->getOrder();
    $order->setBillingAddress([
        'firstName' => 'Jenny',
        'lastName' => 'Andrews',
        'addressLine1' => '1 Main St',
        'addressLine2' => 'Suite 2',
        'locality' => 'Portland',
        'administrativeArea' => 'OR',
        'postalCode' => '97201',
        'countryCode' => 'US',
    ]);
    $order->setShippingAddress([
        'firstName' => 'Sam',
        'lastName' => 'Smith',
        'addressLine1' => '2 Side St',
        'locality' => 'London',
        'postalCode' => 'SW1A 1AA',
        'countryCode' => 'GB',
    ]);
    request()->server->set('REMOTE_ADDR', '203.0.113.7');

    $response = $transaction->getGateway()->purchase($transaction, worldpayForm());

    expect($response->isSuccessful())->toBeTrue()
        ->and($response->isRedirect())->toBeFalse()
        ->and($response->isProcessing())->toBeFalse()
        ->and($response->getTransactionReference())->toBe(WORLDPAY_ORDER_CODE)
        ->and($response->getMessage())->toBe('SUCCESS')
        ->and($response->getCode())->toBe('')
        ->and($response->getData())->toBe(worldpayOrder());

    Http::assertSentCount(1);
    Http::assertSent(function(Request $request) use ($transaction) {
        expect($request->url())->toBe('https://api.worldpay.com/v1/orders')
            ->and($request->method())->toBe('POST')
            // Worldpay takes the service key as-is, without a `Bearer` prefix.
            ->and($request->header('Authorization'))->toBe(['T_S_service'])
            ->and($request->header('Content-Type'))->toBe(['application/json'])
            ->and($request->data())->toBe([
                'token' => 'TEST_RU_7a22d2ec-6725-48b7-b8e7-243f03914b27',
                'amount' => 1230,
                'currencyCode' => 'USD',
                'orderDescription' => 'Order #' . $transaction->orderId,
                'customerOrderCode' => $transaction->hash,
                'currency' => 'USD',
                'billingAddress' => [
                    'address1' => '1 Main St',
                    'address2' => 'Suite 2',
                    'city' => 'Portland',
                    'state' => 'OR',
                    'countryCode' => 'US',
                    'postalCode' => '97201',
                    'telephoneNumber' => null,
                ],
                'name' => 'Jenny Andrews',
                'deliveryAddress' => [
                    'firstName' => 'Sam',
                    'lastName' => 'Smith',
                    'address1' => '2 Side St',
                    'address2' => null,
                    'city' => 'London',
                    'state' => null,
                    'countryCode' => 'GB',
                    'postalCode' => 'SW1A 1AA',
                    'telephoneNumber' => null,
                ],
                'shopperEmailAddress' => 'customer@example.com',
                'shopperIpAddress' => '203.0.113.7',
                'orderType' => 'ECOM',
            ]);

        return true;
    });
});

it('sends amounts in the currency’s minor unit', function(string $currency, float $amount, int $expected) {
    Http::fake(['api.worldpay.com/v1/orders' => Http::response(worldpayOrder())]);
    $transaction = worldpayTransaction($amount);
    $transaction->paymentCurrency = $currency;

    $transaction->getGateway()->purchase($transaction, worldpayForm());

    Http::assertSent(fn(Request $request) => $request['amount'] === $expected && $request['currencyCode'] === $currency);
})->with([
    'cents' => ['USD', 0.29, 29],
    'zero-decimal currency' => ['JPY', 1234.0, 1234],
    'three-decimal currency' => ['KWD', 1.234, 1234],
]);

it('sends the names from the billing address when there is no shipping address', function() {
    Http::fake(['api.worldpay.com/v1/orders' => Http::response(worldpayOrder())]);
    $transaction = worldpayTransaction();
    $transaction->getOrder()->setBillingAddress(['firstName' => 'Jenny', 'lastName' => 'Andrews', 'countryCode' => 'US']);

    $transaction->getGateway()->purchase($transaction, worldpayForm());

    Http::assertSent(fn(Request $request) => $request['name'] === 'Jenny Andrews'
        && $request['deliveryAddress']['firstName'] === 'Jenny'
        && $request['deliveryAddress']['lastName'] === 'Andrews'
        && $request['deliveryAddress']['countryCode'] === null);
});

it('sends the IPv4 loopback address instead of the IPv6 one', function() {
    Http::fake(['api.worldpay.com/v1/orders' => Http::response(worldpayOrder())]);
    $transaction = worldpayTransaction();
    request()->server->set('REMOTE_ADDR', '::1');

    $transaction->getGateway()->purchase($transaction, worldpayForm());

    Http::assertSent(fn(Request $request) => $request['shopperIpAddress'] === '127.0.0.1');
});

it('maps the payment status of purchases and authorizations', function(string $paymentType, array $order, bool $successful, string $message) {
    Http::fake(['api.worldpay.com/v1/orders' => Http::response(worldpayOrder($order))]);
    $transaction = worldpayTransaction(type: $paymentType);

    $gateway = $transaction->getGateway();
    $response = $paymentType === 'authorize'
        ? $gateway->authorize($transaction, worldpayForm())
        : $gateway->purchase($transaction, worldpayForm());

    expect($response->isSuccessful())->toBe($successful)
        ->and($response->isProcessing())->toBeFalse()
        ->and($response->isRedirect())->toBeFalse()
        ->and($response->getMessage())->toBe($message)
        ->and($response->getTransactionReference())->toBe(WORLDPAY_ORDER_CODE);

    Http::assertSent(fn(Request $request) => ($request['authorizeOnly'] ?? null) === ($paymentType === 'authorize' ? true : null));
})->with([
    'purchase, success' => ['purchase', ['paymentStatus' => 'SUCCESS'], true, 'SUCCESS'],
    'purchase, refused' => ['purchase', ['paymentStatus' => 'FAILED', 'paymentStatusReason' => 'REFUSED'], false, 'REFUSED'],
    'purchase, only authorized' => ['purchase', ['paymentStatus' => 'AUTHORIZED'], false, 'AUTHORIZED'],
    'authorize, authorized' => ['authorize', ['paymentStatus' => 'AUTHORIZED'], true, 'AUTHORIZED'],
    'authorize, refused' => ['authorize', ['paymentStatus' => 'FAILED', 'paymentStatusReason' => 'REFUSED'], false, 'REFUSED'],
    'authorize, unexpected success' => ['authorize', ['paymentStatus' => 'SUCCESS'], false, 'SUCCESS'],
]);

it('reports Worldpay API errors', function() {
    Http::fake(['api.worldpay.com/v1/orders' => Http::response([
        'httpStatusCode' => 400,
        'customCode' => 'BAD_REQUEST',
        'message' => 'This card is not accepted for Test transactions',
        'description' => 'Some of request parameters are invalid, please check your request. For more information please refer to Json schema.',
        'errorHelpUrl' => null,
    ], 400)]);
    $transaction = worldpayTransaction();

    $response = $transaction->getGateway()->purchase($transaction, worldpayForm());

    expect($response->isSuccessful())->toBeFalse()
        ->and($response->getCode())->toBe('BAD_REQUEST')
        ->and($response->getMessage())->toBe('This card is not accepted for Test transactions')
        ->and($response->getTransactionReference())->toBe('');
});

it('fails on an empty error response', function() {
    Http::fake(['api.worldpay.com/v1/orders' => Http::response('', 400)]);
    $transaction = worldpayTransaction();

    $response = $transaction->getGateway()->purchase($transaction, worldpayForm());

    expect($response->isSuccessful())->toBeFalse()
        ->and($response->getMessage())->toBe('')
        ->and($response->getData())->toBeNull();
});

it('does not treat a non-200 response with a successful status as paid', function() {
    Http::fake(['api.worldpay.com/v1/orders' => Http::response(worldpayOrder(), 201)]);
    $transaction = worldpayTransaction();

    expect($transaction->getGateway()->purchase($transaction, worldpayForm())->isSuccessful())->toBeFalse();
});

it('requires a token', function() {
    Http::fake();
    $transaction = worldpayTransaction();

    expect(fn() => $transaction->getGateway()->purchase($transaction, new WorldpayPaymentForm()))
        ->toThrow(InvalidArgumentException::class, 'The token parameter is required');
    Http::assertNothingSent();
});

it('lets listeners modify the order request', function() {
    Http::fake(['api.worldpay.com/v1/orders' => Http::response(worldpayOrder())]);
    Event::listen(function(PaymentRequestBuilding $event) {
        $event->request['orderDescription'] = 'Custom description';
        unset($event->request['deliveryAddress']);
    });
    $transaction = worldpayTransaction();

    $transaction->getGateway()->purchase($transaction, worldpayForm());

    Http::assertSent(fn(Request $request) => $request['orderDescription'] === 'Custom description' && !isset($request['deliveryAddress']));
});

it('captures an authorized order through Commerce', function() {
    Http::fake(['api.worldpay.com/v1/orders/' . WORLDPAY_ORDER_CODE . '/capture' => Http::response(worldpayOrder(['paymentStatus' => 'SUCCESS']))]);
    $authorization = worldpayTransaction(5.0, TransactionModel::TYPE_AUTHORIZE);
    $authorization->reference = WORLDPAY_ORDER_CODE;
    $authorization->status = TransactionModel::STATUS_SUCCESS;
    $authorization->response = worldpayOrder(['paymentStatus' => 'AUTHORIZED']);
    app(Transactions::class)->saveTransaction($authorization);

    $capture = app(Payments::class)->captureTransaction($authorization);

    expect($capture->type)->toBe(TransactionModel::TYPE_CAPTURE)
        ->and($capture->status)->toBe(TransactionModel::STATUS_SUCCESS)
        ->and($capture->reference)->toBe(WORLDPAY_ORDER_CODE)
        ->and($capture->message)->toBe('');

    Http::assertSent(fn(Request $request) => $request->url() === 'https://api.worldpay.com/v1/orders/' . WORLDPAY_ORDER_CODE . '/capture'
        && $request->hasHeader('Authorization', 'T_S_service')
        && $request->data() === ['captureAmount' => 500]);
});

it('reports capture errors', function() {
    Http::fake(['api.worldpay.com/v1/orders/*' => Http::response(['httpStatusCode' => 400, 'customCode' => 'BAD_REQUEST', 'message' => 'Order cannot be captured'], 400)]);
    $transaction = worldpayTransaction(5.0, TransactionModel::TYPE_AUTHORIZE);

    $response = $transaction->getGateway()->capture($transaction, WORLDPAY_ORDER_CODE);

    expect($response->isSuccessful())->toBeFalse()
        ->and($response->getCode())->toBe('BAD_REQUEST')
        ->and($response->getMessage())->toBe('Order cannot be captured');
});

it('refunds part of an order through Commerce', function() {
    // Worldpay responds to a refund with an empty body.
    Http::fake(['api.worldpay.com/v1/orders/' . WORLDPAY_ORDER_CODE . '/refund' => Http::response('', 200)]);
    $purchase = worldpayTransaction(20.0);
    $purchase->reference = WORLDPAY_ORDER_CODE;
    $purchase->status = TransactionModel::STATUS_SUCCESS;
    $purchase->response = worldpayOrder();
    app(Transactions::class)->saveTransaction($purchase);

    $refund = app(Payments::class)->refundTransaction($purchase, 5.25, 'Damaged');

    expect($refund->status)->toBe(TransactionModel::STATUS_SUCCESS)
        ->and($refund->type)->toBe(TransactionModel::TYPE_REFUND)
        ->and($refund->message)->toBe('');

    Http::assertSent(fn(Request $request) => $request->url() === 'https://api.worldpay.com/v1/orders/' . WORLDPAY_ORDER_CODE . '/refund'
        && $request->hasHeader('Authorization', 'T_S_service')
        && $request->data() === ['refundAmount' => 525]);
});

it('reports refund errors', function() {
    Http::fake(['api.worldpay.com/v1/orders/*' => Http::response([
        'httpStatusCode' => 400,
        'customCode' => 'BAD_REQUEST',
        'message' => 'TEST Order: ' . WORLDPAY_ORDER_CODE . ' cannot be refunded while in status: REFUNDED - try again later.',
    ], 400)]);
    $purchase = worldpayTransaction(20.0);
    $purchase->reference = WORLDPAY_ORDER_CODE;
    $purchase->status = TransactionModel::STATUS_SUCCESS;
    app(Transactions::class)->saveTransaction($purchase);

    $refund = app(Transactions::class)->createTransaction(null, $purchase, TransactionModel::TYPE_REFUND);
    $response = $purchase->getGateway()->refund($refund);

    expect($response->isSuccessful())->toBeFalse()
        ->and($response->getCode())->toBe('BAD_REQUEST')
        ->and($response->getMessage())->toBe('TEST Order: ' . WORLDPAY_ORDER_CODE . ' cannot be refunded while in status: REFUNDED - try again later.');
});

it('takes a payment through Commerce', function() {
    Http::fake(['api.worldpay.com/v1/orders' => Http::response(worldpayOrder())]);
    $order = worldpayTransaction()->getOrder();
    $order->setLineItems([app(LineItems::class)->create($order, [
        'sku' => 'custom-sku',
        'description' => 'Custom',
        'price' => 10.5,
        'qty' => 2,
        'taxCategoryId' => app(TaxCategories::class)->getDefaultTaxCategory()->id,
        'shippingCategoryId' => app(ShippingCategories::class)->getDefaultShippingCategory($order->storeId)->id,
    ], LineItemType::Custom)]);
    Elements::saveElement($order);

    $redirect = null;
    $transaction = null;
    app(Payments::class)->processPayment($order, worldpayForm(), $redirect, $transaction);

    expect($transaction->status)->toBe(TransactionModel::STATUS_SUCCESS)
        ->and($transaction->reference)->toBe(WORLDPAY_ORDER_CODE)
        ->and($transaction->message)->toBe('SUCCESS')
        ->and($transaction->response)->toBe(worldpayOrder())
        ->and($redirect)->toBeEmpty();

    Http::assertSent(fn(Request $request) => $request['amount'] === 2100 && !isset($request['authorizeOnly']));
});

it('reads the token posted by Worldpay.js', function() {
    $form = new WorldpayPaymentForm();
    $form->setAttributes(['worldpayToken' => 'TEST_RU_token']);

    expect($form->token)->toBe('TEST_RU_token')
        ->and($form->validate())->toBeTrue();
});

it('requires card details when there is no token', function() {
    $form = new WorldpayPaymentForm();
    $form->setAttributes([]);

    expect($form->validate())->toBeFalse()
        ->and($form->errors()->keys())->toContain('firstName', 'lastName', 'number', 'month', 'year', 'cvv');
});

it('renders the payment form and registers Worldpay.js', function() {
    HtmlStack::bodyEndHtml();

    $html = worldpayGateway()->getPaymentFormHtml([]);
    $bodyEnd = HtmlStack::bodyEndHtml();

    expect($html)->toContain('class="worldpay-form"')
        ->toContain('data-payment-form-namespace="paymentForm[worldpay]"')
        ->toContain('data-clientkey="T_C_client"')
        ->toContain('<div id="payment-section"></div>')
        // Worldpay.js renders the card fields, so the form has no inputs of its own.
        ->not->toContain('<input')
        // Untrusted template content would be inert, and Worldpay.js couldn't render into it.
        ->not->toContain('inert')
        ->and($bodyEnd)->toContain('<script src="https://cdn.worldpay.com/v1/worldpay.js"></script>')
        ->toContain('Worldpay.useTemplateForm(')
        ->toContain("paymentFormNamespace + '[worldpayToken]'");
});

it('parses and escapes the client key in the payment form', function() {
    putenv('WORLDPAY_TEST_CLIENT_KEY=T_C_"quoted"<key>');

    $html = worldpayGateway(['serviceKey' => 'T_S_service', 'clientKey' => '$WORLDPAY_TEST_CLIENT_KEY'])->getPaymentFormHtml([]);

    expect($html)->toContain('data-clientkey="T_C_&quot;quoted&quot;&lt;key&gt;"')
        ->not->toContain('$WORLDPAY_TEST_CLIENT_KEY');
    putenv('WORLDPAY_TEST_CLIENT_KEY');
});

it('keeps the payment form markup when the control panel namespaces it', function() {
    $html = Html::namespaceInputs((string)worldpayGateway()->getPaymentFormHtml([]), 'paymentForm[worldpay]');

    // paymentForm.js reads the container ID from the DOM, so a namespaced ID still works.
    expect($html)->toContain('class="worldpay-form"')
        ->toContain('data-payment-form-namespace="paymentForm[worldpay]"')
        ->toContain('data-clientkey="T_C_client"')
        ->toMatch('/<div id="[^"]*payment-section"><\\/div>/');
});
