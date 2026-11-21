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
 *      \file       htdocs/kreawoo/class/business/kreawooClientApi.class.php
 *      \ingroup    kreawoo
 *      \brief      This file for managing Client API
 */

dol_include_once('/kreawoo/class/business/kreawooUtils.class.php');
if (!class_exists('GuzzleHttp\\Client')) {
	$moduleAutoload = DOL_DOCUMENT_ROOT . '/custom/kreawoo/vendor/autoload.php';
	if (is_readable($moduleAutoload)) {
		require_once $moduleAutoload;
	}
}
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * 	Class to manage Client API
 */
class kreawooClientApi
{
	/**
	 * @var DoliDB Database handler.
	 */
	public $db;
	/**
	 * @var string Error
	 */
	public $error = '';
	/**
	 * @var array Errors
	 */
	public $errors = array();

	/**
	 * @var bool  Debug mode
	 */
	public $debug_mode = false;

	/**
	 * @var Client  Client REST handler
	 */
	public $client;
	/**
	 * @var string  API URL
	 */
	public $api_url;
	/**
	 * @var string  API URL prefix for requesting the API
	 */
	public $api_url_prefix;

	const METHOD_GET = 'GET';
	const METHOD_HEAD = 'HEAD';
	const METHOD_DELETE = 'DELETE';
	const METHOD_PUT = 'PUT';
	const METHOD_PATCH = 'PATCH';
	const METHOD_POST = 'POST';


