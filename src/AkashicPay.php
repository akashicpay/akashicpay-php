<?php

namespace Akashic;

require "vendor/autoload.php";

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Akashic\OTK\Otk;
use Akashic\HttpClient;
use Akashic\Constants\Environment;
use Akashic\Constants\AkashicError;
use Akashic\Constants\ACDevNode;
use Akashic\Constants\ACNode;
use Akashic\AkashicChain;
use Akashic\Constants\AkashicBaseUrls;
use Akashic\L1Network;
use Akashic\Constants\TokenSymbol;
use Akashic\Constants\NetworkSymbol;
use Akashic\Utils\Currency;
use Akashic\Utils\Prefix;

class AkashicPay
{
    public const AC_PRIVATE_KEY_REGEX = '/^0x[a-f\d]{64}$/';
    private $otk;
    private $targetNode;
    private $akashicUrl;
    private $logger;
    private $env;
    private $httpClient;
    private $akashicChain;

    public function __construct($args)
    {
        $this->env = $args["environment"] ?? Environment::PRODUCTION;

        $this->akashicChain = new AkashicChain($this->env);

        $this->akashicUrl =
            $this->env === Environment::PRODUCTION
                ? AkashicBaseUrls::BASE_URL
                : AkashicBaseUrls::BASE_URL_DEV;

        // Logger initialization
        $this->logger = new Logger("AkashicPay");
        $this->logger->pushHandler(
            new StreamHandler("php://stdout", Logger::DEBUG)
        );

        // Initialize HttpClient
        $this->httpClient = new HttpClient();

        $this->targetNode = $args["targetNode"] ?? $this->chooseBestACNode();

        if (!isset($args["l2Address"])) {
            $this->setNewOTK();
        } elseif (isset($args["privateKey"]) && $args["privateKey"]) {
            $this->setOtkFromKeyPair($args["privateKey"], $args["l2Address"]);
        } elseif (isset($args["recoveryPhrase"]) && $args["recoveryPhrase"]) {
            $this->setOtkFromRecoveryPhrase(
                $args["recoveryPhrase"],
                $args["l2Address"]
            );
        } else {
            throw new \Exception(AkashicError::INCORRECT_PRIVATE_KEY_FORMAT);
        }

        $this->logger->info("AkashicPay instance initialised");
    }

    /**
     * Get the OTK (One-Time-Key) object for this instance.
     * @return array if the environment is development. This enables you to
     * easily create an OTK and re-use it in future tests or dev work.
     * @throws \Exception if the environment is production.
     */
    public function getKeyBackup()
    {
        if ($this->env === "production") {
            throw new \Exception("Access Denied");
        }

        return [
            "l2Address" => $this->otk["identity"],
            "privateKey" => $this->otk["key"]["prv"]["pkcs8pem"],
            "raw" => $this->otk,
        ];
    }

    /**
     * Send a crypto-transaction
     * @param string $recipientId userID or similar identifier of the user
     * requesting the payout
     * @param string $to L1 or L2 address of receiver
     * @param string $amount
     * @param string $network L1-Network the funds belong to, e.g. `ETH`
     * @param string|null $token Optional. Include if sending token, e.g. `USDT`
     * @return array L2 Transaction hash of the transaction
     */
    public function payout($recipientId, $to, $amount, $network, $token = null)
    {
        $toAddress = $to;
        $initiatedToNonL2 = null;
        $isL2 = false;

        // map TokenSymbol.TETHER to TokenSymbol.USDT
        if (
            $token === TokenSymbol::USDT &&
            $network === NetworkSymbol::TRON_SHASTA
        ) {
            $token = TOkenSymbol::TETHER;
        }
        // convert to backend currency
        $decimalAmount = Currency::convertToDecimals($amount, $network, $token);

        $result = $this->lookForL2Address($to, $network);

        if (
            preg_match(
                L1Network::NETWORK_DICTIONARY[$network]["regex"]["address"],
                $to
            )
        ) {
            // Sending by L1 address
            if ($result["l2Address"]) {
                $toAddress = $result["l2Address"];
                $initiatedToNonL2 = $to;
                $isL2 = true;
            }
        } elseif (preg_match(L2Regex::L2_REGEX, $to)) {
            // Sending L2 by L2 address
            if (!$result["l2Address"]) {
                throw new \Exception(AkashicError::L2AddressNotFound);
            }
            $isL2 = true;
        } else {
            // Sending by alias
            if (!$result["l2Address"]) {
                throw new \Exception(AkashicError::L2AddressNotFound);
            }
            $toAddress = $result["l2Address"];
            $initiatedToNonL2 = $to;
            $isL2 = true;
        }

        if ($isL2) {
            $l2Tx = $this->akashicChain->l2Transaction([
                "otk" => $this->otk,
                "amount" => $decimalAmount,
                "toAddress" => $toAddress,
                "coinSymbol" => $network,
                "tokenSymbol" => $token,
                "initiatedToNonL2" => $initiatedToNonL2,
                "identifier" => $recipientId,
            ]);

            $acResponse = $this->post($this->targetNode["node"], $l2Tx);

            $this->akashicChain->checkForAkashicChainError($acResponse["data"]);

            $this->logger->info(
                "Paid out %d %s to user %s at %s",
                $amount,
                $token,
                $recipientId,
                $to
            );

            return [
                "l2Hash" => Prefix::prefixWithAS($acResponse["data"]['$umid']),
            ];
        } else {
            $payload = [
                "toAddress" => $to,
                "coinSymbol" => $network,
                "amount" => $amount,
                "tokenSymbol" => $token,
                "identity" => $otk["identity"],
            ];

            $response = $this->post(
                $this->akashicUrl . AkashicEndpoints::PREPARE_TX,
                $payload
            );
            $withdrawalKeys = $response["data"]["withdrawalKeys"];

            $feesEstimate = Currency::convertToDecimals(
                $response["data"]["fees"]["feesEstimate"],
                $network
            );

            $lT1x = $this->akashicChain->l2ToL1SignTransaction([
                "otk" => $this->otk,
                "amount" => $decimalAmount,
                "toAddress" => $toAddress,
                "coinSymbol" => $network,
                "tokenSymbol" => $token,
                "keyLedgerId" => $withdrawalKeys[0]["ledgerId"],
                "identifier" => $recipientId,
                "feesEstimate" => $feesEstimate,
            ]);

            $acResponse = $this->post($this->targetNode["node"], $lT1x);

            $this->akashicChain->checkForAkashicChainError($acResponse["data"]);

            $this->logger->info(
                "Paid out %d %s to user %s at %s",
                $amount,
                $token,
                $recipientId,
                $to
            );

            return [
                "l2Hash" => Prefix::prefixWithAS($acResponse["data"]['$umid']),
            ];
        }
    }

