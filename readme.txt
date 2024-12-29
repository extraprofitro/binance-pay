=== BinancePay Checkout for WooCommerce ===
Contributors: BinancePay
Tags: binancepay, bitcoin, payments, crypto, cryptocurrency, ecommerce, e-commerce, commerce, wordpress ecommerce, store, sales, sell, shop, shopping, cart, checkout, binance
Requires at least: 5.0
Tested up to: 6.2
Requires PHP: 5.6
Stable tag: 1.1.9
License: Apache License, Version 2.0
License URI: http://www.apache.org/licenses/LICENSE-2.0

Binance Pay Checkout for WooCommerce.

== Description ==

Binance Pay is a contactless, borderless, and secure cryptocurrency payment technology designed by Binance. Binance Pay allows Binance customers to pay and get paid in crypto from their friends and family worldwide. WooCommerce Binance Pay provides comprehensive yet straightforward payment processing. It provides a seamless checkout experience for your consumers.

== Installation ==

= Requirements =

* [WooCommerce](https://wordpress.org/plugins/woocommerce/)
* BinancePay [merchant account](https://merchant.binance.com/en?utm_source=wordpress&utm_medium=referral&utm_campaign=woocommerce-merchant&ref=VIVBYAF1)

= Plugin installation =

1. Register a BinancePay Merchant Account [here](https://merchant.binance.com/en/onboarding?utm_source=wordpress&utm_medium=referral&utm_campaign=woocommerce-merchant&ref=VIVBYAF1) and perform the necessary KYC/KYB.
2. From your Wordpress admin panel, go to Plugins > Add New > Search plugins and search for **Binance Pay WooCommerce**.
3. Select **Binance Pay WooCommerce** and click on **Install Now** and then on **Activate Plugin**
4. Go to your WooCommerce settings and click **Binance Pay WooCommerce** to configure the plugin.

= Plugin configuration =

1. In the WordPress admin left menu, select [WooCommerce] > [Settings] > [Payments].
2. Under Method, check the “Enabled” checkbox for Binance Pay”.
3. Select [Manage] next to the Binance Pay plugin.
4. Enter your API credentials and you are ready to accept payment with Binance Pay.


== Frequently Asked Questions ==
Read more about Binance Pay [here](https://www.binance.com/en/support/faq/what-is-binance-pay-d6fabc736d1f4e7fb60e56afe6d1f3b9).

== Changelog ==
= 1.0.0 =
* Beta release
= 1.0.1 =
* Add cancel url for checkout
= 1.0.2 =
* Update readme.txt
= 1.0.3 =
* Update readme.txt
= 1.1.0 =
* Support the configuration of fiat currency, with fiat selection, your customers will pay you with equivalent USDT, and your settlement currency will be in USDT as well.
= 1.1.4 =
* Remove the BUSD, and change the default currency to USDT
= 1.1.5 =
* Fix the timestamp precision issue for versions of PHP prior to 7.3
= 1.1.7 =
* Fix the svg icon issue
= 1.1.8 =
* Add note on currency selection part
= 1.1.9 =
* Support to set USDC as the order currency