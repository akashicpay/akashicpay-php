<?php
declare(strict_types=1);

namespace Akashic;

use Akashic\Constants\ACDevNode;
use Akashic\Constants\ACNode;
use Akashic\Constants\AkashicBaseUrls;
use Akashic\Constants\AkashicEndpoints;
use Akashic\Constants\AkashicErrorCode;
use Akashic\Constants\AkashicException;
use Akashic\Constants\AkashicPayBaseUrls;
use Akashic\Constants\Environment;
use Akashic\Constants\NetworkSymbol;
use Akashic\Constants\TokenSymbol;
use Akashic\Constants\CurrencySymbol;
use Akashic\Constants\Networks;
use Akashic\Constants\CallbackEvent;
use Akashic\OTK\Otk;
use Akashic\Utils\Currency;
use Akashic\Utils\Prefix;
use Exception;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use function array_keys;
use function array_map;
use function array_merge;
use function array_unique;
use function http_build_query;
use function in_array;
use function preg_match;
use function sprintf;
use function str_contains;
use function str_starts_with;
use function strtotime;
use function urlencode;

/** @api */
class AkashicPay
{
    private const AC_PRIVATE_KEY_REGEX = '/^0x[a-f\d]{64}$/';
    private const L2_REGEX             = '/^AS[A-Fa-f\d]{64}$/';
    /** @var array */
    private $otk;
    /** @var array */
    private $targetNode;
    /** @var string */
    private $akashicUrl;
    /** @var string */
    private $akashicPayUrl;
    /** @var Logger */
    private $logger;
    /** @var string */
    private $env;
    /** @var HttpClient */
    private $httpClient;
    /** @var AkashicChain */
    private $akashicChain;
    /** @var boolean */
    private $isFxBp;
    /** @var string */
    private $apiSecret;

    public function __construct($args)
    {
        $this->env = $args["environment"] ?? Environment::PRODUCTION;

        $this->akashicUrl =
            $this->env === Environment::PRODUCTION
                ? AkashicBaseUrls::BASE_URL
                : AkashicBaseUrls::BASE_URL_DEV;

        $this->akashicPayUrl =
            $this->env === Environment::PRODUCTION
                ? AkashicPayBaseUrls::BASE_URL
                : AkashicPayBaseUrls::BASE_URL_DEV;

        // Logger initialization
        $this->logger = new Logger("AkashicPay");

        // standard output
        $stream = new StreamHandler("php://stdout", Logger::DEBUG);
        $this->logger->pushHandler($stream);

        $attributes  = [
            'hostname' => $_SERVER['SERVER_NAME'] ?? 'localhost',
            'service'  => 'php-sdk',
            'tags'     => 'env:' . $this->env,
        ];
        if (isset($args["l2Address"])) {
            $attributes['identity'] = $args["l2Address"];
        }

        $this->akashicChain = new AkashicChain($this->env, $this->logger);

        // Initialize HttpClient
        $this->httpClient = new HttpClient($this->logger);

        $this->targetNode = $args["targetNode"] ?? $this->chooseBestACNode();

        $this->apiSecret = $args["apiSecret"] ?? null;


        $checkIfBpResponse = $this->checkIfBp($args["l2Address"]);
        $this->isFxBp = $checkIfBpResponse["data"]["isFxBp"];
        // Only BPs can use SDK
        if (!$checkIfBpResponse["data"]["isBp"]) {
            throw new AkashicException(AkashicErrorCode::IS_NOT_BP);
        }

        if (isset($args["privateKey"]) && $args["privateKey"]) {
            $this->setOtkFromKeyPair(
                $args["privateKey"],
                $args["l2Address"]
            );
        } elseif (
            isset($args["recoveryPhrase"])
            && $args["recoveryPhrase"]
        ) {
            $this->setOtkFromRecoveryPhrase(
                $args["recoveryPhrase"],
                $args["l2Address"]
            );
        } else {
            throw new AkashicException(
                AkashicErrorCode::INCORRECT_PRIVATE_KEY_FORMAT
            );
        }

        $this->logger->info("AkashicPay instance initialised");
    }

