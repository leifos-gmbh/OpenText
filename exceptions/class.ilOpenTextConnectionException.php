<?php
/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * Thrown on connection failures.
 *
 * Class ilOpenTextConnectionException
 *
 * @author Stefan Meyer <smeyer.ilias@gmx.de>
 *
 */
class ilOpenTextConnectionException extends ilException
{
	const ERR_LOGIN_FAILED = 1;

	private static $code_messages = [
		self::ERR_LOGIN_FAILED => 'exception_login_failed'
	];


	/**
	 * @return string
	 */
	public function exceptionCodeToString()
	{
		switch($this->getCode()) {

			case self::ERR_LOGIN_FAILED:
				return 'exc_login_failed';
		}

	}

	/**
	 * @param int $code
	 * @return string
	 */
	public static function getMessageForCode(int $code)
	{
		return self::$code_messages[$code];
	}


	/**
	 * @param int $code
	 * @return string
	 */
	public static function translateExceptionCode(int $code) : string
	{
		$plugin = \ilOpenTextPlugin::getInstance();
		return $plugin->txt(self::exceptionCodeToString($code));
	}

}