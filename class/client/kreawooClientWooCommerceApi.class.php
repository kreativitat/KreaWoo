<?php
/* Copyright (C) 2026       Kreativität Works		<support@kreativitat.com>
 * Copyright (C) 2024-2026  Kreativitat             <mail@kreativitat.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *      \file       htdocs/kreawoo/class/business/kreawooClientWooCommerceApi.class.php
 *      \ingroup    kreawoo
 *      \brief      This file for managing Client WooCommerce API
 */

dol_include_once('/kreawoo/class/client/kreawooClientApi.class.php');
dol_include_once('/kreawoo/includes/oauth-subscriber-woocommerce/src/Oauth1.php');
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Subscriber\Oauth\Oauth1;

/**
 * 	Class to manage Client WooCommerce API
 */
class kreawooClientWooCommerceApi extends kreawooClientApi
{
	/**
	 * @var string  API version
	 */
	public $api_version;
	/**
	 * @var string  Authentication type
	 */
	public $authentication_type;
	/**
	 * @var string  Authentication login
	 */
	public $authentication_login;
	/**
	 * @var string  Authentication password
	 */
	public $authentication_password;
	/**
	 * @var bool  Include post parameters in signature
	 */
	public $include_post_parameters_in_signature;
	/**
	 * @var bool  Use ?rest_route fallback mode
	 */
	public $use_rest_route_fallback = false;

	/**
	 *  Connect to the Wordpress
	 *
	 * @param   kreawooSite   $site   kreawooSite object
	 * @return	int		                <0 if KO, >0 if OK
	 */
	public function connection($site)
	{
		global $conf, $langs;
		$this->errors = array();
		if (!class_exists('GuzzleHttp\\Client') || !class_exists('GuzzleHttp\\HandlerStack') || !class_exists('GuzzleHttp\\Middleware')) {
			$this->errors[] = $langs->trans('kreawooErrorConnectAPI', $site->name);
			$this->errors[] = 'Missing Guzzle dependency (vendor autoload not available).';
			dol_syslog(__METHOD__ . " Error: " . $this->errorsToString(), LOG_ERR);
			return -1;
		}

		$this->api_version = !empty($site->api_version) ? $site->api_version : 'v3';
		$this->authentication_type = !empty($site->authentication_type) ? $site->authentication_type : 'oauth1_header';
		$this->authentication_login = $site->user_name;
		$this->authentication_password = $site->user_password;
		$this->include_post_parameters_in_signature = !empty($site->parameters['site_api_include_post_parameters_in_signature']);
		$this->use_rest_route_fallback = false;
		$timeout = $site->timeout > 0 ? $site->timeout : 30;

		$this->api_version = strtolower($this->api_version);

		$raw_api_url = trim((string) $site->webservice_address);
		$normalized_api_url = rtrim($raw_api_url, '/');
		if (preg_match('#^(.*)/wp-json/wc/(v[0-9]+)(?:/.*)?$#i', $normalized_api_url, $matches)) {
			$this->api_version = strtolower($matches[2]);
			$normalized_api_url = rtrim($matches[1], '/');
		} elseif (preg_match('#^(.*)/wp-json(?:/.*)?$#i', $normalized_api_url, $matches)) {
			$normalized_api_url = rtrim($matches[1], '/');
		}

		if (!empty($normalized_api_url)) {
			$this->api_url = $normalized_api_url;
			if ($this->api_url !== rtrim($raw_api_url, '/')) {
				dol_syslog(__METHOD__ . " - normalized WooCommerce site URL from '{$raw_api_url}' to '{$this->api_url}'", LOG_WARNING);
			}
		} else {
			$this->api_url = rtrim($raw_api_url, '/');
		}
		$this->api_url_prefix = '/wp-json/wc/' . $this->api_version;

		try {
			$userAgent = 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:34.0) Gecko/20100101 Firefox/34.0';
			if (!empty($conf->global->ECOMMERCE_USER_AGENT)) {
				$userAgent = $conf->global->ECOMMERCE_USER_AGENT;
			}
			$options = [
				// Base URI is used with relative requests
				'base_uri' => $this->api_url,
				// You can set any number of default request options.
				'timeout' => $timeout,
				// 'cookies' => true,
				'headers' => array('User-Agent' => $userAgent),
			];
			if (!empty($conf->global->KREAWOO_WOOCOMMERCE_NO_VERIFY_SSL)) $options['verify'] = false;

			$stack = HandlerStack::create();
			if (in_array($this->authentication_type, [ 'oauth1_header', 'oauth1_query' ])) {
				$middleware = new Oauth1([
					'consumer_key'    => $this->authentication_login,
					'consumer_secret' => $this->authentication_password,
					'request_method' => $this->authentication_type == 'oauth1_header' ? Oauth1::REQUEST_METHOD_HEADER : Oauth1::REQUEST_METHOD_QUERY,
					'signature_method' => Oauth1::SIGNATURE_METHOD_HMACSHA256,
					'api_version' => $this->api_version,
					'include_post_parameters_in_signature' => $this->include_post_parameters_in_signature,
				]);

				$stack->push($middleware);
				$options['auth'] = 'oauth';
			}

			// Define the retry middleware
			$retryMiddleware = Middleware::retry(
				function ($retries, $request, $response, $exception) {
					// Limit the number of retries to 5
					if ($retries >= 5) {
						return false;
					}

					// Retry on server errors (5xx HTTP status codes)
					if ($response) {
						if ($response->getStatusCode() >= 500) {
							dol_syslog('ecommerceClientWooCommerceApi::connection is retrying on status code ' . $response->getStatusCode(), LOG_WARNING);
							return true;
						} elseif ($response->getStatusCode() == 401) {
							$error_info = json_decode(trim($response->getBody()->getContents()), true);
							if (!empty($error_info['message']) && preg_match('/Nonce/i', $error_info['message'])) {
								dol_syslog('ecommerceClientWooCommerceApi::connection is retrying on status code ' . $response->getStatusCode() . " ; " . $request->getMethod() . " " . $request->getUri(), LOG_WARNING);
								return true;
							}
						}
					}

					// Retry on connection exceptions
					if ($exception instanceof RequestException && $exception->getCode() === 0) {
						return true;
					}

					return false;
				},
				function ($retries) {
					// Define a delay function (e.g., exponential backoff)
					return (int) pow(2, $retries) * 1000; // Delay in milliseconds
				}
			);

			// Add the retry middleware to the handler stack
			$stack->push($retryMiddleware);

			$options['handler'] = $stack;

			$this->client = new Client($options);
		} catch (Exception $e) {
			$this->errors[] = $langs->trans('kreawooErrorConnectAPI', $site->name);
			$this->errors[] = $e->getMessage();
			dol_syslog(__METHOD__ . " Error: " . $this->errorsToString(), LOG_ERR);
			return -1;
		}

