# Upgrading to Worldpay for Craft Commerce 5.0 (Commerce 6)

Worldpay for Craft Commerce 5.0 supports Craft Commerce 6. It talks to the Worldpay Online Payments JSON API directly, rather than through Omnipay.

> [!CAUTION]
> Worldpay retired Worldpay Online Payments (WPOP) on 14 October 2022. See Worldpay’s [WPOP end of life FAQs](https://developerengine.fisglobal.com/assets/pdf/wpopfaq.pdf). This release can’t take payments either.

## Requirements

- Craft CMS 6.0 and Craft Commerce 6.0 or later
- PHP 8.5 or later
- `craftcms/yii2-adapter` is not needed.

## Upgrade steps

1. Update to Worldpay for Craft Commerce 4.1.0 and Craft Commerce 5 first.
2. Upgrade your project to Craft 6 and Craft Commerce 6.
3. Update the plugin: `composer require craftcms/commerce-worldpay:^5.0`.
4. Review the breaking changes below.

Existing Worldpay gateways keep working: gateways saved with the old `craft\commerce\worldpay\gateways\Gateway` type load as `CraftCms\CommerceWorldpay\Gateway`, and save with the new class name the next time they’re saved.

## Breaking changes

### Omnipay

The plugin no longer depends on `craftcms/commerce-omnipay`, `omnipay/worldpay` or `omnipay/common`. If your project uses Omnipay classes directly, require those packages yourself.

### Events

The Omnipay gateway events are gone. To change the request sent to Worldpay when an order is created (for purchases and authorizations), listen for `CraftCms\CommerceWorldpay\Events\PaymentRequestBuilding` and modify its `$request` array, which is the JSON body of the `POST /v1/orders` request. Authorizations have `authorizeOnly` set to `true`.

| Before | After |
| --- | --- |
| `craft\commerce\omnipay\base\Gateway::EVENT_BEFORE_SEND_PAYMENT_REQUEST` | `CraftCms\CommerceWorldpay\Events\PaymentRequestBuilding` for purchases and authorizations. No replacement for captures and refunds. |
| `craft\commerce\omnipay\base\Gateway::EVENT_BEFORE_GATEWAY_REQUEST_SEND` | `CraftCms\CommerceWorldpay\Events\PaymentRequestBuilding` for purchases and authorizations. No replacement for captures and refunds. |
| `craft\commerce\omnipay\base\Gateway::EVENT_AFTER_CREATE_ITEM_BAG` | No replacement. Worldpay never received the item bag. |

Before:

```php
use craft\commerce\omnipay\events\SendPaymentRequestEvent;
use craft\commerce\worldpay\gateways\Gateway;
use yii\base\Event;

Event::on(Gateway::class, Gateway::EVENT_BEFORE_SEND_PAYMENT_REQUEST, function(SendPaymentRequestEvent $event) {
    $data = $event->requestData;
    $data['orderDescription'] = 'My Store order';
    $event->modifiedRequestData = $data;
});
```

After:

```php
use CraftCms\CommerceWorldpay\Events\PaymentRequestBuilding;
use Illuminate\Support\Facades\Event;

Event::listen(function(PaymentRequestBuilding $event): void {
    $event->request['orderDescription'] = 'My Store order';
});
```

The keys of the request are the same Worldpay parameter names Omnipay sent (`token`, `amount`, `currencyCode`, `orderDescription`, `customerOrderCode`, `billingAddress`, `deliveryAddress`, ...).

### Classes and namespaces

| Before | After |
| --- | --- |
| `craft\commerce\worldpay\Plugin` | `CraftCms\CommerceWorldpay\Plugin` |
| `craft\commerce\worldpay\gateways\Gateway` | `CraftCms\CommerceWorldpay\Gateway` (the old name is kept as an alias) |
| `craft\commerce\worldpay\models\WorldpayPaymentForm` | `CraftCms\CommerceWorldpay\Forms\WorldpayPaymentForm` |
| `craft\commerce\worldpay\WorldpayPaymentBundle` | Removed. `Gateway::paymentFormUi()` registers Worldpay.js and the payment form script. |
| `craft\commerce\omnipay\base\RequestResponse` | `CraftCms\CommerceWorldpay\Responses\PaymentResponse` |

The `merchantId`, `serviceKey` and `clientKey` settings are now public properties of the gateway. `getMerchantId()`, `getServiceKey()` and `getClientKey()` still return the parsed values, and accept `false` to return the raw ones.

### Settings

- The **Service key** and **Client key** settings are now required.
- The Omnipay “Send cart information” (`sendCartInfo`) setting was removed. Worldpay never received the cart information, so nothing changes.

### Templates and Twig

`gateway.getPaymentFormHtml()` still renders the payment form, with the same markup as before: a `.worldpay-form` container with `data-clientkey` and `data-payment-form-namespace` attributes, which Worldpay.js renders the card fields into. The payment form posts the same `worldpayToken` field as before, namespaced by the gateway handle (`paymentForm[<handle>][worldpayToken]`).

The plugin’s `commerce-worldpay/paymentForm` template is gone. If you rendered it yourself, use `gateway.getPaymentFormHtml()`. To change the payment form, extend the gateway and override `paymentFormUi()`, which returns a `CraftCms\Cms\Ui\Ui`:

```php
use CraftCms\Cms\Ui\Ui;
use CraftCms\Commerce\Payment\Forms\BasePaymentForm;
use CraftCms\CommerceWorldpay\Gateway;

class MyWorldpayGateway extends Gateway
{
    public function paymentFormUi(BasePaymentForm $form, array $params = []): ?Ui
    {
        $ui = parent::paymentFormUi($form, $params);
        // Add or replace nodes, keeping the `.worldpay-form` container paymentForm.js needs.
        return $ui;
    }
}
```

The `craft\commerce\worldpay\WorldpayPaymentBundle` asset bundle is gone. `paymentFormUi()` registers Worldpay.js and the payment form script with `HtmlStack`.

### Removed

- The install migration, which converted Craft Commerce 1 `craft\commerce\gateways\WorldPay_Json` gateways, was removed.
