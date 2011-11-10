<?php

/**
 * Postmark PHP class
 *
 * Copyright 2009, Markus Hedlund, Mimmin AB, www.mimmin.com
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @author Markus Hedlund (markus@mimmin.com) at mimmin (www.mimmin.com)
 * @copyright Copyright 2009, Markus Hedlund, Mimmin AB, www.mimmin.com
 * @version 0.3
 * @license http://www.opensource.org/licenses/mit-license.php The MIT License
 *
 * Usage:
 * Mail_Postmark::compose()
 *      ->to('address@example.com', 'Name')
 *      ->subject('Subject')
 *      ->messagePlain('Plaintext message')
 *	    ->tag('Test tag')
 *      ->send();
 *
 * or:
 *
 * $email = new Mail_Postmark();
 * $email->to('address@example.com', 'Name')
 *      ->subject('Subject')
 *      ->messagePlain('Plaintext message')
 *	    ->tag('Test tag')
 *      ->send();
 */

namespace jmvc\classes;

class Postmark
{
	const DEBUG_OFF = 0;
	const DEBUG_VERBOSE = 1;
	const DEBUG_RETURN = 2;

	private $_fromName;
	private $_fromAddress;
	private $_tag;
	private $_toName;
	private $_toAddress=array();
	private $_replyToName;
	private $_replyToAddress;
	private $_subject;
	private $_messagePlain;
	private $_messageHtml;
	private $_debugMode = self::DEBUG_OFF;

	/**
	* Initialize
	*/
	public function __construct()
	{
		$this->_default('POSTMARKAPP_MAIL_FROM_NAME', null);
		$this->_default('POSTMARKAPP_MAIL_FROM_ADDRESS', null);
		$this->_default('POSTMARKAPP_API_KEY', null);
		$this->from(POSTMARKAPP_MAIL_FROM_ADDRESS, POSTMARKAPP_MAIL_FROM_NAME)->messageHtml(null)->messagePlain(null);
	}

	/**
	* New e-mail
	* @return Mail_Postmark
	*/
	public static function compose()
	{
		return new self();
	}

	/**
	* Turns debug output on
	* @param int $mode One of the debug constants
	* @return Mail_Postmark
	*/
	public function &debug($mode = self::DEBUG_VERBOSE)
	{
		$this->_debugMode = $mode;
		return $this;
	}

	/**
	* Specify sender. Overwrites default From.
	* @param string $address E-mail address used in From
	* @param string $name Optional. Name used in From
	* @return Mail_Postmark
	*/
	public function &from($address, $name = null)
	{
		$this->_fromAddress = $address;
		$this->_fromName = $name;
		return $this;
	}

	/**
	* Specify sender name. Overwrites default From name, but doesn't change address.
	* @param string $name Name used in From
	* @return Mail_Postmark
	*/
	public function &fromName($name)
	{
		$this->_fromName = $name;
		return $this;
	}

	/**
	* You can categorize outgoing email using the optional Tag  property.
	* If you use different tags for the different types of emails your
	* application generates, you will be able to get detailed statistics
	* for them through the Postmark user interface.
	* Only 1 tag per mail i supported.
	*
	* @param string $tag One tag
	* @return Mail_Postmark
	*/
	public function &tag($tag)
	{
		$this->_tag = $tag;
		return $this;
	}

	/**
	* Specify receiver
	* @param string $address E-mail address used in To
	* @param string $name Optional. Name used in To
	* @return Mail_Postmark
	*/
	public function &to()
	{
		foreach (func_get_args() as $address) {
			if (!$this->_validateAddress($address)) {
				throw new \Exception('invalid email address: '.$address);
			}

			$this->_toAddress[] = $address;
		}

		$this->_toAddress = array_unique($this->_toAddress);
		return $this;
	}

	/**
	* Specify reply-to
	* @param string $address E-mail address used in To
	* @param string $name Optional. Name used in To
	* @return Mail_Postmark
	*/
	public function &replyTo($address, $name = null)
	{
		$this->_replyToAddress = $address;
		$this->_replyToName = $name;
		return $this;
	}

	/**
	* Specify subject
	* @param string $subject E-mail subject
	* @return Mail_Postmark
	*/
	public function &subject($subject)
	{
		$this->_subject = $subject;
		return $this;
	}

	/**
	* Add plaintext message. Can be used in conjunction with messageHtml()
	* @param string $message E-mail message
	* @return Mail_Postmark
	*/
	public function &messagePlain($message)
	{
		if ($message) {
			$message = html_entity_decode($message);
		}

		$this->_messagePlain = $message;
		return $this;
	}

	/**
	* Add HTML message. Can be used in conjunction with messagePlain()
	* @param string $message E-mail message
	* @return Mail_Postmark
	*/
	public function &messageHtml($message)
	{
		$this->_messageHtml = $message;
		return $this;
	}

