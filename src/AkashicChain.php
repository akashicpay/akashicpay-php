<?php

namespace Akashic;

use Akashic\Constants\AkashicChainContracts;
use Akashic\Constants\TestNetContracts;
use Akashic\Constants\NetworkSymbol;
use Akashic\Constants\EthereumSymbol;
use Akashic\Constants\TronSymbol;
use Akashic\Classes\IBaseTransaction;
use Akashic\Constants\Environment;
use Akashic\Constants\AkashicError;
use Exception;

class AkashicChain
{
    public const NITR0GEN_NATIVE_COIN = "#native";
    private $contracts;
    private $dbIndex;

    public function __construct($env)
    {
        $this->contracts =
            $env === Environment::PRODUCTION
                ? new AkashicChainContracts()
                : new TestNetContracts();
        $this->dbIndex = $env === Environment::PRODUCTION ? 0 : 15;
    }

    /**
     * Check for errors in the AkashicChain response
     *
     * @param  $response
     * @throws Exception
     */
    public function checkForAkashicChainError($response): void
    {
        $commit = $response['$summary']["commit"] ?? null;
        if ($commit) {
            return;
        }
        $errorMessage = $this->convertChainErrorToAkashicError(
            $response['$summary']["errors"][0] ?? "Unknown error"
        );

        throw new Exception("AkashicChain Failure: " . $errorMessage);
    }

    /**
     * Converts chain error to Akashic error based on predefined conditions.
     *
     * @param  string $error
     * @return string
     */
    private function convertChainErrorToAkashicError(string $error): string
    {
        if ($this->isChainErrorSavingsExceeded($error)) {
            return AkashicError::SAVINGS_EXCEEDED;
        }

        if (strpos($error, "Stream(s) not found") !== false) {
            return AkashicError::L2_ADDRESS_NOT_FOUND;
        }

        return $error;
    }

    /**
     * Checks if the error is related to savings exceeded.
     *
     * @param  string $error
     * @return bool
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
     * @param  NetworkSymbol $coinSymbol
     * @param  $otk
     * @return IBaseTransaction
     */
    public function keyCreateTransaction($coinSymbol, $otk)
    {
        $txBody = [
            '$tx' => [
                '$namespace' => $this->contracts::CONTRACT_NAMESPACE,
                '$contract' => $this->contracts::CREATE,
                '$i' => [
                    "owner" => [
                        '$stream' => $otk["identity"],
                        "symbol" => $this->getACSymbol($coinSymbol),
                        "network" => $this->getACNetwork($coinSymbol),
                        "business" => true,
                    ],
                ],
                "_dbIndex" => $this->dbIndex,
            ],
            '$sigs' => [],
        ];

        // Sign Transaction
        return self::signTransaction($txBody, $otk);
    }

    /**
     * Create a differential consensus transaction
     *
     * @param  $otk
     * @param  $key
     * @return IBaseTransaction
     */
    public function differentialConsensusTransaction($otk, $key, $identifier)
    {
        $txBody = [
            '$tx' => [
                '$namespace' => $this->contracts::CONTRACT_NAMESPACE,
                '$contract' => $this->contracts::DIFF_CONSENSUS,
                '$i' => [
                    "owner" => [
                        '$stream' => $otk["identity"],
                        "address" => $key["address"],
                        "hashes" => $key["hashes"],
                    ],
                ],
                '$o' => [
                    "key" => [
                        '$stream' => $key["id"],
                    ],
                ],
                "_dbIndex" => $this->dbIndex,
                "metadata" => ["identifier" => $identifier],
            ],
            '$sigs' => [],
        ];

        // Sign Transaction
        return self::signTransaction($txBody, $otk);
    }

    /**
     * Create an onboard OTK transaction
     *
     * @param  $otk
     * @return IBaseTransaction
     */
    public function onboardOtkTransaction($otk)
    {
        $txBody = [
            '$tx' => [
                '$namespace' => $this->contracts::CONTRACT_NAMESPACE,
                '$contract' => $this->contracts::ONBOARD,
                '$i' => [
                    "otk" => [
                        "publicKey" => $otk["key"]["pub"]["pkcs8pem"],
                        "type" => $otk["type"],
                    ],
                ],
                "_dbIndex" => $this->dbIndex,
            ],
            '$sigs' => [],
            '$selfsign' => true,
        ];

        // Sign Transaction
        return self::signTransaction($txBody, $otk);
    }

    public function l2Transaction(array $params)
    {
        $otk = $params["otk"];
        $coinSymbol = $params["coinSymbol"];
        $amount = $params["amount"];
        $toAddress = $params["toAddress"];
        $tokenSymbol = $params["tokenSymbol"] ?? self::NITR0GEN_NATIVE_COIN;
        $initiatedToNonL2 = $params["initiatedToNonL2"] ?? null;

        $txBody = [
            '$tx' => [
                '$namespace' => $this->contracts::CONTRACT_NAMESPACE,
                '$contract' => $this->contracts::CRYPTO_TRANSFER,
                '$entry' => "transfer",
                '$i' => [
                    "owner" => [
                        '$stream' => $otk["identity"],
                        "network" => $coinSymbol,
                        "token" => $tokenSymbol,
                        "amount" => $amount,
                    ],
                ],
                '$o' => [
                    "to" => ['$stream' => $toAddress],
                ],
                "_dbIndex" => $this->dbIndex,
                "metadata" => $initiatedToNonL2
                    ? [
                        "initiatedToNonL2" => $initiatedToNonL2,
                        "identifier" => $params["identifier"],
                    ]
                    : ["identifier" => $params["identifier"]],
            ],
            '$sigs' => [],
        ];

        // Sign Transaction
        return $this->signTransaction($txBody, $otk);
    }

    public function l2ToL1SignTransaction(array $params)
    {
        $otk = $params["otk"];
        $keyLedgerId = $params["keyLedgerId"];
        $coinSymbol = $params["coinSymbol"];
        $amount = $params["amount"];
        $toAddress = $params["toAddress"];
        $tokenSymbol = $params["tokenSymbol"] ?? self::NITR0GEN_NATIVE_COIN;
        $identifier = $params["identifier"];
        $feesEstimate = $params["feesEstimate"];
        $ethGasPrice = $params["ethGasPrice"];

        $o = [
            $keyLedgerId => ["amount" => $amount],
        ];

        $contractAddress =
            array_filter(
                L1Network::NETWORK_DICTIONARY[$coinSymbol]["tokens"],
                function ($t) use ($tokenSymbol) {
                    return $t["symbol"] === $tokenSymbol;
                }
            )[0]["contract"] ?? null;

        $txBody = [
            '$tx' => [
                '$namespace' => $this->contracts::CONTRACT_NAMESPACE,
                '$contract' => $this->contracts::CRYPTO_TRANSFER,
                '$entry' => "sign",
                '$i' => [
                    "owner" => [
                        '$stream' => $otk["identity"],
                        "network" => $coinSymbol,
                        "token" => $tokenSymbol,
                        "amount" => $amount,
                        "to" => $toAddress,
                        "contractAddress" => $contractAddress,
                        "delegated" => true,
                        "gas" => $ethGasPrice,
                    ],
                ],
                '$o' => $o,
                "_dbIndex" => $this->dbIndex,
                "metadata" => [
                    "identifier" => $identifier,
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
     * @param  NetworkSymbol $coinSymbol
     * @return string
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
     * @return string
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

    public function signTransaction($txBody, $otk): array
    {
        try {
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
            throw new Exception(
                "Error signing transaction: " . $e->getMessage()
            );
        }
    }
}
