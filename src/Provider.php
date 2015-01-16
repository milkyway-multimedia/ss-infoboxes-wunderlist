<?php namespace Milkyway\SS\InfoBoxes\Wunderlist;
/**
 * Milkyway Multimedia
 * Provide.php
 *
 * @package milkywaymultimedia.com.au
 * @author Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */

use GuzzleHttp\Client;
use GuzzleHttp\Message\ResponseInterface;
use Milkyway\SS\Config;
use \Milkyway\SS\InfoBoxes\Wunderlist\Contracts\Provider as Contract;
use Exception;


class Provider implements \Flushable, Contract {
    protected $endpoint = 'http://api.wunderlist.com';
    protected $client;
    protected $cacheLifetime = 1;

    protected $cache;

    protected $token;
    protected $listId;

    private $tokenChecked = false;

    public function __construct($cache = 1) {
        $this->cacheLifetime = $cache;
    }

    public static function flush() {
        singleton('Milkyway\SS\InfoBoxes\Wunderlist\Provider')->cleanCache();
    }

    public function cleanCache() {
        $this->cache()->clean();
    }

    /**
     * Get a new HTTP client instance.
     * @return \GuzzleHttp\Client
     */
    protected function http()
    {
        if(!$this->client)
            $this->client = new Client($this->getHttpSettings());

        return $this->client;
    }

    protected function getHttpSettings() {
        return [
            'base_url' => $this->endpoint,
        ];
    }

    protected function isError(ResponseInterface $response) {
        if ($response->getStatusCode() < 200 || $response->getStatusCode() > 399) {
            throw new Exception(sprintf('Invalid response received. Please check your credentials and action: %s', $response->getEffectiveUrl()));
        }

        return false;
    }

    protected function verifyIfTokenNeedsRefreshing(ResponseInterface $response, $action, $vars = []) {
        if($response->getStatusCode() != 401)
            return $response;

        if ($this->tokenChecked) {
            throw new Exception(sprintf('Response could not be obtained due to invalid token and response. Please check your credentials and action: %s', $response->getEffectiveUrl()));
        }

        $this->tokenChecked = true;

        if(file_exists($this->tokenLocation()))
            unlink($this->tokenLocation());

        return $this->get($action, $vars);
    }

    protected function cache() {
        if(!$this->cache)
            $this->cache = \SS_Cache::factory('Milkyway_SS_InfoBoxes_Wunderlist_Provider', 'Output', ['lifetime' => $this->cacheLifetime * 60 * 60]);

        return $this->cache;
    }

    protected function getCacheKey(array $vars = []) {
        return preg_replace('/[^a-zA-Z0-9_]/', '', get_class($this) . '_' . urldecode(http_build_query($vars, '', '_')));
    }

    public function get($action, $vars = []) {
        $cacheKey = $this->getCacheKey(array_merge([$action], $vars));

        if(!($body = unserialize($this->cache()->load($cacheKey)))) {
            $vars = [
                'headers' => [
                    'Authorization' => $this->getToken(),
                ],
                'json' => $vars,
            ];

            if(!count($vars['json']))
                unset($vars['json']);

            $response = $this->verifyIfTokenNeedsRefreshing($this->http()->get($action, $vars), $action, (isset($vars['json']) ? $vars['json'] : []));

            if($response && !$this->isError($response)) {
                $body = $this->parseResponse($response);

                if(!$this->isValid($body))
                    throw new Exception(sprintf('Data not received from %s. Please check your credentials.', $this->endpoint));

                $this->cache()->save(serialize($body), $cacheKey);

                return $body;
            }
        }

        return $body;
    }

    protected function parseResponse(ResponseInterface $response) {
        return $response->json();
    }

    protected function isValid($body) {
        return $body ? true : false;
    }

    protected function getToken() {
        if($this->token && !$this->tokenChecked)
            return $this->token;

        $token = $this->tokenChecked ? null : Config::get('wunderlist.token');

        if(!$token && file_exists($this->tokenLocation()))
            $token = file_get_contents($this->tokenLocation());

        if(!$token && ($email = Config::get('wunderlist.email')) && ($password = Config::get('wunderlist.password'))) {
            $response = $this->http()->post(
                'login',
                [
                    'json' => [
                        'email' => $email,
                        'password' => $password,
                    ],
                ]
            );

            if($response && !$this->isError($response)) {
                $body = $this->parseResponse($response);

                if(!$this->isValid($body))
                    throw new Exception(sprintf('Data not received from %s. Please check your credentials.', $this->endpoint));

                if(!isset($body['token']))
                    throw new Exception(sprintf('No token received from %s. Please check your credentials.', $this->endpoint));

                file_put_contents($this->tokenLocation(), $body['token']);
                $token = $body['token'];
            }
        }

        if(!$token)
            throw new Exception('No token could be retrieved. Please check your credentials.');

        $this->token = $token;

        return $token;
    }

    private function tokenLocation() {
        return TEMP_FOLDER . DIRECTORY_SEPARATOR . '.' . get_class($this) . '_token';
    }
} 