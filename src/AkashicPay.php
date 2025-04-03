<?php

namespace Akashic;

require 'vendor/autoload.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Akashic\OTK\Otk;
use Akashic\HttpClient;

class AkashicPay
{
    private $otk;
    private $url;
    private $env;
    private $httpClient;
    private $logger;
    private $targetNode;

    const ACPrivateKeyRegex = '/^0x[a-f\d]{64}$/i';

    public function __construct($otk, $url, $env, $args)
    {
        // Logger initialization
        $this->logger = new Logger('AkashicPay');
        $this->logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));

        // Assign constructor parameters to class properties
        $this->otk = $otk;
        $this->url = $url;
        $this->env = $env;
        $this->httpClient = new HttpClient();

        // Initialization logic
        $this->logger->info('Initialising AkashicPay instance');

        $this->init($args);
    }

    public function init($args)
    {
        $this->logger->info('Initialising AkashicPay instance');
        if (!isset($args['l2Address'])) {
            $this->setNewOTK();
        } elseif (isset($args['privateKey']) && $args['privateKey']) {
            $this->setOtkFromKeyPair($args['privateKey'], $args['l2Address']);
        } elseif (isset($args['recoveryPhrase']) && $args['recoveryPhrase']) {
            $this->setOtkFromRecoveryPhrase($args['recoveryPhrase'], $args['l2Address']);
        } else {
            throw new \Exception('Incorrect Private Key Format');
        }

        if (!$this->targetNode) {
            $this->targetNode = $this->chooseBestACNode();
        }

        $this->logger->info('AkashicPay instance initialised');
    }

    protected function setOtkFromKeyPair($privateKey, $l2Address)
    {
        // Check if the private key is in the correct format (hexadecimal, 64 characters long)
        if (!preg_match(self::ACPrivateKeyRegex, $privateKey)) {
            throw new \Exception('Incorrect Private Key Format');
        }

        $this->otk = array_merge(Otk::restoreOtkFromKeypair($privateKey), ['identity' => $l2Address]);
        $this->logger->debug('OTK set from private key');
    }

    protected function setOtkFromRecoveryPhrase($recoveryPhrase, $l2Address)
    {
        $this->otk = array_merge(Otk::restoreOtkFromPhrase($recoveryPhrase), ['identity' => $l2Address]);
        $this->logger->debug('OTK set from recovery phrase');
    }

    protected function setNewOTK()
    {
        $this->otk = Otk::generateOTK();
        $this->logger->debug('New OTK generated');
    }

    protected function chooseBestACNode()
    {
        $this->logger->debug('Best AC node chosen');
        return 'BestACNodeAddress';
    }
}

?>