    /**
     * Send a crypto-transaction
     *
     * @param  string      $recipientId userID or similar identifier of the user
     *                                  requesting the payout
     * @param  string      $to          L1 or L2 address of receiver
     * @param  string      $amount
     * @param  string      $network     L1-Network the funds belong to, e.g. `ETH`
     * @param  string|null $token       Optional. Include if sending token, e.g. `USDT`
     * @return array|string $l2Hash|$error L2 Transaction hash of the transaction or error
     */
    public function payout($recipientId, $to, $amount, $network, $token = null)
    {
        $toAddress        = $to;
        $initiatedToNonL2 = null;
        $isL2             = false;

        // convert to backend currency
        $decimalAmount = Currency::convertToDecimals($amount, $network, $token);

        $result    = $this->lookForL2Address($to, $network);
        $l2Address = $result["l2Address"] ?? null;

        if (
            preg_match(
                L1Network::NETWORK_DICTIONARY[$network]["regex"]["address"],
                $to
            )
        ) {
            // Sending by L1 address
            if ($l2Address) {
                $toAddress        = $result["l2Address"];
                $initiatedToNonL2 = $to;
                $isL2             = true;
            }
        } elseif (preg_match(self::L2_REGEX, $to)) {
            // Sending L2 by L2 address
            if (! $l2Address) {
                return [
                    "error" => AkashicErrorCode::L2_ADDRESS_NOT_FOUND,
                ];
            }
            $isL2 = true;
        } else {
            // Sending by alias
            if (! $l2Address) {
                return [
                    "error" => AkashicErrorCode::L2_ADDRESS_NOT_FOUND,
                ];
            }
            $toAddress        = $result["l2Address"];
            $initiatedToNonL2 = $to;
            $isL2             = true;
        }

        if ($isL2) {
            $l2Tx = $this->akashicChain->l2Transaction(
                [
                    "otk"              => $this->otk,
                    "amount"           => $decimalAmount,
                    "toAddress"        => $toAddress,
                    "coinSymbol"       => $network,
                    "tokenSymbol"      => $this->mapUSDTToTether($network, $token),
                    "initiatedToNonL2" => $initiatedToNonL2,
                    "identifier"       => $recipientId,
                ], $this->isFxBp
            );

            if ($this->isFxBp) {
                $response = $this->prepareL2Transaction($l2Tx);
                $l2Tx = $response['preparedTxn'];
            }

            $acResponse = $this->post($this->targetNode["node"], $l2Tx);

            $chainError = $this->akashicChain->checkForAkashicChainError($acResponse["data"]);
            if ($chainError) {
                return [
                    "error" => $chainError,
                ];
            }

            $this->logger->info(
                "Paid out "
                . $amount
                . " "
                . $token
                . " to user "
                . $recipientId
                . " at "
                . $to
            );

            return [
                "l2Hash" => Prefix::prefixWithAS($acResponse["data"]['$umid']),
            ];
        }

        $payload = [
            "toAddress"             => $to,
            "coinSymbol"            => $network,
            "amount"                => $amount,
            "tokenSymbol"           => $token,
            "identity"              => $this->otk["identity"],
            "identifier"            => $recipientId,
            "feeDelegationStrategy" => "Delegate",
        ];

            $response = $this->post(
                $this->akashicUrl . AkashicEndpoints::PREPARE_TX,
                $payload
            );
        try {
            $preparedTxn = $response["data"]["preparedTxn"];
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
            if (str_contains($e->getMessage(), 'exceeds total savings')) {
                return [
                    "error" => AkashicErrorCode::SAVINGS_EXCEEDED,
                ];
            } else {
                return [
                    "error" => AkashicErrorCode::UNKNOWN_ERROR,
                ];
            }
        }

            $signedTxn = $this->akashicChain->signTransaction(
                $preparedTxn,
                $this->otk
            );

            $acResponse = $this->post($this->targetNode["node"], $signedTxn);

        $chainError = $this->akashicChain->checkForAkashicChainError($acResponse["data"]);
        if ($chainError) {
            return [
                "error" => $chainError,
            ];
        }

        $this->logger->info(
            "Paid out "
            . $amount
            . " "
            . $token
            . " to user "
            . $recipientId
            . " at "
            . $to
        );

        return [
            "l2Hash" => Prefix::prefixWithAS($acResponse["data"]['$umid']),
        ];
    }

    /**
     * Get an L1-address on the specified network for a user to deposit into
     * 
     * @param  string $network     L1-network
     * @param  string $identifier  userID or similar identifier of the user
     *                             making the deposit
     * @param  string $referenceId referenceId to identify the order
     * @return array
     */
    public function getDepositAddress($network, $identifier, $referenceId = null)
    {
        return $this->getDepositAddressFunc($network, $identifier, $referenceId);
    }

