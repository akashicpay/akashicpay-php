<?php

declare(strict_types=1);

namespace Akashic;

use Akashic\Classes\IBaseTransaction;
use Akashic\Constants\AkashicErrorCode;
use Akashic\Constants\AkashicException;
use Akashic\Constants\Environment;
use Akashic\Constants\EthereumSymbol;
use Akashic\Constants\MainNetContracts;
use Akashic\Constants\NetworkSymbol;
use Akashic\Constants\TestNetContracts;
use Akashic\Constants\TronSymbol;
use Exception;
use Monolog\Logger;
use ReflectionClass;

use function array_filter;
use function gmdate;
use function in_array;
use function is_array;
use function is_string;
use function strpos;
use function time;

class AkashicChain
{
    public const NITR0GEN_NATIVE_COIN = "#native";
    private $contracts;
    private int $dbIndex;
    private Logger $logger;

    public function __construct(
        string $env,
        Logger $logger
    ) {
        $this->contracts =
            $env === Environment::PRODUCTION
                ? new MainNetContracts()
                : new TestNetContracts();
        $this->dbIndex   = $env === Environment::PRODUCTION ? 0 : 15;
        $this->logger    = $logger;
    }

    /**
     * Check for errors in the AkashicChain response
     *
     * @param  array $response
     * @throws Exception
     */
    public function checkForAkashicChainError(array $response): ?string
    {
        $commit = $response['$summary']["commit"] ?? null;
        if ($commit) {
            return null;
        }
        $errorMessage = $this->convertChainErrorToAkashicError(
            $response['$summary']["errors"][0] ?? "Unknown error"
        );

        // Check if the error message matches a valid AkashicErrorCode
        if (in_array($errorMessage, (new ReflectionClass(AkashicErrorCode::class))->getConstants(), true)) {
            return $errorMessage;
        }

        // Throw an unknown error with additional details
        throw new AkashicException(
            AkashicErrorCode::UNKNOWN_ERROR,
            "AkashicChain Failure: {$errorMessage}"
        );
    }

    /**
     * Converts chain error to Akashic error based on predefined conditions.
     */
    private function convertChainErrorToAkashicError(string $error): string
    {
        if ($this->isChainErrorSavingsExceeded($error)) {
            return AkashicErrorCode::SAVINGS_EXCEEDED;
        }

        if (strpos($error, "Stream(s) not found") !== false) {
            return AkashicErrorCode::L2_ADDRESS_NOT_FOUND;
        }

        return $error;
    }

