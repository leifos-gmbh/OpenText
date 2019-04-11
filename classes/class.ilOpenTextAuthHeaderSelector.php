<?php

/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */

use Swagger\Client\HeaderSelector;

/**
 * Header selector which uses Content type x-www-url-encoded
 *
 * @author Stefan Meyer <smeyer.ilias@gmx.de>
 */

class ilOpenTextAuthHeaderSelector extends HeaderSelector
{
	const X_WWW_URL_ENCODED = 'application/x-www-form-urlencoded';



	/**
	 * @param string[] $accept
	 * @param string[] $contentTypes
	 * @return array
	 */
	public function selectHeaders($accept, $contentTypes)
	{
		$headers = [];

		$accept = $this->selectAcceptHeader($accept);
		if ($accept !== null) {
			$headers['Accept'] = $accept;
		}

		$headers['Content-Type'] = $this->selectContentTypeHeader($contentTypes);
		return $headers;
	}

	/**
	 * @param string[] $accept
	 * @return array
	 */
	public function selectHeadersForMultipart($accept)
	{
		$headers = $this->selectHeaders($accept, []);

		unset($headers['Content-Type']);
		return $headers;
	}

	/**
	 * Return the header 'Accept' based on an array of Accept provided
	 *
	 * @param string[] $accept Array of header
	 *
	 * @return string Accept (e.g. application/json)
	 */
	private function selectAcceptHeader($accept)
	{
		if (count($accept) === 0 || (count($accept) === 1 && $accept[0] === '')) {
			return null;
		} elseif (preg_grep("/application\/json/i", $accept)) {
			return 'application/json';
		} else {
			return implode(',', $accept);
		}
	}


	/**
	 * @return string|void
	 */
	private function selectContentTypeHeader($contentType) : string
	{
		return self::X_WWW_URL_ENCODED;
	}
}