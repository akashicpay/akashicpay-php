# AkashicPay - PHP Library

A library to interact with the AkashicChain network, written in PHP.

# Installing

```bash
composer require akashic/akashic-pay
```

# Usage

```php
use Akashic\AkashicPay;
```

**Features**

- Send crypto via **Layer 1** and **Layer 2** (Akashic Chain)
- Create wallets for your users into which they can deposit crypto
- Fetch balance and transaction details
- Completely _Web3_: No login or API-key necessary. Just supply your Akashic
  private key, which stays on your server. The SDK signs your transactions with your key and sends them to Akashic Chain.
- Supports **Ethereum** and **Tron**

**Getting Started**

1. Create an account on AkashicLink (Google Chrome Extension or iPhone/Android
   App)
2. Visit [AkashicPay](https://www.akashicpay.com) and connect with AkashicLink.
   Set up the URL you wish to receive callbacks for.
3. Integrate the SDK in your code. This example configuration uses many optional build arguments, for illustration purposes:

```php
use Akashic\AkashicPay;
use Akashic\Constants\Environment;
use Akashic\Constants\ACNode;
use Akashic\Constants\ACDevNode;

$akashicPay = new AkashicPay([
    // in development, you will use our testnet and testnet L1 chains
    'environment' => getenv('environment') === 'production' ? Environment::PRODUCTION : Environment::DEVELOPMENT,
    // optional, the SDK will try to find the fastest node if omitted
    'targetNode' => getenv('environment') === 'production' ? ACNode::SINGAPORE_1 : ACDevNode::SINGAPORE_1,
    // use whatever secret management tool you prefer to load the private key
    // from your AkashicLink account. It should be of the form:
    // `"0x2d99270559d7702eadd1c5a483d0a795566dc76c18ad9d426c932de41bfb78b7"`
    // In development, each developer could have their own, or omit this (and
    // the l2Address), in which case the SDK will create and use a new pair.
    // you can instead use your Akashic Link account's 12-word phrase, using the
    // argument `recoveryPhrase`
    'privateKey' => getenv('akashicKey'),
    // this is the address of your AkashicLink account. Of the form "AS1234..."
    'l2Address' => getenv('l2Address'),
]);
```

AkashicPay is now fully setup and ready to use.

# Testing

You can also use AkashicPay with the AkashicChain Testnet & **Sepolia**
(Ethereum) and **Shasta** (Tron) testnets, useful for local development and
staging environments.
To do this, no AkashicLink is necessary; you can build an AkashicPay instance as follows, and the SDK will create a "test otk" for you:

```php
use Akashic\AkashicPay;
use Akashic\Constants\Environment;

// production is the default environment.
// And in production, an otk must be specified
$akashicPay = new AkashicPay([
    'environment' => Environment::DEVELOPMENT,
]);

// for security reasons, this would throw if the environment was production
// but you can use this in development to record and re-use your otk
$keyPair = $akashicPay->getKeyBackup();
print('my testing L2 address: ' . $keyPair['l2Address'] . ' and private key: ' . $keyPair['privateKey']);

```

Then, to reuse the same otk in later testing (recommended), simply supply the otk during setup:

```php
use Akashic\AkashicPay;
use Akashic\Constants\Environment;

// production is the default environment.
// And in production, an otk must be specified
$akashicPay = new AkashicPay([
    'environment' => Environment::DEVELOPMENT,
    'privateKey' => getenv('akashicKey'),
    'l2Address' => getenv('l2Address'),
]);

```

You can now create an L1-wallet on a testnet:

```php
use Akashic\Constants\NetworkSymbol;

$address = $akashicPay->getDepositAddress(NetworkSymbol::TRON_SHASTA, 'EndUser123');

```

Use a faucet (e.g. via the [Tron Discord](https://discord.com/invite/nSBF64yb5U)) to deposit some coin into the created wallet.
While you won't receive callbacks during testing, you can check to see if your balance has increased with:

```php
$balances = $akashicPay->getBalance();
// -> [{networkSymbol: 'TRX-SHASTA', balance: '5000'}, ...]
```

# Documentation

Link to further docs here

# Contributing

See [CONTRIBUTING.md](./CONTRIBUTING.md) for information