    /**
     * Get an L1-address on the specified network for a user to deposit into
     *
     * @param  string $network     L1-network
     * @param  string $identifier  userID or similar identifier of the user
     *                             making the deposit
     * @param  string $referenceId referenceId to identify the order
     * @param  string $requestedCurrency CurrencySymbol requestedCurrency to identify the order
     * @param  string $requestedAmount requestedAmount to identify the order
     * @param  string $token       Optional. Include if sending token, e.g. `USDT`
     * @param  float|null $markupPercentage Optional. Include if you want to add a markup percentage to the requested amount
     * @return array
     */
    public function getDepositAddressWithRequestedValue(
        $network,
        $identifier,
        $referenceId,
        $requestedCurrency,
        $requestedAmount,
        $token = null,
        $markupPercentage = null
    ) {
        return $this->getDepositAddressFunc($network, $identifier, $referenceId, $token, $requestedCurrency, $requestedAmount, $markupPercentage);
    }


    private function getDepositAddressFunc($network, $identifier, $referenceId = null, $token = null, $requestedCurrency = null, $requestedAmount = null, $markupPercentage = null)
    {
        // Prevent using mainnets in development or testnets in production
        if (
            ($this->env === Environment::DEVELOPMENT && in_array($network, Networks::MAIN_NETS, true))
            || ($this->env === Environment::PRODUCTION && in_array($network, Networks::TEST_NETS, true))
        ) {
            throw new AkashicException(AkashicErrorCode::NETWORK_ENVIRONMENT_MISMATCH);
        }
        
        $response = $this->getByOwnerAndIdentifier(
            [
                "identifier" => $identifier,
                "coinSymbol" => $network,
            ]
        );

        $address = $response["address"] ?? null;
        $unassignedLedgerId = $response["unassignedLedgerId"];

        // unassignedLedgerId indiciate that the key is not assigned to an owner
        // and we need to assign it
        if ($address) {
            if ($unassignedLedgerId) {
                $tx = $this->akashicChain->assignKey([
                    "otk" => $this->otk,
                    "ledgerId" => $unassignedLedgerId,
                    "identifier" => $identifier,
                ]);
                $acResponse = $this->post($this->targetNode["node"], $tx);
        
                $assignedKey = $acResponse["data"]['$responses'][0] ?? null;
                if (! $assignedKey) {
                    $this->logger->warning(
                        "Key assigned on "
                        . $network
                        . " failed for identifier "
                        . $identifier
                        . ". Responses: "
                        . $acResponse["data"]
                    );
                    throw new AkashicException(AkashicErrorCode::ASSIGNED_KEY_FAILURE);
                }
            }
            if ($referenceId) {
                $payloadToSign = [
                    "identity"    => $this->otk["identity"],
                    "expires"     => strtotime("+1 minutes") * 1000,
                    "referenceId" => $referenceId,
                    "identifier"  => $identifier,
                    "toAddress"   => $address,
                    "coinSymbol"  => $network,
                ];
                if ($requestedCurrency && $requestedAmount) {
                    $payloadToSign["requestedValue"] = [
                        "currency" => $requestedCurrency,
                        "amount" => $requestedAmount,
                    ];
                }
                if ($markupPercentage) {
                    $payloadToSign["markupPercentage"] = $markupPercentage;
                }
                $result = $this->createDepositOrder(array_merge($payloadToSign, [
                    "signature" => $this->sign($payloadToSign),
                ]));
                return [
                    "address"    => $response["address"],
                    "identifier" => $identifier,
                    "referenceId" => $referenceId,
                    "requestedAmount" => $result["requestedValue"]["amount"] ?? $requestedAmount,
                    "requestedCurrency" => $result["requestedValue"]["currency"] ?? $requestedCurrency,
                    "network" => $result["coinSymbol"] ?? $network,
                    "token" => $result["tokenSymbol"] ?? $token,
                    "exchangeRate" => $result["exchangeRate"] ?? null,
                    "amount" => $result["amount"] ?? null,
                    "expires" => $result["expires"] ?? null,
                    "markupPercentage" => $result["markupPercentage"] ?? $markupPercentage,
                ];
            }

            return [
                "address"    => $response["address"],
                "identifier" => $identifier,
            ];
        }

        $tx       = $this->akashicChain->keyCreateTransaction($network, $this->otk);
        $response = $this->post($this->targetNode["node"], $tx);

        $newKey = $response["data"]['$responses'][0] ?? null;
        if (! $newKey) {
            $this->logger->warning(
                "Key creation on "
                . $network
                . " failed for identifier "
                . $identifier
                . ". Responses: "
                . $response["data"]
            );
            throw new AkashicException(AkashicErrorCode::KEY_CREATION_FAILURE);
        }

        $txBody       = $this->akashicChain->differentialConsensusTransaction(
            $this->otk,
            $newKey,
            $identifier
        );
        $diffResponse = $this->post($this->targetNode["node"], $txBody)["data"];

        if (
            isset($diffResponse['$responses'][0])
            && $diffResponse['$responses'][0] !== "confirmed"
        ) {
            $this->logger->warning(
                "Key creation on "
                . $network
                . " failed at differential consensus for identifier "
                . $identifier
                . ". Unhealthy key: "
                . $newKey
            );
            throw new AkashicException(AkashicErrorCode::UNHEALTHY_KEY);
        }

        if ($referenceId) {
            $payloadToSign = [
                "identity"    => $this->otk["identity"],
                "expires"     => strtotime("+1 minutes") * 1000,
                "referenceId" => $referenceId,
                "identifier"  => $identifier,
                "toAddress"   => $newKey["address"],
                "coinSymbol"  => $network,
            ];
            if ($requestedCurrency && $requestedAmount) {
                $payloadToSign["requestedValue"] = [
                    "currency" => $requestedCurrency,
                    "amount" => $requestedAmount,
                ];
            }
            if ($markupPercentage) {
                $payloadToSign["markupPercentage"] = $markupPercentage;
            }
            $result = $this->createDepositOrder(array_merge($payloadToSign, [
                "signature" => $this->sign($payloadToSign),
            ]));
            return [
                "address"    => $newKey["address"],
                "identifier" => $identifier,
                "referenceId" => $referenceId,
                "requestedAmount" => $result["requestedValue"]["amount"] ?? $requestedAmount,
                "requestedCurrency" => $result["requestedValue"]["currency"] ?? $requestedCurrency,
                "network" => $result["coinSymbol"] ?? $network,
                "token" => $result["tokenSymbol"] ?? $token,
                "exchangeRate" => $result["exchangeRate"] ?? null,
                "amount" => $result["amount"] ?? null,
                "expires" => $result["expires"] ?? null,
                "markupPercentage" => $result["markupPercentage"] ?? $markupPercentage,
            ];
        }

        return [
            "address"    => $newKey["address"],
            "identifier" => $identifier,
        ];
    }

