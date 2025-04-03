<?php

namespace Akashic;

use Akashic\Constants\AkashicChainContracts;
use Akashic\Constants\TestNetContracts;
use Akashic\Constants\NetworkSymbol;
use Akashic\Constants\EthereumSymbol;
use Akashic\Constants\TronSymbol;
use Akashic\KeyPair;
use Akashic\Classes\ActiveLedgerResponse;
use Akashic\Classes\IBaseTransaction;
use Akashic\L1Network;

class AkashicChain
{
    public const L2_REGEX = '/^AS[A-Fa-f\d]{64}$/';
    public const NITR0GEN_NATIVE_COIN = "#native";

    /**
     * Check for errors in the AkashicChain response
     *
     * @param ActiveLedgerResponse $response
     * @throws \Exception
     */
    public static function checkForAkashicChainError(
        ActiveLedgerResponse $response
    ): void {
        if ($response->summary->commit) {
            return;
        }
        throw new \Exception(
            "AkashicChain Failure: " .
                ($response->summary->errors[0] ?? "Unknown error")
        );
    }

    /**
     * Create a key creation transaction
     *
     * @param NetworkSymbol $coinSymbol
     * @param string $otkIdentity
     * @param string $identifier
     * @return IBaseTransaction
     */
    public static function keyCreateTransaction(
        NetworkSymbol $coinSymbol,
        string $otkIdentity
    ) {
        return [
            '$tx' => [
                '$namespace' => AkashicChainContracts::NAMESPACE,
                '$contract' => AkashicChainContracts::CREATE,
                '$i' => [
                    "owner" => [
                        '$stream' => $otkIdentity,
                        "symbol" => self::getACSymbol($coinSymbol),
                        "network" => self::getACNetwork($coinSymbol),
                        "business" => true,
                    ],
                ],
            ],
            '$sigs' => [],
        ];
    }

    /**
     * Create a differential consensus transaction
     *
     * @param $otk
     * @param $key
     * @return IBaseTransaction
     */
    public static function differentialConsensusTransaction(
        $otk,
        $key,
        $identifier
    ) {
        return [
            '$tx' => [
                '$namespace' => AkashicChainContracts::NAMESPACE,
                '$contract' => AkashicChainContracts::DIFF_CONSENSUS,
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
                "metadata" => ["identifier" => $identifier],
            ],
            '$sigs' => [],
        ];
    }

    /**
     * Create an onboard OTK transaction
     *
     * @param $otk
     * @return IBaseTransaction
     */
    public static function onboardOtkTransaction($otk)
    {
        $txBody = [
            '$tx' => [
                '$namespace' => TestNetContracts::NAMESPACE,
                '$contract' => TestNetContracts::ONBOARD,
                '$i' => [
                    "otk" => [
                        "publicKey" => $otk["key"]["pub"]["pkcs8pem"],
                        "type" => $otk["type"],
                    ],
                ],
            ],
            '$sigs' => [],
            '$selfsign' => true,
        ];

        // Sign Transaction
        return self::signTransaction($txBody, $otk);
    }

    public static function l2Transaction(array $params): IBaseTransaction
    {
        $otk = $params["otk"];
        $coinSymbol = $params["coinSymbol"];
        $amount = $params["amount"];
        $toAddress = $params["toAddress"];
        $tokenSymbol = $params["tokenSymbol"] ?? self::NITR0GEN_NATIVE_COIN;
        $initiatedToNonL2 = $params["initiatedToNonL2"] ?? null;

        $txBody = [
            '$tx' => [
                '$namespace' => TestNetContracts::Namespace,
                '$contract' => TestNetContracts::CryptoTransfer,
                '$entry' => "transfer",
                '$i' => [
                    "owner" => [
                        '$stream' => $otk->identity,
                        "network" => $coinSymbol,
                        "token" => $tokenSymbol,
                        "amount" => $amount,
                    ],
                ],
                '$o' => [
                    "to" => ['$stream' => $toAddress],
                ],
                "metadata" => $initiatedToNonL2
                    ? ["initiatedToNonL2" => $initiatedToNonL2]
                    : [],
            ],
            '$sigs' => [],
        ];

        // Sign Transaction
        return self::signTransaction($txBody, $otk);
    }

    public static function l2ToL1SignTransaction(
        array $params
    ): IBaseTransaction {
        $otk = $params["otk"];
        $keyLedgerId = $params["keyLedgerId"];
        $coinSymbol = $params["coinSymbol"];
        $amount = $params["amount"];
        $toAddress = $params["toAddress"];
        $tokenSymbol = $params["tokenSymbol"] ?? self::NITR0GEN_NATIVE_COIN;

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
                '$namespace' => TestNetContracts::Namespace,
                '$contract' => TestNetContracts::CryptoTransfer,
                '$entry' => "sign",
                '$i' => [
                    "owner" => [
                        '$stream' => $otk->identity,
                        "network" => $coinSymbol,
                        "token" => $tokenSymbol,
                        "amount" => $amount,
                        "toAddress" => $toAddress,
                        "contractAddress" => $contractAddress,
                    ],
                ],
                '$o' => $o,
            ],
            '$sigs' => [],
        ];

        // Sign Transaction
        return self::signTransaction($txBody, $otk);
    }

    /**
     * Get the AC Symbol for the given coin symbol
     *
     * @param NetworkSymbol $coinSymbol
     * @return string
     */
    private static function getACSymbol(NetworkSymbol $coinSymbol): string
    {
        if (in_array($coinSymbol, TronSymbol::VALUES, true)) {
            return "trx";
        } elseif (in_array($coinSymbol, EthereumSymbol::VALUES, true)) {
            return "eth";
        }
        return $coinSymbol;
    }

    /**
     * Get the AC Network for the given coin symbol
     *
     * @param NetworkSymbol $coinSymbol
     * @return string
     */
    private function getACNetwork(string $coinSymbol): string
    {
        switch ($coinSymbol) {
            case NetworkSymbol::Ethereum_Mainnet:
                return "ETH";
            case NetworkSymbol::Ethereum_Sepolia:
                return "SEP";
            case NetworkSymbol::Tron:
                return "trx";
            case NetworkSymbol::Tron_Shasta:
                return "shasta";
            default:
                return $coinSymbol;
        }
    }

    public static function signTransaction($txBody, $otk): array
    {
        try {
            $key = new KeyPair($otk["type"], $otk["key"]["prv"]["pkcs8pem"]);
            // Check if $txBody is a string
            if (is_string($txBody)) {
                // Sign the string
                return $key->sign($txBody);
            } elseif (
                is_array($txBody) &&
                isset($txBody['$tx']) &&
                isset($txBody['$sigs'])
            ) {
                // Sign the transaction in the array
                $identifier = $otk["name"] ?? "default";

                $txBody['$sigs'][$identifier] = $key->sign($txBody['$tx']);

                return $txBody;
            } else {
                throw new \Exception("Invalid transaction body provided");
            }
        } catch (\Exception $e) {
            throw new \Exception(
                "Error signing transaction: " . $e->getMessage()
            );
        }
    }
}
