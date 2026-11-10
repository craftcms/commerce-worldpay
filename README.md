<p align="center"><img src="./resources/icon.svg" width="100" height="100" alt="Worldpay for Craft Commerce icon"></p>

<h1 align="center">Worldpay Online Payments for Craft Commerce</h1>

This plugin provides a [Worldpay](https://www.worldpay.com/) integration for [Craft Commerce](https://craftcms.com/commerce).

This integration uses Worldpay’s Online Payments JSON API (`https://api.worldpay.com/v1`), with [Worldpay.js](https://cdn.worldpay.com/v1/worldpay.js) to collect and tokenize card details.

> [!CAUTION]
> Worldpay retired Worldpay Online Payments (WPOP) on 14 October 2022, and merchants can no longer take payments with it. See Worldpay’s [WPOP end of life FAQs](https://developerengine.fisglobal.com/assets/pdf/wpopfaq.pdf). This plugin can’t take payments until it’s rebuilt on one of Worldpay’s current APIs.

> [!NOTE]
> 3D Secure is not supported.

## Requirements

Worldpay 5.x requires Craft CMS 6.0, Craft Commerce 6.0 and PHP 8.5 or later.

Worldpay 4.1.x supports Craft 4 and Craft Commerce 4, or Craft 5 and Craft Commerce 5. If you’re upgrading from 4.x, see [UPGRADE.md](UPGRADE.md).

## Installation

You can install this plugin from the Plugin Store or with Composer.

#### From the Plugin Store

Go to the **Plugin Store** in your project’s control panel and search for “Worldpay for Craft Commerce”. Then click on the **Install** button in the sidebar.

#### With Composer

Open your terminal and run the following commands:

```bash
# Navigate to your project directory:
cd /path/to/my-project.test

# Require the plugin package with Composer:
composer require craftcms/commerce-worldpay

# Run the installer:
php artisan craft:plugin:install commerce-worldpay
```

## Setup

To add a Worldpay payment gateway, go to Commerce → Settings → Gateways, create a new gateway, and set the gateway type to “Worldpay Json”.

> [!NOTE]
> The **Merchant ID**, **Service key** and **Client key** settings can be set to environment variables. See [Environmental Configuration](https://craftcms.com/docs/6.x/configure.html#control-panel-settings) in the Craft docs to learn more about using secrets in configuration.

## Customizing payment requests

To change the request sent to Worldpay when an order is created (for purchases and authorizations), listen for the `PaymentRequestBuilding` event and modify its `request` array, which is the JSON body of the `POST /v1/orders` request.

```php
use CraftCms\CommerceWorldpay\Events\PaymentRequestBuilding;
use Illuminate\Support\Facades\Event;

Event::listen(function(PaymentRequestBuilding $event): void {
    $event->request['orderDescription'] = 'My Store order #' . $event->transaction->orderId;
});
```