    /**
     * Get deposit page url
     *
     * @param  string $identifier userID or similar identifier of the user
     *                            making the deposit
     * @param  string $referenceId optional referenceId to identify the order
     * @param  array  $receiveCurrencies optional currencies to be display on deposit page, comma separated
     * @return string
     */
    public function getDepositUrl($identifier, $referenceId = null, $receiveCurrencies = null, $redirectUrl = null)
    {
        return $this->getDepositUrlFunc($identifier, $referenceId, $receiveCurrencies, $redirectUrl);
    }

    /**
     * Get deposit page url with requested value
     * Callback will match the requested value
     *
     * @param  string $identifier userID or similar identifier of the user
     *                            making the deposit
     * @param  string $referenceId referenceId to identify the order
     * @param  string $requestedCurrency CurrencySymbol requestedCurrency to identify the order
     * @param  string $requestedAmount requestedAmount to identify the order
     * @param  array  $receiveCurrencies optional currencies to be display on deposit page, comma separated
     * @param  float|null $markupPercentage optional markup percentage to be applied to the requested amount
     * @param  string|null $redirectUrl optional redirect URL after deposit
     * @return string
     */
    public function getDepositUrlWithRequestedValue($identifier, $referenceId, $requestedCurrency, $requestedAmount, $receiveCurrencies = null, $markupPercentage = null, $redirectUrl = null)
    {
        return $this->getDepositUrlFunc($identifier, $referenceId, $receiveCurrencies, $redirectUrl, $requestedCurrency, $requestedAmount, $markupPercentage);
    }

