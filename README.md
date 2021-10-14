<p align="center"><img src="./src/icon.svg" width="100" height="100" alt="Worldpay for Craft Commerce icon"></p>

<h1 align="center">Worldpay Online Payments for Craft Commerce</h1>

This plugin provides a [Worldpay](https://www.worldpay.com/) integration for [Craft Commerce](https://craftcms.com/commerce).

This integration uses Worldpay's [Online Payments](https://developer.worldpay.com/docs/wpop) API utilizing the features of the [Omnipay Worldpay library](https://github.com/thephpleague/omnipay-worldpay).

> **Note:** 3D secure is not currently implemented due to the underlying Omnipay library [not supporting the feature](https://github.com/thephpleague/omnipay-worldpay/issues/41).

## Requirements

This plugin requires Craft 4.0 and Craft Commerce 4.0 or later.

## Installation

You can install this plugin from the Plugin Store or with Composer.

#### From the Plugin Store

Go to the Plugin Store in your project’s Control Panel and search for “Worldpay for Craft Commerce”. Then click on the “Install” button in its modal window.

#### With Composer

Open your terminal and run the following commands:

```bash
# go to the project directory
cd /path/to/my-project.test

# tell Composer to load the plugin
composer require craftcms/commerce-worldpay

# tell Craft to install the plugin
./craft install/plugin commerce-worldpay
```

## Setup

To add a Worldpay payment gateway, go to Commerce → Settings → Gateways, create a new gateway, and set the gateway type to “Worldpay Json”.

> **Tip:** The Merchant ID, Service key, and Client key settings can be set to environment variables. See [Environmental Configuration](https://docs.craftcms.com/v3/config/environments.html) in the Craft docs to learn more about that.