    /**
     * Checks if the error is related to savings exceeded.
     */
    private function isChainErrorSavingsExceeded(string $error): bool
    {
        $messages = [
            "balance is not sufficient",
            "Couldn't parse integer",
            "Part-Balance to low",
        ];

        foreach ($messages as $msg) {
            if (strpos($error, $msg) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Create a key creation transaction
     *
     * @param  string $coinSymbol (i.e. a NetworkSymbol)
     * @param  array $otk
     * @return IBaseTransaction
     */
    public function keyCreateTransaction(string $coinSymbol, array $otk)
    {
        $txBody = [
            '$tx'   => [
                '$namespace' => $this->contracts::CONTRACT_NAMESPACE,
                '$contract'  => $this->contracts::CREATE,
                '$i'         => [
                    "owner" => [
                        '$stream'  => $otk["identity"],
                        "symbol"   => $this->getACSymbol($coinSymbol),
                        "network"  => $this->getACNetwork($coinSymbol),
                        "business" => true,
                    ],
                ],
                "_dbIndex"   => $this->dbIndex,
            ],
            '$sigs' => [],
        ];

        // Sign Transaction
        return $this->signTransaction($txBody, $otk);
    }

    /**
     * Create a differential consensus transaction
     *
     * @return IBaseTransaction
     */
    public function differentialConsensusTransaction(
        array $otk,
        array $key,
        string $identifier
    ) {
        $txBody = [
            '$tx'   => [
                '$namespace' => $this->contracts::CONTRACT_NAMESPACE,
                '$contract'  => $this->contracts::DIFF_CONSENSUS,
                '$i'         => [
                    "owner" => [
                        '$stream' => $otk["identity"],
                        "address" => $key["address"],
                        "hashes"  => $key["hashes"],
                    ],
                ],
                '$o'         => [
                    "key" => [
                        '$stream' => $key["id"],
                    ],
                ],
                "_dbIndex"   => $this->dbIndex,
                "metadata"   => ["identifier" => $identifier],
            ],
            '$sigs' => [],
        ];

        // Sign Transaction
        return $this->signTransaction($txBody, $otk);
    }

    /**
     * Create an onboard OTK transaction
     *
     * @param  array $otk
     * @return array the shape of an {@link IBaseTransaction}
     */
    public function onboardOtkTransaction(array $otk): array
    {
        $txBody = [
            '$tx'       => [
                '$namespace' => $this->contracts::CONTRACT_NAMESPACE,
                '$contract'  => $this->contracts::ONBOARD,
                '$i'         => [
                    "otk" => [
                        "publicKey" => $otk["key"]["pub"]["pkcs8pem"],
                        "type"      => $otk["type"],
                    ],
                ],
                "_dbIndex"   => $this->dbIndex,
            ],
            '$sigs'     => [],
            '$selfsign' => true,
        ];

        // Sign Transaction
        return $this->signTransaction($txBody, $otk);
    }

    public function l2Transaction(array $params): array
    {
        $otk              = $params["otk"];
        $coinSymbol       = $params["coinSymbol"];
        $amount           = $params["amount"];
        $toAddress        = $params["toAddress"];
        $tokenSymbol      = $params["tokenSymbol"] ?? self::NITR0GEN_NATIVE_COIN;
        $initiatedToNonL2 = $params["initiatedToNonL2"] ?? null;

        $txBody = [
            '$tx'   => [
                '$namespace' => $this->contracts::CONTRACT_NAMESPACE,
                '$contract'  => $this->contracts::CRYPTO_TRANSFER,
                '$entry'     => "transfer",
                '$i'         => [
                    "owner" => [
                        '$stream' => $otk["identity"],
                        "network" => $coinSymbol,
                        "token"   => $tokenSymbol,
                        "amount"  => $amount,
                    ],
                ],
                '$o'         => [
                    "to" => ['$stream' => $toAddress],
                ],
                "_dbIndex"   => $this->dbIndex,
                "metadata"   => $initiatedToNonL2
                    ? [
                        "initiatedToNonL2" => $initiatedToNonL2,
                        "identifier"       => $params["identifier"],
                    ]
                    : ["identifier" => $params["identifier"]],
            ],
            '$sigs' => [],
        ];

        // Sign Transaction
        return $this->signTransaction($txBody, $otk);
    }

    /**
     * Builds an L1 payout transaction that still needs to be signed and can then
     * be sent directly to AC.
     * For use only when the backend is unavailable.
     *
     * @api
     * */
    public function buildPayoutTransaction(array $params): array
    {
        $otk          = $params["otk"];
        $keyLedgerId  = $params["keyLedgerId"];
        $coinSymbol   = $params["coinSymbol"];
        $amount       = $params["amount"];
        $toAddress    = $params["toAddress"];
        $tokenSymbol  = $params["tokenSymbol"] ?? self::NITR0GEN_NATIVE_COIN;
        $identifier   = $params["identifier"];
        $feesEstimate = $params["feesEstimate"];
        $ethGasPrice  = $params["ethGasPrice"];

        $contractAddress =
            array_filter(
                L1Network::NETWORK_DICTIONARY[$coinSymbol]["tokens"],
                function ($t) use ($tokenSymbol) {
                    return $t["symbol"] === $tokenSymbol;
                }
            )[0]["contract"] ?? null;

        $txBody = [
            '$tx'   => [
                '$namespace' => $this->contracts::CONTRACT_NAMESPACE,
                '$contract'  => $this->contracts::CRYPTO_TRANSFER,
                '$entry'     => "sign",
                '$i'         => [
                    "owner" => [
                        '$stream'         => $otk["identity"],
                        "network"         => $coinSymbol,
                        "token"           => $tokenSymbol,
                        "amount"          => $amount,
                        "to"              => $toAddress,
                        "contractAddress" => $contractAddress,
                        "delegated"       => true,
                        "gas"             => $ethGasPrice,
                    ],
                ],
                '$r'         => ["wallet" => $keyLedgerId],
                "_dbIndex"   => $this->dbIndex,
                "metadata"   => [
                    "identifier"   => $identifier,
                    "feesEstimate" => $feesEstimate,
                ],
            ],
            '$sigs' => [],
        ];

        // Sign Transaction
        return $this->signTransaction($txBody, $otk);
    }

    /**
     * Get the AC Symbol for the given coin symbol
     *
     * @param  string $coinSymbol (i.e. a NetworkSymbol)
     */
    private function getACSymbol($coinSymbol): string
    {
        if (in_array($coinSymbol, TronSymbol::VALUES, true)) {
            return "trx";
        }

        if (in_array($coinSymbol, EthereumSymbol::VALUES, true)) {
            return "eth";
        }
        return $coinSymbol;
    }

    /**
     * Get the AC Network for the given coin symbol
     *
     * @param  NetworkSymbol $coinSymbol
     */
    private function getACNetwork(string $coinSymbol): string
    {
        switch ($coinSymbol) {
            case NetworkSymbol::ETHEREUM_MAINNET:
                return "ETH";
            case NetworkSymbol::ETHEREUM_SEPOLIA:
                return "SEP";
            case NetworkSymbol::TRON:
                return "trx";
            case NetworkSymbol::TRON_SHASTA:
                return "shasta";
            default:
                return $coinSymbol;
        }
    }

    public function signTransaction($txBody, array $otk): array
    {
        try {
            $txBody = $this->addExpireToTxBody($txBody);

            $key = new KeyPair($otk["type"], $otk["key"]["prv"]["pkcs8pem"]);
            // Check if $txBody is a string
            if (is_string($txBody)) {
                // Sign the string
                return $key->sign($txBody);
            }

            if (is_array($txBody) && isset($txBody['$tx'], $txBody['$sigs'])) {
                // Sign the transaction in the array
                $identifier = $otk["identity"] ?? $otk["name"];

                $txBody['$sigs'][$identifier] = $key->sign($txBody['$tx']);

                return $txBody;
            }

            throw new Exception("Invalid transaction body provided");
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
            throw new Exception(
                "Error signing transaction: " . $e->getMessage()
            );
        }
    }

    /**
     * Adds expiry time to the transaction body.
     *
     * @param array $txBody
     * @return array
     */
    private function addExpireToTxBody(array &$txBody): array
    {
        // Set expiry to 1 minute from now
        $txBody['$tx']['$expire'] = gmdate("Y-m-d\TH:i:s\Z", time() + 60);

        return $txBody;
    }
}