    /**
     * Get an L1-address on the specified network for a user to deposit into
     * @param string $network L1-network
     * @param string $identifier userID or similar identifier of the user
     * making the deposit
     * @return array
     */
    public function getDepositAddress($network, $identifier)
    {
        $response = $this->getByOwnerAndIdentifier([
            "identifier" => $identifier,
            "coinSymbol" => $network,
        ]);

        if ($response["address"]) {
            return [
                "address" => $response["address"],
                "identifier" => $identifier,
            ];
        }

        $tx = $this->akashicChain->keyCreateTransaction(
            $network,
            $this->otk["identity"]
        );
        $response = $this->post($this->targetNode["node"], $tx);

        $newKey = $response["data"]['$responses'][0] ?? null;
        if (!$newKey) {
            $this->logger->warn(
                "Key creation on %s failed for identifier %s. Responses: %o",
                $network,
                $identifier,
                $response["data"]['$responses']
            );
            throw new \Exception("Key creation failure");
        }

        $txBody = $this->akashicChain->differentialConsensusTransaction(
            $this->otk,
            $newKey,
            $identifier
        );
        $diffResponse = $this->post($this->targetNode["node"], $txBody)["data"];

        if (
            isset($diffResponse['$responses'][0]) &&
            $diffResponse['$responses'][0] !== "confirmed"
        ) {
            $this->logger->warn(
                "Key creation on %s failed at differential consensus for identifier %s. Unhealthy key: %o",
                $network,
                $identifier,
                $newKey
            );
            throw new \Exception("Unhealthy key");
        }

        return [
            "address" => $newKey["address"],
            "identifier" => $identifier,
        ];
    }

    /**
     * Check which L2-address an alias or L1-address belongs to. Or call with an
     * L2-address to verify it exists
     * @param string $aliasOrL1OrL2Address
     * @param string|null $network
     * @return array
     */
    public function lookForL2Address($aliasOrL1OrL2Address, $network = null)
    {
        $url =
            $this->akashicUrl .
            AkashicEndpoint::L2_LOOKUP .
            urlencode($aliasOrL1OrL2Address);
        if ($network) {
            $url .= "&coinSymbol=" . urlencode($network);
        }
        return $this->get($url)["data"];
    }

    /**
     * Get all or a subset of transactions. Optionally paginated with `page` and `limit`.
     * Optionally parameters: `layer`, `status`, `startDate`, `endDate`, `hideSmallTransactions`.
     * `hideSmallTransactions` excludes values below 1 USD
     * @param array $getTransactionParams
     * @return array
     */
    public function getTransfers(array $getTransactionParams)
    {
        $queryParameters = array_merge($getTransactionParams, [
            "identity" => $this->otk["identity"],
            "withSigningErrors" => true,
        ]);
        $query = http_build_query($queryParameters);
        $transactions = $this->get(
            $this->akashicUrl .
                AkashicEndpoint::OWNER_TRANSACTION .
                "?" .
                $query
        )["data"]["transactions"];
        return array_map(function ($t) {
            return array_merge($t, [
                "tokenSymbol" =>
                    $t["tokenSymbol"] === TokenSymbol::TETHER
                        ? TokenSymbol::USDT
                        : $t["tokenSymbol"],
            ]);
        }, $transactions);
    }