	/**
	 * Constructor
	 *
	 * @param	DoliDB		$_db			Database handler
	 * @param	bool		$debug_mode		Debug mode
	 */
	public function __construct($_db, $debug_mode = false)
	{
		global $db;
		$this->db = is_object($_db) ? $_db : $db;
		$this->debug_mode = !empty($debug_mode);
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
		$logUrl = $this->sanitizeUrlForLog($url);
		$logOptions = $this->sanitizeForLog($options);
		dol_syslog(__METHOD__ . " - method=" . $method . " url=" . $logUrl . " options=" . json_encode($logOptions) . " without_prefix=" . $without_prefix, LOG_NOTICE);
		global $conf, $langs;

		$stopwatch_id = -1;
		try {
			$status_code = 0;
			$request_url = rtrim($this->api_url, '/') . '/' . ($without_prefix ? '' : trim($this->api_url_prefix, '/') . '/') . ltrim($url, '/');

			// Disabled send data to site
			if (in_array($method, [self::METHOD_POST, self::METHOD_PUT, self::METHOD_DELETE, self::METHOD_PATCH]) && !empty($conf->global->ECOMMERCE_DISABLED_SEND_DATA_TO_API)) {
				return [];
			}

			$stopwatch_id = kreawooUtils::startStopwatch(__METHOD__ . " - " . $method . " " . $this->sanitizeUrlForLog($request_url));
			switch ($method) {
				case self::METHOD_HEAD:
					$response = $this->client->head($request_url, $options);
					break;
				case self::METHOD_GET:
					$response = $this->client->get($request_url, $options);
					break;
				case self::METHOD_POST:
					$response = $this->client->post($request_url, $options);
					break;
				case self::METHOD_PUT:
					$response = $this->client->put($request_url, $options);
					break;
				case self::METHOD_DELETE:
					$response = $this->client->delete($request_url, $options);
					break;
				case self::METHOD_PATCH:
					$response = $this->client->patch($request_url, $options);
					break;
				default:
					$this->errors[] = 'Bad REST Method';
					dol_syslog(__METHOD__ . " Errors: " . $this->errorsToString(), LOG_ERR);
					return null;
			}
			kreawooUtils::stopAndLogStopwatch($stopwatch_id);

			$status_code = (int) $response->getStatusCode();
			$request_data = trim($response->getBody()->getContents());
			$msg_error =  "Method: " . $method . " Url: " . $this->sanitizeUrlForLog($request_url) . " - Options: " . json_encode($logOptions) . " - Data: " . $this->sanitizeStringForLog($request_data);
			dol_syslog(__METHOD__ . " - " . $msg_error, LOG_DEBUG);

			$data =  json_decode($request_data, true);
			if (!is_array($data)) {
				if ($this->debug_mode) {
					$this->errors[] = $langs->trans('kreawooErrorBadConvertResult') . ' - ' . $msg_error . ' - Converted data: ' . $data . " - Error: " . json_last_error_msg();
				} else {
					$this->errors[] = $langs->trans('kreawooErrorBadConvertResult') . " - Error: " . json_last_error_msg();
				}
			}
			dol_syslog(__METHOD__ . " - method=" . $method . " url=" . $logUrl . " options=" . json_encode($logOptions) . " without_prefix=" . $without_prefix . " - Data=" . json_encode($this->sanitizeForLog($data)), LOG_NOTICE);
			return $data;
		} catch (RequestException $e) {
			kreawooUtils::stopAndLogStopwatch($stopwatch_id);

			$request = $e->getRequest();
			$response = $e->getResponse();

			if (isset($response)) $status_code = (int) $response->getStatusCode();
            if (isset($response)) $error_info = json_decode(trim($response->getBody()->getContents()), true);
			if (empty($error_info)) $error_info = [];

			$errors_details = array();
			if (isset($request)) $errors_details[] = $this->_requestToString($request);
			if (isset($response)) $errors_details[] = $this->_responseToString($response);
			else $errors_details[] = '<pre>' . dol_nl2br($this->sanitizeStringForLog((string) $e)) . '</pre>';

			if ($this->debug_mode) {
				$this->errors = array_merge($this->errors, $errors_details);
			} else {
				if (isset($response)) {
                    $boby = strip_tags($response->getBody());
					$boby = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function ($match) {
						return mb_convert_encoding(pack('H*', $match[1]), 'UTF-8', 'UCS-2BE');
					}, $boby);
					$this->errors[] = '<b>' . $langs->trans('kreawooResponseCode') . ': </b>' . $response->getStatusCode() . '<br>' .
						'<b>' . $langs->trans('kreawooResponseReasonPhrase') . ': </b>' . $response->getReasonPhrase() .
						(!empty($boby) ? '<br><em>' . $boby . '</em>' : '');
				} else $this->errors[] = $e->getMessage();
			}

			dol_syslog(__METHOD__ . " Error: " . dol_htmlentitiesbr_decode(implode(', ', $errors_details)), LOG_ERR);
			return null;
		} catch (Exception $e) {
			kreawooUtils::stopAndLogStopwatch($stopwatch_id);
			if ($this->debug_mode) {
				$this->errors[] = (string)$e;
			} else {
				$this->errors[] = $e->getMessage();
			}

			dol_syslog(__METHOD__ . " Error: " . $this->sanitizeStringForLog((string) $e), LOG_ERR);
			return null;
		}
	}

	/**
	 *  Format the request to a string
	 *
	 * @param   RequestInterface    $request    Request handler
	 * @return	string		                    Formatted string of the request
	 */
	protected function _requestToString(RequestInterface $request)
	{
		global $langs;

		$out = '';
		$out .= '<b>' . $langs->trans('kreawooRequestData') . ': </b><br><hr>';
		$out .= '<div style="max-width: 1024px;">';
		$out .= '<b>' . $langs->trans('kreawooRequestProtocolVersion') . ': </b>' . $request->getProtocolVersion() . '<br>';
		$out .= '<b>' . $langs->trans('kreawooRequestUri') . ': </b>' . $this->sanitizeUrlForLog((string) $request->getUri()) . '<br>';
		$out .= '<b>' . $langs->trans('kreawooRequestTarget') . ': </b>' . $this->sanitizeUrlForLog($request->getRequestTarget()) . '<br>';
		$out .= '<b>' . $langs->trans('kreawooRequestMethod') . ': </b>' . $request->getMethod() . '<br>';
		$out .= '<b>' . $langs->trans('kreawooRequestHeaders') . ':</b><ul>';
		foreach ($request->getHeaders() as $name => $values) {
			$out .= '<li><b>' . $name . ': </b>' . ($this->isSensitiveLogKey($name) ? '[REDACTED]' : implode(', ', $values)) . '</li>';
		}
		$out .= '</ul>';
		$out .= '<b>' . $langs->trans('kreawooRequestBody') . ': </b>';
		$out .= '<br><em>' . $this->sanitizeStringForLog((string) $request->getBody()) . '</em><br>';
		$out .= '</div>';
		return $out;
	}

	/**
	 * Remove credentials from values written to operational logs.
	 *
	 * @param mixed $value Value to sanitize
	 * @param string $key Parent key
	 * @return mixed
	 */
	protected function sanitizeForLog($value, $key = '')
	{
		if ($this->isSensitiveLogKey($key)) {
			return '[REDACTED]';
		}
		if (is_array($value)) {
			$sanitized = array();
			foreach ($value as $itemKey => $itemValue) {
				$sanitized[$itemKey] = $this->sanitizeForLog($itemValue, (string) $itemKey);
			}
			return $sanitized;
		}
		if (is_object($value)) {
			return $this->sanitizeForLog(get_object_vars($value), $key);
		}
		if (is_string($value)) {
			return $this->sanitizeStringForLog($value);
		}
		return $value;
	}

	/**
	 * @param string $url URL or request target
	 * @return string
	 */
	protected function sanitizeUrlForLog($url)
	{
		return preg_replace_callback(
			'/(^|[?&])([^=?&#]+)=([^&#]*)/',
			function ($matches) {
				return $this->isSensitiveLogKey(rawurldecode($matches[2])) ? $matches[1].$matches[2].'=[REDACTED]' : $matches[0];
			},
			(string) $url
		);
	}

	/**
	 * @param string $value Serialized request data
	 * @return string
	 */
	protected function sanitizeStringForLog($value)
	{
		$decoded = json_decode($value, true);
		if (is_array($decoded) && json_last_error() === JSON_ERROR_NONE) {
			return json_encode($this->sanitizeForLog($decoded));
		}
		$value = $this->sanitizeUrlForLog($value);
		return preg_replace(
			'/("(?:authorization|password|passwd|secret|token|consumer_key|consumer_secret|api_key|access_token|refresh_token|user_password|wordpress_authentication_password)"\s*:\s*")[^"]*(")/i',
			'$1[REDACTED]$2',
			$value
		);
	}

	/**
	 * @param string $key Option or header key
	 * @return bool
	 */
	protected function isSensitiveLogKey($key)
	{
		$key = preg_replace('/[^a-z0-9]/i', '', (string) $key);
		return (bool) preg_match('/(?:authorization|password|passwd|secret|token|consumerkey|apikey|cookie)/i', $key);
	}

	/**
	 *  Format the response to a string
	 *
	 * @param   ResponseInterface   $response   Response handler
	 * @return	string		                    Formatted string of the response
	 */
	protected function _responseToString(ResponseInterface $response)
	{
		global $langs;

		$out = '';
		$out .= '<b>' . $langs->trans('kreawooResponseData') . ': </b><br><hr>';
		$out .= '<div style="max-width: 1024px;">';
		$out .= '<b>' . $langs->trans('kreawooResponseProtocolVersion') . ': </b>' . $response->getProtocolVersion() . '<br>';
		$out .= '<b>' . $langs->trans('kreawooResponseCode') . ': </b>' . $response->getStatusCode() . '<br>';
		$out .= '<b>' . $langs->trans('kreawooResponseReasonPhrase') . ': </b>' . $response->getReasonPhrase() . '<br>';
		$out .= '<b>' . $langs->trans('kreawooResponseHeaders') . ':</b><ul>';
		foreach ($response->getHeaders() as $name => $values) {
			$out .= '<li><b>' . $name . ': </b>' . ($this->isSensitiveLogKey($name) ? '[REDACTED]' : implode(', ', $values)) . '</li>';
		}
		$out .= '</ul>';
		$out .= '<b>' . $langs->trans('kreawooResponseBody') . ': </b>';
		$body = $this->sanitizeForLog(json_decode($response->getBody(), true));
		if (is_array($body)) {
			$out .= '<ul>';
			foreach ($body as $name => $values) {
				$out .= '<li><b>' . $name . ': </b>' . (is_array($values) || is_object($values) ? json_encode($values, JSON_UNESCAPED_UNICODE) : $values) . '</li>';
			}
			$out .= '</ul>';
		} else {
			$out .= '<br><em>' . strip_tags($this->sanitizeStringForLog((string) $response->getBody())) . '</em><br>';
		}
		$out .= '</div>';
		return $out;
	}

	/**
	 * Method to output saved errors
	 *
	 * @param   string      $separator      Separator between each error
	 * @return	string		                String with errors
	 */
	public function errorsToString($separator = ', ')
	{
		return $this->error . (is_array($this->errors) ? (!empty($this->error) ? $separator : '') . join($separator, $this->errors) : '');
	}
}