    private function getDepositUrlFunc($identifier, $referenceId = null, $receiveCurrencies = null, $redirectUrl = null, $requestedCurrency = null, $requestedAmount = null, $markupPercentage = null)
    {
        // Perform asynchronous tasks sequentially
        $keys                = $this->getKeysByOwnerAndIdentifier(['identifier' => $identifier]);
        $supportedCurrencies = $this->getSupportedCurrencies();

        // Process supported currencies
        $supportedCurrencySymbols = array_unique(array_merge(...array_values($supportedCurrencies)));
        $existingKeys             = array_unique(array_map("self::getCoinSymbol", $keys));

        foreach ($supportedCurrencySymbols as $coinSymbol) {
            if (! in_array($coinSymbol, $existingKeys)) {
                $this->getDepositAddress($coinSymbol, $identifier);
            }
        }

        if ($referenceId) {
            $payloadToSign = [
                "identity"    => $this->otk["identity"],
                "expires"     => strtotime("+1 minutes") * 1000,
                "referenceId" => $referenceId,
                "identifier"  => $identifier,
            ];
            if ($requestedCurrency && $requestedAmount) {
                $payloadToSign["requestedValue"] = [
                    "currency" => $requestedCurrency,
                    "amount" => $requestedAmount,
                ];
            }
            if ($markupPercentage) {
                $payloadToSign["markupPercentage"] = $markupPercentage;
            }
            $this->createDepositOrder(array_merge($payloadToSign, [
                "signature" => $this->sign($payloadToSign),
            ]));
        }

        // Construct the deposit URL
        $url = "{$this->akashicPayUrl}/sdk/deposit?identity={$this->otk['identity']}&identifier={$identifier}";
        if ($referenceId) {
            $url .= "&referenceId={$referenceId}";
        }
        if ($redirectUrl) {
            // strtr and rtrim makes sure it follows base64url encoding
            $encodedRedirectUrl = rtrim(strtr(base64_encode($redirectUrl), '+/', '-_'), '=');
            $url .= "&redirectUrl={$encodedRedirectUrl}";
        }
        if ($receiveCurrencies) {
            $mappedReceiveCurrencies = join(",", array_map(
                [$this, "mapMainToTestCurrency"],
                $receiveCurrencies
            ));
            $url .= "&receiveCurrencies={$mappedReceiveCurrencies}";
        }
        return $url;
    }

    /**
     * Get exchange rates for all supported main-net coins in value of requested currency
     *
     * @param string $requestedCurrency
     * @return array
     */
    public function getExchangeRates(string $requestedCurrency): array
    {
        $url = $this->akashicUrl
            . AkashicEndpoints::EXCHANGE_RATES
            . '/' . urlencode($requestedCurrency);
        return $this->get($url)['data'];
    }

    /**
     * Check which L2-address an alias or L1-address belongs to. Or call with an
     * L2-address to verify it exists
     *
     * @param  string      $aliasOrL1OrL2Address
     * @param  string|null $network
     * @return array
     */
    public function lookForL2Address($aliasOrL1OrL2Address, $network = null)
    {
        $url =
            $this->akashicUrl
            . AkashicEndpoints::L2_LOOKUP
            . "?to="
            . urlencode($aliasOrL1OrL2Address);
        if ($network) {
            $url .= "&coinSymbol=" . urlencode($network);
        }
        return $this->get($url)["data"];
    }

    /**
     * Get all or a subset of transactions. Optionally paginated with `page` and `limit`.
     * Optionally parameters: `layer`, `status`, `startDate`, `endDate`, `hideSmallTransactions`.
     * `hideSmallTransactions` excludes values below 1 USD
     *
     * @param  array $getTransactionParams
     * @return array
     */
    public function getTransfers(array $getTransactionParams)
    {
        $queryParameters = array_merge(
            $getTransactionParams,
            [
                "identity"          => $this->otk["identity"],
            ]
        );
        $query           = http_build_query(
            array_map("self::boolsToString", $queryParameters)
        );
        $transactions    = $this->get(
            $this->akashicUrl
            . AkashicEndpoints::OWNER_TRANSACTION
            . "?"
            . $query
        )["data"]["transactions"];
        return $transactions;
    }

    /**
     * Get total balances, divided by Network and Token.
     *
     * @return array
     */
    public function getBalance()
    {
        $response = $this->get(
            $this->akashicUrl
            . AkashicEndpoints::OWNER_BALANCE
            . "?address="
            . $this->otk["identity"]
        )["data"];
        return array_map(
            function ($bal) {
                return [
                    "networkSymbol" => $bal["coinSymbol"],
                    "tokenSymbol" => $bal["tokenSymbol"],
                    "balance" => $bal["balance"],
                ];
            },
            $response["totalBalances"]
        );
    }

    /**
     * Get details of an individual transaction. Returns null if no
     * transaction found for the queried hash
     *
     * @param  string $l2TxHash l2Hash of transaction
     * @return array|null
     */
    public function getTransactionDetails($l2TxHash)
    {
        $response    = $this->get(
            $this->akashicUrl
            . AkashicEndpoints::TRANSACTIONS_DETAILS
            . "?l2Hash="
            . urlencode($l2TxHash)
        );
        $transaction = $response["data"]["transaction"] ?? null;
        if (! $transaction) {
            return null;
        }

        return $transaction;
    }

    /**
     * Prepares an L2 transaction by sending transaction data to the Akashic API.
     *
     * @param array $transactionData The transaction data required for the L2 withdrawal.
     * @return array The signed transactionData
     * @throws \GuzzleHttp\Exception\GuzzleException If the HTTP request fails.
     */
    protected function prepareL2Transaction($transactionData)
    {
        // Send a POST request to the Akashic API with the transaction data
        return $this->post($this->akashicUrl . AkashicEndpoints::PREPARE_L2_TXN, ['signedTx' => $transactionData])['data'];
    }

