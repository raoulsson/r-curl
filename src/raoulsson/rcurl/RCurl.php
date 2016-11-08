<?php
/**
 * Created by IntelliJ IDEA.
 * User: raoulsson
 * Date: 15.05.16
 * Time: 20:20
 */
namespace raoulsson\rcurl;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;

/**
 * Class RCurl
 * @package butik\shopmachine\util
 */
class RCurl {

	/** @var LoggerInterface */
	private $logger;
	/** @var string */
	private $userName;
	/** @var string */
	private $password;

	/**
	 * Curl constructor.
	 *
	 * @param $logger LoggerInterface
	 */
	public function __construct($logger = null) {
		if($logger == null) {
			$this->logger = new NullLogger();
		} else {
			$this->logger = $logger;
		}
	}

	/**
	 * @return string
	 */
	public function getUserName() {
		return $this->userName;
	}

	/**
	 * @param string $userName
	 */
	public function setUserName($userName) {
		$this->userName = $userName;
	}

	/**
	 * @return string
	 */
	public function getPassword() {
		return $this->password;
	}

	/**
	 * @param string $password
	 */
	public function setPassword($password) {
		$this->password = $password;
	}

	/**
	 * @param $url
	 * @param null $getFields
	 * @param array|string $headerData
	 * @param bool $debug
	 * @return array
	 */
	public function makeGetCall($url, $getFields = null, array $headerData = null, $debug = false) {
		return $this->doMakeCall("GET", $url, $getFields, null, $headerData, $debug);
	}

	/**
	 * @param $url
	 * @param $postFields
	 * @param array|string $headerData
	 * @param bool $debug
	 * @return array
	 */
	public function makePostCall($url, $postFields = null, array $headerData = null, $debug = false) {
		return $this->doMakeCall("POST", $url, null, $postFields, $headerData, $debug);
	}

	/**
	 * Back and forth the params but for client code convenience we do it
	 *
	 * @param $url
	 * @param array $excludeFields
	 * @param array $addGetFields
	 * @param bool $debug
	 * @return array
	 */
	public function makeGetProxyCall($url, array $excludeFields = [], array $addGetFields = [], $debug = false) {
		$uri = explode("?", $_SERVER["REQUEST_URI"]);
		$url .= $uri[0];
		$getFields = [];
		if(count($uri) > 1) {
			$kvs = explode("&", $uri[1]);
			foreach($kvs as $kv) {
				$lr = explode("=", $kv);
				if(!in_array($lr[0], $excludeFields, false)) {
					$getFields[$lr[0]] = $lr[1];
				}
			}
		}
		$getFields = array_merge($getFields, $addGetFields);
		return $this->doMakeCall("GET", $url, $getFields, null, null, $debug);
	}

	/**
	 * @param $method
	 * @param $url
	 * @param null $getFields
	 * @param $postFields
	 * @param array|string $headerData
	 * @param bool $debug
	 * @return array
	 */
	private function doMakeCall($method, $url, $getFields = null, $postFields = null, array $headerData = null, $debug = false) {
		if($debug) {
			$this->logger->debug("Curl to: " . $url);
		}

		if(self::contains($url, "?")) {
			throw new RuntimeException("URL has to be clean of params, no ?");
		}

		$ch = curl_init();

		if($method == "GET") {
			$url .= $this->appendGetFields($getFields);
		}

		curl_setopt($ch, CURLOPT_URL, $url);

		if($method == "POST") {
			curl_setopt($ch, CURLOPT_POST, 1);
		}

		if($method == "POST" && $postFields) {
			curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
			if(!$headerData) {
				$headerData = [];
			}
			$headerData[] = "Content-Length: " . strlen($postFields);
		}

		if($headerData) {
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headerData);
		}

		if($this->userName && $this->password) {
			curl_setopt($ch, CURLOPT_USERPWD, $this->userName . ":" . $this->password);
		}

		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
		curl_setopt($ch, CURLOPT_TIMEOUT, 20);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_USERAGENT, "rcurl");
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

		$fp = null;
		$tmpLog = null;
		if($debug) {
			$tmpLog = tempnam(sys_get_temp_dir(), 'crl');
			$fp = fopen($tmpLog, 'w');
			curl_setopt($ch, CURLOPT_VERBOSE, true);
			curl_setopt($ch, CURLOPT_STDERR, $fp);
		}

		$response = curl_exec($ch);

		$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		curl_close($ch);

		$header = substr($response, 0, $headerSize);
		$body = substr($response, $headerSize);

		if($debug) {
			fclose($fp);
			$fp = fopen($tmpLog, 'r');
			if ($fp) {
				while(($line = fgets($fp)) !== false) {
					$this->logger->debug("CurlHeader: " . trim(preg_replace('/\s\s+/', ' ', $line)));
				}
			}
			fclose($fp);
			unlink($tmpLog);
			$this->logger->debug("CurlBody: " . $body);
		}

		return [
			"code" => $httpCode,
			"header" => $header,
			"body"   => $body,
		];
	}

	/**
	 * @param $getFields
	 * @return null|string
	 */
	private function appendGetFields($getFields) {
		if($getFields) {
			$sb = "";
			$del = "?";
			foreach($getFields as $k => $v) {
				$sb .= $del . $k . "=" . rawurlencode($v);
				$del = "&";
			}
			return $sb;
		}
		return null;
	}

	/**
	 * @param $heystack
	 * @param $needle
	 *
	 * @return bool
	 */
	private static function contains($heystack, $needle) {
		return strpos($heystack, $needle) !== false;
	}

}
