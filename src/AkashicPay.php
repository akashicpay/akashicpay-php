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
use Akashic\OTK\Otk;
use Akashic\Utils\Currency;
use Akashic\Utils\DatadogHandler;
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
    private const DATADOG_API_KEY      = '10f3796eb5494075b36b7d89ae456a65';
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

        // send log to datadog by http
        $attributes  = [
            'hostname' => $_SERVER['SERVER_NAME'] ?? 'localhost',
            'service'  => 'php-sdk',
            'tags'     => 'env:' . $this->env,
        ];
        if (isset($args["l2Address"])) {
            $attributes['identity'] = $args["l2Address"];
        }
        $datadogLogs = new DatadogHandler(self::DATADOG_API_KEY, $attributes, Logger::WARNING);
        $this->logger->pushHandler($datadogLogs);

        $this->akashicChain = new AkashicChain($this->env, $this->logger);

        // Initialize HttpClient
        $this->httpClient = new HttpClient($this->logger);

        $this->targetNode = $args["targetNode"] ?? $this->chooseBestACNode();

        if (! isset($args["l2Address"])) {
            $this->setNewOTK();
        } else {
            // Chck if BP if on prod
            if ($this->env === Environment::PRODUCTION) {
                $checkIfBpUrl =
                    $this->akashicUrl
                    . AkashicEndpoints::IS_BP
                    . "?address="
                    . urlencode($args["l2Address"]);
                $isBp         = $this->get($checkIfBpUrl)["data"]["isBp"];
                $isFxBp         = $this->get($checkIfBpUrl)["data"]["isFxBp"];

                if (! $isBp) {
                    throw new AkashicException(AkashicErrorCode::IS_NOT_BP);
                }
                $this->isFxBp = $isFxBp;
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
        }

        $this->logger->info("AkashicPay instance initialised");
    }

    /**
     * Get the OTK (One-Time-Key) object for this instance.
     *
     * @return array if the environment is development. This enables you to
     * easily create an OTK and re-use it in future tests or dev work.
     * @throws AkashicException if the environment is production.
     */
    public function getKeyBackup()
    {
        if ($this->env === "production") {
            throw new AkashicException(AkashicErrorCode::ACCESS_DENIED);
        }

        return [
            "l2Address"  => $this->otk["identity"],
            "privateKey" => $this->otk["key"]["prv"]["pkcs8pem"],
            "raw"        => $this->otk,
        ];
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

        // map TokenSymbol.TETHER to TokenSymbol.USDT
        if (
            $token === TokenSymbol::USDT
            && $network === NetworkSymbol::TRON_SHASTA
        ) {
            $token = TokenSymbol::TETHER;
        }
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
                    "tokenSymbol"      => $token,
                    "initiatedToNonL2" => $initiatedToNonL2,
                    "identifier"       => $recipientId,
                ]
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
     * @param  string $referenceId optional referenceId to identify the order
     * @param  string $requestedCurrency optional requestedCurrency to identify the order
     * @param  string $requestedAmount optional requestedAmount to identify the order
     * @return array
     */
    public function getDepositAddress($network, $identifier, $referenceId = null, $requestedCurrency = null, $requestedAmount = null)
    {
        $response = $this->getByOwnerAndIdentifier(
            [
                "identifier" => $identifier,
                "coinSymbol" => $network,
            ]
        );

        $address = $response["address"] ?? null;
        if ($address) {
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
                $this->createDepositOrder(array_merge($payloadToSign, [
                    "signature" => $this->sign($payloadToSign),
                ]));
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
                . $response["data"]['$responses']
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
            $this->createDepositOrder(array_merge($payloadToSign, [
                "signature" => $this->sign($payloadToSign),
            ]));
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
     * @param  string $requestedCurrency optional requestedCurrency to identify the order
     * @param  string $requestedAmount optional requestedAmount to identify the order
     * @return string
     */
    public function getDepositUrl($identifier, $referenceId = null, $requestedCurrency = null, $requestedAmount = null)
    {
        // Perform asynchronous tasks sequentially
        $keys                = $this->getKeysByOwnerAndIdentifier(['identifier' => $identifier]);
        $supportedCurrencies = $this->getSupportedCurrencies();

        // Process supported currencies
        $supportedCurrencySymbols = array_unique(array_keys($supportedCurrencies));
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
            $this->createDepositOrder(array_merge($payloadToSign, [
                "signature" => $this->sign($payloadToSign),
            ]));
        }

        // Construct the deposit URL
        return "{$this->akashicPayUrl}/sdk/deposit?identity={$this->otk['identity']}&identifier={$identifier}" .
        ($referenceId ? "&referenceId={$referenceId}" : "");
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
                "withSigningErrors" => true,
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
        return array_map(
            function ($t) {
                return array_merge(
                    $t,
                    [
                        "tokenSymbol"
                    => $t["tokenSymbol"] === TokenSymbol::TETHER
                        ? TokenSymbol::USDT
                        : $t["tokenSymbol"],
                    ]
                );
            },
            $transactions
        );
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
                    "tokenSymbol"
                    => $bal["tokenSymbol"] === TokenSymbol::TETHER
                        ? TokenSymbol::USDT
                        : $bal["tokenSymbol"],
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

        return array_merge(
            $transaction,
            [
                "tokenSymbol"
                    => $transaction["tokenSymbol"] === TokenSymbol::TETHER
                        ? TokenSymbol::USDT
                        : $transaction["tokenSymbol"],
            ]
        );
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
     * Get key by BP and identifier
     *
     * @return ?array address
     */
    public function getByOwnerAndIdentifier($getByOwnerAndIdentifierParams): ?array
    {
        $queryParameters = array_merge(
            $getByOwnerAndIdentifierParams,
            ["identity" => $this->otk["identity"]]
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
     * Generates a new OTK and assigns it to `this.otk`. The OTK will live and die
     * with the lifetime of this AkashicPay instance.
     *
     * Only for us in development/testing environments.
     */
    private function setNewOTK(): void
    {
        $this->logger->info(
            "Generating new OTK for development environment. Access it via `this->otk()`"
        );
        $otk       = Otk::generateOTK();
        $onboardTx = $this->akashicChain->onboardOtkTransaction($otk);

        $response = $this->post($this->targetNode["node"], $onboardTx);
        $identity = $response["data"]['$streams']["new"][0]["id"] ?? null;

        if ($identity === null) {
            throw new AkashicException(AkashicErrorCode::TEST_NET_OTK_ONBOARDING_FAILED);
        }

        $this->otk = array_merge($otk, ["identity" => "AS" . $identity]);
        $this->logger->debug(
            "New OTK generated and onboarded with identity: "
            . $this->otk["identity"]
        );
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

    private function sign($data)
    {
        try {
            // Convert private key into the correct format
            $pemPrivate = "-----BEGIN EC PRIVATE KEY-----\n" . $this->otk["key"]["prv"]["pkcs8pem"] . "\n-----END EC PRIVATE KEY-----";

            if (str_starts_with($this->otk["key"]["prv"]["pkcs8pem"], '0x')) {
                $keyPair = new KeyPair('secp256k1', $this->otk["key"]["prv"]["pkcs8pem"]);
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
}
