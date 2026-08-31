# payzum/payzum-php — official PHP SDK

Accept crypto and stablecoin payments (USDC/USDT, multi-chain) with
[Payzum](https://merchant.payzum.com). Non-custodial — funds settle to your own
wallet. Zero runtime dependencies (PHP ≥ 8.1 with curl).

```
composer require payzum/payzum-php
```

You need two values from your [Payzum dashboard](https://merchant.payzum.com):
an **API key** and a **webhook secret**.

## Charge a customer

```php
use Payzum\Payzum;

$payzum = new Payzum('your-api-key');       // Payzum::sandbox(...) for staging

$invoice = $payzum->payments->create(
    priceAmount: '49.99',                   // a string — never a float
    priceCurrency: 'usd',
    payCurrency: 'all',                     // the buyer picks the coin on the checkout page
    orderId: 'ORDER-12345',
    ipnCallbackUrl: 'https://your-shop.example/payzum/ipn',
    idempotencyKey: 'ORDER-12345',          // lets the SDK retry safely on network hiccups
);
header('Location: ' . $invoice['invoice_url']);
```

That's the whole flow: create, redirect, and wait for the webhook. Which coins
the buyer can pick is configured in your dashboard, not in code.

## Get notified when it's paid

Payzum POSTs a signed webhook (IPN) to your `ipnCallbackUrl`. Hand the SDK the
**raw request body** and the headers — it finds the right header, checks the
signature and rejects replays, all by itself:

```php
use Payzum\Errors\SignatureException;
use Payzum\PaymentStatus;
use Payzum\Webhooks\Verifier;

try {
    $verifier = new Verifier($webhookSecret);
    $data = $verifier->verifyPaymentIpn(
        file_get_contents('php://input'),   // RAW bytes, before any parsing
        getallheaders(),
    );
} catch (SignatureException $e) {
    http_response_code(401);
    exit('bad signature');
}

$status = PaymentStatus::fromMerchant($data['payment_status']);
if ($status->isPaid()) {
    fulfilOrder($data['order_id']);         // only fulfil on isPaid()
}
```

Deliveries can arrive more than once — deduplicate on
`$verifier->eventId($headers)` if a repeat must be a no-op on your side.

## Statuses

A payment is always in one of five states: `waiting`, `partially_paid`,
`finished`, `expired`, `failed`. Everything you need is on the enum:

- `$status->isPaid()` — safe to fulfil (covers overpayment too).
- `$status->isTerminal()` — nothing further will happen.
- `PaymentStatus::fromMerchant(...)` throws on anything unexpected, so a
  surprise value can never be mistaken for "paid".

## Errors

Everything throws a typed exception: `ApiException` (with `->errorCode`, e.g.
`AmountBelowMinimum`, to branch on — never branch on messages),
`SignatureException` for webhooks, `TransportException` for network failures,
`PayzumException` for local validation.

Transient failures are retried for you, honouring the server's back-off hints.
A create is **only** retried when you pass an `idempotencyKey`, and then in a
way that cannot double-charge.

## Amounts are exact

Amounts go in as strings and come back as exact decimal strings — the SDK
never converts money through a float in either direction.

## Testing your integration

`new Payzum($apiKey, Payzum::BASE_URL, $transport)` accepts any
`Payzum\Http\Transport` implementation, so you can test without touching the
network. The SDK's own suite runs with `composer test`.

Docs: <https://merchant.payzum.com/docs> · llms: <https://merchant.payzum.com/llms.txt>