	public function queue()
	{
		$this->preSendCheck();
		$data = $this->_prepareData();

		$m = new \jmvc\models\Postmark_Mail_Queue();
		$m->data = json_encode($data);
		$m->save();
	}

	public static function flush_queue()
	{
		if (!IS_PRODUCTION) {
			return;
		}

		$headers = array(
			'Accept: application/json',
			'Content-Type: application/json',
			'X-Postmark-Server-Token: ' . POSTMARKAPP_API_KEY
		);

		while ($queued_messages = \jmvc\models\Postmark_Mail_Queue::get_batch()) {

			$data = '[';
			$ids = array();

			foreach ($queued_messages as $msg) {
				$ids[] = $msg->id;
				$data .= $msg->data.', ';
			}

			$data = substr($data, 0, -2).']';

			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, 'http://api.postmarkapp.com/email/batch');
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

			$return = curl_exec($ch);

			if (curl_error($ch) != '') {
				\jmvc::log(date('r')."\nPostmark CURL error: ".curl_error($ch), 'postmark');
				return;
			}

			\jmvc\models\Postmark_Mail_Queue::clear_ids($ids);

			$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

			if (!self::_isTwoHundred($httpCode)) {
				$message = json_decode($return)->Message;
				\jmvc::log(date('r')."\nPostmark Error $httpCode:".$message, 'postmark');
			}
		}
	}

	private function preSendCheck()
	{
		if (is_null(POSTMARKAPP_API_KEY)) {
			throw new \ErrorException('Postmark API key is not set');
		}

		if (is_null($this->_fromAddress)) {
			throw new \ErrorException('From address is not set');
		}

		if (empty($this->_toAddress)) {
			throw new \ErrorException('To address is not set');
		}

		if (!$this->_validateAddress($this->_fromAddress)) {
			throw new \ErrorException("Invalid from address '{$this->_fromAddress}'");
		}

		if (isset($this->_replyToAddress) && !$this->_validateAddress($this->_replyToAddress)) {
			throw new \ErrorException("Invalid reply to address '{$this->_replyToAddress}'");
		}
	}

	/**
	* Sends the e-mail. Prints debug output if debug mode is turned on
	* @return Mail_Postmark
	*/
	public function &send()
	{
		$this->preSendCheck();

		if (!IS_PRODUCTION) {
			static $sent = false;

			if (!defined('TEST_EMAIL_ADDRESS') || $sent) {
				return;
			}

			$this->_toAddress = array(TEST_EMAIL_ADDRESS);
			$sent = true;
		}


		$data = $this->_prepareData();
		$headers = array(
			'Accept: application/json',
			'Content-Type: application/json',
			'X-Postmark-Server-Token: ' . POSTMARKAPP_API_KEY
		);

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, 'http://api.postmarkapp.com/email');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

		$return = curl_exec($ch);

		if ($this->_debugMode == self::DEBUG_VERBOSE) {
			echo "JSON: " . json_encode($data) . "\nHeaders: \n\t" . implode("\n\t", $headers) . "\nReturn:\n$return";

		} else if ($this->_debugMode == self::DEBUG_RETURN) {
			return array(
				'json' => json_encode($data),
				'headers' => $headers,
				'return' => $return
			);
		}

		if (curl_error($ch) != '') {
			\jmvc::log(date('r')."\nPostmark CURL error: ".curl_error($ch), 'postmark');
			return $this;
		}

		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		if (!self::_isTwoHundred($httpCode)) {
			$message = json_decode($return)->Message;
			\jmvc::log(date('r')."\nPostmark Error $httpCode:".$message, 'postmark');
		}

		return $this;
	}

	/**
	* Prepares the data array
	*/
	private function _prepareData()
	{
		$data = array(
			'Subject' => $this->_subject
		);

		$data['From'] = is_null($this->_fromName) ? $this->_fromAddress : "{$this->_fromName} <{$this->_fromAddress}>";

		$data['To'] = implode(', ', $this->_toAddress);

		if (!is_null($this->_messageHtml)) {
			$data['HtmlBody'] = $this->_messageHtml;
		}

		if (!is_null($this->_messagePlain)) {
			$data['TextBody'] = $this->_messagePlain;
		}

		if (!is_null($this->_tag)) {
			$data['Tag'] = $this->_tag;
		}

		if (!is_null($this->_replyToAddress)) {
			$data['ReplyTo'] = is_null($this->_replyToName) ? $this->_replyToAddress : "{$this->_replyToName} <{$this->_replyToAddress}>";
		}

		return $data;
	}

	/**
	* If a number is 200-299
	*/
	private static function _isTwoHundred($value)
	{
		return intval($value / 100) == 2;
	}

	/**
	* Defines a constant, if it isn't defined
	*/
	private function _default($name, $default)
	{
		if (!defined($name)) {
			define($name, $default);
		}
	}

	/**
	* Validates an e-mailadress
	*/
	private function _validateAddress($email)
	{
		return \jmvc\classes\Valid::email($email);
	}
}
