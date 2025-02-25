<?php

declare(strict_types=1);

namespace Akashic\Utils;

use Exception;
use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Handler\Curl\Util;
use Monolog\Logger;

use function curl_init;
use function curl_setopt;
use function extension_loaded;
use function json_decode;

use const CURLOPT_HTTPHEADER;
use const CURLOPT_POST;
use const CURLOPT_POSTFIELDS;
use const CURLOPT_RETURNTRANSFER;
use const CURLOPT_SSL_VERIFYPEER;
use const CURLOPT_URL;

/**
 * Sends logs to Datadog Logs using Curl integrations
 *
 * You'll need a Datzdog account to use this handler.
 *
 * @see https://docs.datadoghq.com/logs/ Datadog Logs Documentation
 */
class DatadogHandler extends AbstractProcessingHandler
{
    /**
     * Datadog Api Key access
     *
     * @var string
     */
    protected const DATADOG_LOG_HOST = 'https://http-intake.logs.datadoghq.com';

    /**
     * Datadog Api Key access
     * 
     * @var string
     */
    private $apiKey;

    /**
     * Datadog's optionals attributes
     *
     * @var array
     */
    private $attributes;

    /**
     * SDK Version
     *
     * @var array
     */
    private $apVersion;

    /**
     * @param string $apiKey Datadog Api Key access
     * @param array $attributes Some options fore Datadog Logs
     * @param int $level The minimum logging level at which this handler will be triggered
     * @param bool $bubble Whether the messages that are handled can bubble up the stack or not
     * @throws Exception
     */
    public function __construct(
        string $apiKey,
        array $attributes = [],
        int $level = Logger::DEBUG,
        bool $bubble = true
    ) {
        if (! extension_loaded('curl')) {
            throw new Exception('The curl extension is needed to use the DatadogHandler');
        }

        parent::__construct($level, $bubble);

        $this->apiKey     = $this->getApiKey($apiKey);
        $this->attributes = $attributes;
        $config          = json_decode(file_get_contents(__DIR__ . '/../config.json'), true);
        $this->apVersion = $config['version'];
    }

    /**
     * Handles a log record
     */
    protected function write(array $record): void
    {
        $this->send($record['formatted']);
    }

    /**
     * Send request to @link https://http-intake.logs.datadoghq.com on send action.
     */
    protected function send(string $record)
    {
        $headers = ['Content-Type:application/json'];

        $source   = $this->getSource();
        $hostname = $this->getHostname();
        $service  = $this->getService($record);
        $tags     = $this->getTags();
        $identity = $this->getIdentity();

        $url  = self::DATADOG_LOG_HOST . '/api/v2/logs';
        $url .= '?dd-api-key=' . $this->apiKey . '&ddsource=' . $source . '&service=' . $service . '&hostname=' . $hostname . '&ddtags=' . $tags . '&identity='. $identity . '&version=' . $this->apVersion;

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $record);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        Util::execute($ch);
    }

    /**
     * Get Datadog Api Key from $attributes params.
     *
     * @throws Exception
     */
    protected function getApiKey(string $apiKey): string
    {
        if ($apiKey) {
            return $apiKey;
        } else {
            throw new Exception('The Datadog Api Key is required');
        }
    }

    /**
     * Get Datadog Source from $attributes params.
     */
    protected function getSource(): string
    {
        return ! empty($this->attributes['source']) ? $this->attributes['source'] : 'php';
    }

    /**
     * Get Datadog Service from $attributes params.
     *
     * @param string $record
     */
    protected function getService($record): string
    {
        $channel = json_decode($record, true);

        return ! empty($this->attributes['service']) ? $this->attributes['service'] : $channel['channel'];
    }

    /**
     * Get Datadog Hostname from $attributes params.
     */
    protected function getHostname(): string
    {
        return ! empty($this->attributes['hostname']) ? $this->attributes['hostname'] : $_SERVER['SERVER_NAME'];
    }

    /**
     * Get Datadog Tags from $attributes params.
     */
    protected function getTags(): string
    {
        return ! empty($this->attributes['tags']) ? $this->attributes['tags'] : '';
    }

    /**
     * Get BP identity from $attributes params.
     */
    protected function getIdentity(): string
    {
        return ! empty($this->attributes['identity']) ? $this->attributes['identity'] : '';
    }

    /**
     * Returns the default formatter to use with this handler
     */
    protected function getDefaultFormatter(): FormatterInterface
    {
        return new JsonFormatter();
    }
}