    /**
     * Get the currently supported currencies in AkashicPay
     *
     * @return array with the currency as the keys and a list of networks as the values
     */
    public function getSupportedCurrencies(): array
    {
        return $this->get(
            $this->akashicUrl
            . AkashicEndpoints::SUPPORTED_CURRENCIES
        )["data"];
    }

    /**
     * Create deposit order
     *
     * @return array
     */
    public function createDepositOrder($payload)
    {
        return $this->post(
            $this->akashicUrl
            . AkashicEndpoints::CREATE_DEPOSIT_ORDER,
            $payload
        )["data"];
    }

   
    /**
     * Check if BP
     *
     * @return array
     */ 
    public function checkIfBp($l2Address)
    {
        $checkIfBpUrl = $this->akashicUrl
            . AkashicEndpoints::IS_BP
            . "?address="
            . urlencode($l2Address);

        return $this->get($checkIfBpUrl);
    }

    /**
     * Get key by BP and identifier
     *
     * @return ?array address
     */
    public function getByOwnerAndIdentifier($getByOwnerAndIdentifierParams): ?array
    {
        $queryParameters = array_merge(
            $getByOwnerAndIdentifierParams,
            ["identity" => $this->otk["identity"], "usePreSeed" => true]
        );
        $query           = http_build_query(
            array_map("self::boolsToString", $queryParameters)
        );
        return $this->get(
            $this->akashicUrl
            . AkashicEndpoints::IDENTIFIER_LOOKUP
            . "?"
            . $query
        )["data"];
    }

    /**
     * Get all keys by BP and identifier
     *
     * @return ?array address
     */
    public function getKeysByOwnerAndIdentifier($getKeysByOwnerAndIdentifierParams): ?array
    {
        $queryParameters = array_merge(
            $getKeysByOwnerAndIdentifierParams,
            ["identity" => $this->otk["identity"]]
        );
        $query           = http_build_query(
            array_map("self::boolsToString", $queryParameters)
        );
        return $this->get(
            $this->akashicUrl
            . AkashicEndpoints::ALL_KEYS_OF_IDENTIFIER
            . "?"
            . $query
        )["data"];
    }

    /**
     * Experimental feature to onboard a new BP from SDK
     * 
     * @param string $l2Address L2 address of the BP
     * @param string $privateKey Private key of the BP
     * @return array OTK and apiPrivateKey
     */
    public function onboardBp($l2Address, $privateKey): array {
        $otk = array_merge(
            Otk::restoreOtkFromKeypair($privateKey),
            ["identity" => $l2Address]
        );
        $this->becomeBp($otk);
        $apiPrivateKey = $this->generateApiKeyPair($otk);
        return [
            'apiPrivateKey' => $apiPrivateKey["key"]["prv"]["pkcs8pem"]
        ];
    }

    /**
     * Experimental feature to add callback URLs for deposit and payout events from SDK
     * 
     * @param string $l2Address L2 address of the BP
     * @param string $privateKey Private key of the BP
     * @param array $params Parameters containing URLs for different events
     *                      e.g. pendingDepositUrl, confirmedDepositUrl, etc.
     */

    public function addCallbackUrls($l2Address, $privateKey, $params): void {
        $otk = array_merge(
            Otk::restoreOtkFromKeypair($privateKey),
            ["identity" => $l2Address]
        );
        $urls = [
            [
                "url" => $params["pendingDepositUrl"] ?? null,
                "events" => [CallbackEvent::PENDING_DEPOSIT],
            ],
            [
                "url" => $params["confirmedDepositUrl"] ?? null,
                "events" => [CallbackEvent::CONFIRMED_DEPOSIT],
            ],
            [
                "url" => $params["failedDepositUrl"] ?? null,
                "events" => [CallbackEvent::FAILED_DEPOSIT],
            ],
            [
                "url" => $params["pendingPayoutUrl"] ?? null,
                "events" => [CallbackEvent::PENDING_PAYOUT],
            ],
            [
                "url" => $params["confirmedPayoutUrl"] ?? null,
                "events" => [CallbackEvent::CONFIRMED_PAYOUT],
            ],
            [
                "url" => $params["failedPayoutUrl"] ?? null,
                "events" => [CallbackEvent::FAILED_PAYOUT],
            ],
        ];
        $filtered = array_values(array_filter($urls, function($item) {
            return !is_null($item['url']);
        }));
        foreach ($filtered as &$url) {
            $url["enabledCurrencies"] = [
                [
                    "coinSymbol" => $this->env === Environment::PRODUCTION
                        ? NetworkSymbol::ETHEREUM_MAINNET
                        : NetworkSymbol::ETHEREUM_SEPOLIA,
                    "currencies" => [$this->akashicChain::NITR0GEN_NATIVE_COIN, TokenSymbol::USDT],
                ],
                [
                    "coinSymbol" => $this->env === Environment::PRODUCTION
                        ? NetworkSymbol::TRON
                        : NetworkSymbol::TRON_SHASTA,
                    "currencies" => [$this->akashicChain::NITR0GEN_NATIVE_COIN, TokenSymbol::USDT],
                ],
            ];
        }

        $payloadToSign = [
            "identity"    => $otk["identity"],
            "expires"     => strtotime("+1 minutes") * 1000,
            "callbackUrls" => $filtered,
        ];
        $payload = array_merge($payloadToSign, [
            "signature" => $this->sign($payloadToSign, $otk),
        ]);
        // Retry setCallbackUrls operation up to 3 times with 1 second delay
        // Due to potential delay in secondary OTK generation and onboarding process
        $this->retryWithAttempts(function () use ($payload) {
            $this->post(
                $this->akashicUrl . AkashicEndpoints::SET_CALLBACK_URLS,
                $payload
            );
        }, 3, 1000000);
    }

