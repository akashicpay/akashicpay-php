<?php

namespace Akashic;

require 'vendor/autoload.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Akashic\OTK\Otk;
use Akashic\HttpClient;
use Akashic\Constants\Environment;
use Akashic\Constants\AkashicError;
use Akashic\Constants\ACDevNode;
use Akashic\Constants\ACNode;
use Akashic\AkashicChain;

class AkashicPay
{
    public const AC_PRIVATE_KEY_REGEX = '/^0x[a-f\d]{64}$/';
    private $otk;
    private $targetNode;
    private $akashicUrl;
    private $logger;
    private $env;
    private $httpClient;

    public function __construct($args)
    {
        $this->env = $args['environment'] ?? Environment::PRODUCTION;

        // Logger initialization
        $this->logger = new Logger('AkashicPay');
        $this->logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));

        // Initialize HttpClient
        $this->httpClient = new HttpClient();

        $this->targetNode = $args['targetNode'] ?? null;
    }

    public function init($args)
    {
        $this->logger->info('Initialising AkashicPay instance');
        if (!$this->targetNode) {
            $this->targetNode = $this->chooseBestACNode();
        }

        if (!isset($args['l2Address'])) {
            $this->setNewOTK();
        } elseif (isset($args['privateKey']) && $args['privateKey']) {
            $this->setOtkFromKeyPair($args['privateKey'], $args['l2Address']);
        } elseif (isset($args['recoveryPhrase']) && $args['recoveryPhrase']) {
            $this->setOtkFromRecoveryPhrase($args['recoveryPhrase'], $args['l2Address']);
        } else {
            throw new \Exception(AkashicError::INCORRECT_PRIVATE_KEY_FORMAT);
        }

        $this->logger->info('AkashicPay instance initialised');
    }

    private function chooseBestACNode(): string
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
        return $this->env === Environment::PRODUCTION ? ACNode::SINGAPORE_DAI : ACDevNode::SINGAPORE_1;
    }

    private function setNewOTK(): void
    {
        $this->logger->info('Generating new OTK for development environment. Access it via `this->otk()`');
        $otk = Otk::generateOTK();
        $onboardTx = AkashicChain::onboardOtkTransaction($otk);

        $response = $this->post($this->targetNode . ':5260', $onboardTx);
        $identity = $response['data']['$streams']['new'][0]['id'] ?? null;

        if (is_null($identity)) {
            throw new \Exception(AkashicError::TEST_NET_OTK_ONBOARDING_FAILED);
        }

        $this->otk = array_merge($otk, ['identity' => 'AS' . $identity]);
        $this->logger->debug('New OTK generated and onboarded with identity: ' . $this->otk['identity']);
    }

    private function setOtkFromKeyPair(string $privateKey, string $l2Address): void
    {
        if (!preg_match(self::AC_PRIVATE_KEY_REGEX, $privateKey)) {
            throw new \Exception(AkashicError::INCORRECT_PRIVATE_KEY_FORMAT);
        }

        $this->otk = array_merge(Otk::restoreOtkFromKeypair($privateKey), ['identity' => $l2Address]);
        $this->logger->debug('OTK set from private key');
    }

    private function setOtkFromRecoveryPhrase(string $recoveryPhrase, string $l2Address): void
    {
        $this->otk = array_merge(Otk::restoreOtkFromPhrase($recoveryPhrase), ['identity' => $l2Address]);
        $this->logger->debug('OTK set from recovery phrase');
    }

    private function post(string $url, $payload)
    {
        $this->logger->info('POSTing to AC url');
        return $this->httpClient->post($url, $payload);
    }
}

?>