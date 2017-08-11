<?php

namespace RetsRabbit;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use RetsRabbit\Bridges\iCmsBridge;

class ApiService
{
    /**
     * Handle to the current CMS
     *
     * @var mixed
     */
    private $cmsBridge = null;

    /**
     * The default base API point.
     *
     * @var string
     */
    private $baseApiEndpoint = 'https://api.retsrabbit.com';

    /**
     * Default api version endpoint.
     *
     * @var string
     */
    private $apiVersionEndpoint = 'api/v2';

    /**
     * Api token fetching endpoint.
     *
     * @var string
     */
    private $apiOauthEndpoint = 'api/oauth/access_token';

    /**
     * Built API endpoint.
     *
     * @var string
     */
    private $apiEndpoint = '';

    /**
     * Build API token endpoint.
     *
     * @var string
     */
    private $tokenEndpoint = '';

    /**
     * Guzzle client
     * @var Client
     */
    private $client;

    /**
     * Constructor
     *
     * @param iCmsBridge $bridge
     */
    public function __construct(iCmsBridge $bridge)
    {
        $this->cmsBridge = $bridge;

        //Allow user to override the base api url.
        if(false) {
            $this->baseApiEndpoint = '';
        }

        //Trim off trailing slash
        if(substr($this->baseApiEndpoint, -1) === '/') {
            $this->baseApiEndpoint = substr($this->baseApiEndpoint, 0, strlen($this->baseApiEndpoint) - 2);
        }

        //Set up api and token endpoints dynamically
        $this->apiEndpoint = $this->baseApiEndpoint . '/' . $this->apiVersionEndpoint;
        $this->tokenEndpoint = $this->baseApiEndpoint . '/' . $this->apiOauthEndpoint;

        //Set up new guzzle client for this instance;
        $this->client = new Client();
    }

    /**
     * Fetch a new access token from the RR API.
     *
     * @return ApiResponse;
     */
    public function getAccessToken()
    {
        $res = $this->postRequest($this->tokenEndpoint, [
            'client_id'         => '',
            'client_secret'     => '',
            'grant_type'        => 'client_credentials'
        ]);

        $response = new ApiResponse;

        if(isset($res['access_token'])) {
            $token = $res['access_token'];
            $ttl = isset($res['expires_in']) ? $res['expires_in'] : null;
            $savedToken = false;

            if(!is_null($ttl))
                $savedToken = $this->cmsBridge->saveAccessToken($token, $ttl);
            else
                $savedToken = $this->cmsBridge->saveAccessToken($token);

            $response->successful()->setContent([
                'token' => $token
            ]);

            return $response;
        }

        return $response->failed()->setContent($res);
    }

    /**
     * Note: This shouldn't be used except for testing purposes.
     *
     * If the client wants to override the base api endpoint that will be done
     * via the config settings only.
     *
     * @param  string $apiEndpoint
     * @return $this
     */
    public function overrideApiEndpoint($apiEndpoint = '')
    {
        if(!empty($apiEndpoint))
            $this->apiEndpoint = $apiEndpoint;

        return $this;
    }

    /**
     * ---------------------------------------------------------
     * | Private Methods for doing all the helpful things
     * ---------------------------------------------------------
     */

    /**
	 * Apply the json content type.
	 *
	 * @param $authenticate bool
	 * @return array
	 */
	private function headers($authenticate = false)
	{
		$headers = array(
			'Accept' => 'application/json',
		);

        //Grab token
        if($authenticate) {
            $token = $this->cmsBridge->getAccessToken();

            $headers['Authorization'] = "Bearer $token";
        }

		return $headers;
	}

    /**
	 * Make a post request to {@code $endpoint} with form params
	 * {@code form}.
	 *
	 * @param  string $endpoint
	 * @param  array  $form
	 * @return array
	 */
	private function postRequest($endpoint, array $form = array(), $needsToken = false)
	{
		$body = array();

		try {
			$res = $this->client->post($endpoint, array(
				'headers' => $this->headers($needsToken),
				'form_params' => $form,
			));

			$body = json_decode($res->getBody(), true);
		} catch (BadResponseException $e) {
			$res = $e->getResponse();
			$body = $res->getBody()->getContents();

			$body = json_decode($body, true);
		}

		return $body;
	}

	/**
	 * Make a get request to {@code $endpoint} with query params {@code $params}.
	 *
	 * @param  string $endpoint
	 * @param  array  $params
	 * @return array
	 */
	private function getRequest($endpoint, array $params = array(), $needsToken = true)
	{
		$body = array();

		try {
			$res = $this->client->get($endpoint, array(
				'headers' => $this->headers($needsToken),
				'query' => $params
			));

			$body = json_decode($res->getBody(), true);
		} catch (BadResponseException $e) {
			$res = $e->getResponse();
            $body = $res->getBody()->getContents();

            $body = json_decode($body, true);
		}

		return $body;
	}
}