    /**
     * Call AP to become a BP
     * 
     * @param array $otk OTK to become a BP with
     */
    private function becomeBp($otk): void {
        $payloadToSign = [
            "identity"    => $otk["identity"],
            "expires"     => strtotime("+1 minutes") * 1000,
        ];
        $payload = array_merge($payloadToSign, [
            "signature" => $this->sign($payloadToSign, $otk),
        ]);
        $this->post(
            $this->akashicUrl
            . AkashicEndpoints::BECOME_BP,
            $payload
        );
    }

    /**
     * Generate a new API/SDK key pair for integration
     *
     * @param array $otk OTK to generate the key pair from
     * @return array The generated API key pair
     */
    private function generateApiKeyPair($otk): array {
        $this->logger->info(
            "Generating new API/SDK key pair for integration."
        );
        $secondaryApiKeyPair       = Otk::generateOTK();
        $secondaryOtkTx = $this->akashicChain->secondaryOtkTransaction([
            "otk" => $otk,
            "newPubKey" => $secondaryApiKeyPair["key"]["pub"]["pkcs8pem"],
        ]);

        $this->post($this->akashicUrl . AkashicEndpoints::GENERATE_SECONDARY_OTK, ['signedTx' => $secondaryOtkTx]);

        return $secondaryApiKeyPair;
    }