    /**
     * Get total balances, divided by Network and Token.
     * @return array
     */
    public function getBalance()
    {
        $response = $this->get(
            $this->akashicUrl .
                AkashicEndpoint::OWNER_BALANCE .
                "?address=" .
                $this->otk["identity"]
        )["data"];
        return array_map(function ($bal) {
            return [
                "networkSymbol" => $bal["coinSymbol"],
                "tokenSymbol" =>
                    $bal["tokenSymbol"] === TokenSymbol::TETHER
                        ? TokenSymbol::USDT
                        : $bal["tokenSymbol"],
                "balance" => $bal["balance"],
            ];
        }, $response["totalBalances"]);
    }

    /**
     * Get details of an individual transaction. Returns null if no
     * transaction found for the queried hash
     * @param string $l2TxHash l2Hash of transaction
     * @return array|null
     */
    public function getTransactionDetails($l2TxHash)
    {
        $response = $this->get(
            $this->akashicUrl .
                AkashicEndpoint::TRANSACTIONS_DETAILS .
                "?l2Hash=" .
                urlencode($l2TxHash)
        );
        $transaction = $response["data"]["transaction"];
        return array_merge($transaction, [
            "tokenSymbol" =>
                $transaction["tokenSymbol"] === TokenSymbol::TETHER
                    ? TokenSymbol::USDT
                    : $transaction["tokenSymbol"],
        ]) ?? null;
    }

    /**
     * Get key by BP and identifier
     * @return address
     */
    public function getByOwnerAndIdentifier($getByOwnerAndIdentifierParams)
    {
        $queryParameters = array_merge($getByOwnerAndIdentifierParams, [
            "identity" => $this->otk["identity"],
        ]);
        $query = http_build_query($queryParameters);
        $transactions = $this->get(
            $this->akashicUrl .
                AkashicEndpoint::IDENTIFIER_LOOKUP .
                "?" .
                $query
        )["data"];
    }

    /**
     * Finds an AkashicChain node to target for requests. The SDK will attempt to
     * find the fastest node for you.
     *
     * @Returns The URL of an AC node on the network matching your environment
     * (production or development)
     */
    private function chooseBestACNode()
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
            ? ACNode::SINGAPORE_DAI
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
        $otk = Otk::generateOTK();
        $onboardTx = $this->akashicChain->onboardOtkTransaction($otk);

        $response = $this->post($this->targetNode["node"], $onboardTx);
        $identity = $response["data"]['$streams']["new"][0]["id"] ?? null;

        if (is_null($identity)) {
            throw new \Exception(AkashicError::TEST_NET_OTK_ONBOARDING_FAILED);
        }

        $this->otk = array_merge($otk, ["identity" => "AS" . $identity]);
        $this->logger->debug(
            "New OTK generated and onboarded with identity: " .
                $this->otk["identity"]
        );
    }

    /**
     * Sets your OTK to sign transactions on AkashicChain (AC)
     *
     * @param string privateKey private key from Akashic Link.
     * @param string l2Address L2-address of your Akashic account
     */
    private function setOtkFromKeyPair(
        string $privateKey,
        string $l2Address
    ): void {
        if (!preg_match(self::AC_PRIVATE_KEY_REGEX, $privateKey)) {
            throw new \Exception(AkashicError::INCORRECT_PRIVATE_KEY_FORMAT);
        }

        $this->otk = array_merge(Otk::restoreOtkFromKeypair($privateKey), [
            "identity" => $l2Address,
        ]);
        $this->logger->debug("OTK set from private key");
    }

    /**
     * Sets your OTK to sign transactions on AkashicChain (AC)
     *
     * @param string recoveryPhrase the recovery phrase generated when you
     * created your Akashic Link account.
     * @param string l2Address L2-address of your Akashic account
     */
    private function setOtkFromRecoveryPhrase(
        string $recoveryPhrase,
        string $l2Address
    ): void {
        $this->otk = array_merge(Otk::restoreOtkFromPhrase($recoveryPhrase), [
            "identity" => $l2Address,
        ]);
        $this->logger->debug("OTK set from recovery phrase");
    }

    private function post(string $url, $payload)
    {
        $this->logger->info("POSTing to AC url");
        return $this->httpClient->post($url, $payload);
    }

    private function get(string $url)
    {
        return $this->httpClient->get($url);
    }

    private function signTransaction($tx)
    {
        return $this->akashicChain->signTransaction($tx, $this->otk);
    }
}