		return 1;
	}

	/**
	 *  Send to the Api
	 *
	 * @param   string  $method     						Method request
	 * @param   string  $url        						Url request
	 * @param   array   $options    						Options request
	 * @param   bool  	$without_prefix						Without api url prefix
	 * @param   int  	$status_code						Status code returned
	 * @param   int  	$error_info							Error info returned
	 * @return	array                 						null if KO otherwise result data
	 */
	public function sendToApi($method, $url, $options = [], $without_prefix = false, &$status_code = null, &$error_info = null)
	{
		if ($this->authentication_type == 'basic') {
			$options['headers']['Authorization'] = 'Basic ' . base64_encode($this->authentication_login . ':' . $this->authentication_password);
		} elseif ($this->authentication_type == 'query') {
			$options['query']['consumer_key'] = $this->authentication_login;
			$options['query']['consumer_secret'] = $this->authentication_password;
		}

		$rest_route_prefix = trim((string) $this->api_url_prefix, '/');
		$rest_route_prefix = preg_replace('#^wp-json/#i', '', $rest_route_prefix);

		$route_url = (string) $url;
		$route_query_pos = strpos($route_url, '?');
		$route_query_string = '';
		if ($route_query_pos !== false) {
			$route_query_string = substr($route_url, $route_query_pos + 1);
			$route_url = substr($route_url, 0, $route_query_pos);
		}

		$fallback_options = $options;
		if (empty($fallback_options['query']) || !is_array($fallback_options['query'])) {
			$fallback_options['query'] = [];
		}
		if ($route_query_string !== '') {
			$route_query = [];
			parse_str($route_query_string, $route_query);
			if (!empty($route_query) && is_array($route_query)) {
				$fallback_options['query'] = array_merge($route_query, $fallback_options['query']);
			}
		}

		$rest_route = '/' . $rest_route_prefix . '/' . ltrim($route_url, '/');
		$rest_route = preg_replace('#/+#', '/', $rest_route);
		$fallback_options['query']['rest_route'] = $rest_route;

		// When using query-string auth, also send credentials as Basic Auth header in the fallback
		// path. Cloudflare and some proxies strip consumer_key/consumer_secret from query params
		// before the request reaches WordPress, causing 401. The Authorization header survives.
		if ($this->authentication_type == 'query') {
			$fallback_options['headers']['Authorization'] = 'Basic ' . base64_encode($this->authentication_login . ':' . $this->authentication_password);
		}

		if (!empty($this->use_rest_route_fallback) && empty($without_prefix) && !preg_match('/(?:^|[?&])rest_route=/', (string) $url)) {
			return parent::sendToApi($method, '', $fallback_options, true, $status_code, $error_info);
		}

		$result = parent::sendToApi($method, $url, $options, $without_prefix, $status_code, $error_info);
		if ($result !== null) {
			return $result;
		}

		// Fallback for WordPress installations where pretty permalinks are disabled.
		if ((int) $status_code !== 404 || !empty($without_prefix) || preg_match('/(?:^|[?&])rest_route=/', (string) $url)) {
			return $result;
		}

		$first_attempt_errors = is_array($this->errors) ? $this->errors : [];

		$this->errors = [];
		$this->error = '';
		$this->use_rest_route_fallback = true;

		$result = parent::sendToApi($method, '', $fallback_options, true, $status_code, $error_info);
		if ($result !== null) {
			return $result;
		}
		if ((int) $status_code >= 400) {
			$error_code = is_array($error_info) && !empty($error_info['code']) ? $error_info['code'] : '';
			dol_syslog(__METHOD__ . " - fallback request failed with status={$status_code} error_code='{$error_code}' method={$method} url={$url}", LOG_WARNING);
		}

		if (!empty($first_attempt_errors) && (empty($status_code) || (int) $status_code === 404)) {
			$this->errors = array_merge($first_attempt_errors, is_array($this->errors) ? $this->errors : []);
		}

		return $result;
	}
}