    private function setCallbackUrls($payload) {
        try {
            $this->post(
                $this->akashicUrl
                . AkashicEndpoints::SET_CALLBACK_URLS,
                $payload
            );
            return true;
        } catch (Exception $e) {
            $this->logger->error("Failed to set callback URLs: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Finds an AkashicChain node to target for requests. The SDK will attempt to
     * find the fastest node for you.
     *
     * @Returns string The URL of an AC node on the network matching your environment
     * (production or development)
     */
    private function chooseBestACNode(): array
    {
        // TODO: implement race condition
        // $nodes = $this->env === Environment::PRODUCTION ? ACNode::NODES : ACDevNode::NODES;
        // $fastestNode = null;

        // foreach ($nodes as $node) {
        //     try {
        //         $response = $this->httpClient->get($node);
        //         if ($response->getStatusCode() === 200) {
        //             $fastestNode = $node;
        //             break;
        //         }
        //     } catch (\Exception $e) {
        //         // Handle exception or log it
        //     }
        // }

        // if (is_null($fastestNode)) {
        //     throw new \Exception('No available nodes');
        // }

        // $this->logger->info('Set target node as %s by testing for fastest', $fastestNode);
        // return $fastestNode;
        return $this->env === Environment::PRODUCTION
            ? ACNode::SINGAPORE_1
            : ACDevNode::SINGAPORE_1;
    }

    /**
     * Sets your OTK to sign transactions on AkashicChain (AC)
     *
     * @param string $privateKey private key from Akashic Link.
     * @param string $l2Address L2-address of your Akashic account
     */
    private function setOtkFromKeyPair(
        string $privateKey,
        string $l2Address
    ): void {
        if (! preg_match(self::AC_PRIVATE_KEY_REGEX, $privateKey)) {
            throw new AkashicException(AkashicErrorCode::INCORRECT_PRIVATE_KEY_FORMAT);
        }

        $this->otk = array_merge(
            Otk::restoreOtkFromKeypair($privateKey),
            ["identity" => $l2Address]
        );
        $this->logger->debug("OTK set from private key");
    }

    /**
     * Sets your OTK to sign transactions on AkashicChain (AC)
     *
     * @param string $recoveryPhrase the recovery phrase generated when you
     * created your Akashic Link account.
     * @param string $l2Address L2-address of your Akashic account
     */
    private function setOtkFromRecoveryPhrase(
        string $recoveryPhrase,
        string $l2Address
    ): void {
        $this->otk = array_merge(
            Otk::restoreOtkFromPhrase($recoveryPhrase),
            [
                "identity" => $l2Address,
            ]
        );
        $this->logger->debug("OTK set from recovery phrase");
    }

    private function post(string $url, $payload): ?array
    {
        $this->logger->info("POSTing to AC url");
        return $this->httpClient->post($url, $payload);
    }

    private function get(string $url): ?array
    {
        return $this->httpClient->get($url);
    }

    private function boolsToString($value)
    {
        if ($value === true) {
            return "true";
        }
        if ($value === false) {
            return "false";
        }
        return $value;
    }

    private function sign($data, $otk = null)
    {
        $otk = $otk ?? $this->otk;
        try {
            // Convert private key into the correct format
            $pemPrivate = "-----BEGIN EC PRIVATE KEY-----\n" . $otk["key"]["prv"]["pkcs8pem"] . "\n-----END EC PRIVATE KEY-----";

            if (str_starts_with($otk["key"]["prv"]["pkcs8pem"], '0x')) {
                $keyPair = new KeyPair('secp256k1', $otk["key"]["prv"]["pkcs8pem"]);
            } else {
                $keyPair = new KeyPair('secp256k1', $pemPrivate);
            }
            return $keyPair->sign($data);
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
            throw new Exception(
                "Invalid private key: " . $e->getMessage()
            );
        }
    }

    private function getCoinSymbol($key)
    {
        return $key['coinSymbol'];
    }

    /**
     * Verify HMAC signature against body and secret with sorted keys
     *
     * @param mixed  $body
     * @param string $signature
     * @return bool
     */
    public function verifySignature($body, string $signature): bool
    {
        if (empty($this->apiSecret)) {
            throw new Exception("API secret is empty");
        }
        try {
            $sorted = $this->sortKeys($body);
            $json = json_encode($sorted, JSON_UNESCAPED_SLASHES);
            if ($json === false) {
                return false;
            }
            $computed = hash_hmac('sha256', $json, $this->apiSecret);
            return hash_equals($computed, $signature);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Recursively sort array keys for consistent JSON serialization
     *
     * @param mixed $obj
     * @return mixed
     */
    private function sortKeys($obj)
    {
        if (is_array($obj)) {
            $keys = array_keys($obj);
            $isAssoc = $keys !== range(0, count($obj) - 1);
            if ($isAssoc) {
                ksort($obj, SORT_STRING);
                foreach ($obj as &$val) {
                    $val = $this->sortKeys($val);
                }
                return $obj;
            }
            return array_map([$this, 'sortKeys'], $obj);
        }
        return $obj;
    }

    /**
     * Map USDT to TETHER for Tron Shasta network
     *
     * @param string $coinSymbol
     * @param string|null $tokenSymbol
     * @return string|null
     */
    private function mapUSDTToTether(string $coinSymbol, ?string $tokenSymbol): ?string
    {
        if (!$tokenSymbol) {
            return null;
        }
        return $coinSymbol === NetworkSymbol::TRON_SHASTA && $tokenSymbol === TokenSymbol::USDT
            ? TokenSymbol::TETHER
            : $tokenSymbol;
    }

    /**
     * Map receive currency from mainnet to testnet if in development
     *
     * @param string|null $currency
     * @return string|null
     */
    private function mapMainToTestCurrency(?string $currency): ?string
    {
        if ($this->env === Environment::DEVELOPMENT && $currency === CurrencySymbol::ETH) {
            return 'SEP';
        }
        return $currency;
    }

    /**
     * Retry a callback up to $maxAttempts times with $delay microseconds between attempts
     *
     * @param callable $callback
     * @param int $maxAttempts
     * @param int $delay (microseconds)
     * @throws Exception
     */
    private function retryWithAttempts(callable $callback, int $maxAttempts = 3, int $delay = 1000000): void
    {
        $attempt = 0;
        while ($attempt < $maxAttempts) {
            try {
                $callback();
                return;
            } catch (Exception $e) {
                $attempt++;
                if ($attempt >= $maxAttempts) {
                    throw new Exception("Operation failed after $maxAttempts attempts: " . $e->getMessage());
                }
                usleep($delay);
            }
        }
    }
}
