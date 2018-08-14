<?php
/*
 * Copyright (c) 2017 - 2018, Tim Düsterhus
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace wcf\system\verp;

use \wcf\data\user\User;
use \wcf\data\user\UserAction;
use \wcf\system\email\Email;
use \wcf\system\email\Mailbox;
use \wcf\system\email\mime\PlainTextMimePart;
use \wcf\system\email\mime\AttachmentMimePart;
use \wcf\system\io\File;
use \wcf\system\language\LanguageFactory;
use \wcf\system\Regex;
use \wcf\util\CryptoUtil;
use \wcf\util\FileUtil;
use \wcf\util\StringUtil;

/**
 * Implements an LmtpService handling bounces.
 */
class LmtpService extends File {
	/**
	 * @inheritDoc
	 */
	public function __construct($fileNo) {
		parent::__construct("php://fd/".$fileNo, "rwb");
		stream_set_timeout($this->resource, 15);
	}

	public function send($code, $message) {
		$lines = explode("\n", StringUtil::unifyNewlines($message));
		$last = array_pop($lines);

		foreach ($lines as $line) {
			$this->write($code."-".$line."\r\n");
		}
		$this->write($code." ".$last."\r\n");
	}

	public function handle() {
		$filename = null;
		try {
			$this->send(220, Email::getHost()." WoltLab Suite ready");
			if (!preg_match("/^LHLO (\S+)\r?\n$/", $this->gets(), $matches)) {
				throw new \Exception("Expected LHLO", 503);
			}
			$this->send(250, "Hello ".$matches[1]);
			if (!preg_match("/^MAIL FROM:<([^>]*)>\r?\n$/", $this->gets(), $matches)) {
				throw new \Exception("Expected MAIL FROM", 503);
			}
			$this->send(250, "OK");
			if (!preg_match("/^RCPT TO:<([^>]*)>\r?\n$/", $this->gets(), $matches)) {
				throw new \Exception("Expected RCPT TO", 503);
			}
			$rcpt = $matches[1];

			if (MAIL_VERP_EXTRACT_REGEX) {
				$regex = new Regex(MAIL_VERP_EXTRACT_REGEX);
			}
			else {
				$regex = new Regex(str_replace('\\$', '(?P<signature>[0-9a-f]{32})_(?P<payload>(?P<userID>\d+)_(?P<email>[0-9a-f]{8})_(?P<timestamp>[0-9]+)_(?P<nonce>[0-9a-f]{8}))', preg_quote(MAIL_VERP_FORMAT)));
			}

			if (!$regex($rcpt)) {
				throw new \Exception("Invalid RCPT", 550);
			}

			$payload = $regex->getMatches();

			if (!CryptoUtil::secureCompare(substr(CryptoUtil::getSignature($payload['payload']), 0, 32), $payload['signature'])) {
				throw new \Exception("Invalid RCPT", 550);
			}

			$this->send(250, "OK");
			if (!preg_match("/^DATA\r?\n$/", $this->gets(), $matches)) {
				throw new \Exception("Expected DATA", 503);
			}
			$this->send(354, "Carry on");

			$file = new File($filename = FileUtil::getTemporaryFilename());
			while (!preg_match("/^\.([^.].*)?\r?\n?$/", $line = $this->gets())) {
				$file->write($line);
			}
			$file->close();

			$this->processUser($payload, $filename);

			$this->send(250, "Processed userID ".$payload['userID']);
			if (!preg_match("/^QUIT\r?\n$/", $this->gets(), $matches)) {
				throw new \Exception("Expected QUIT", 503);
			}
		}
		catch (\Throwable $e) {
			if (400 <= $e->getCode() && $e->getCode() < 600) {
				$this->send($e->getCode(), $e->getMessage());
			}
			else {
				$this->send(451, "Internal error");
			}
			throw $e;
		}
		finally {
			if ($filename) @unlink($filename);
		}
	}

	public function processUser($payload, $messageFile) {
		// Expired
		if ($payload['timestamp'] < TIME_NOW - 86400 * 5) return;

		$user = new User($payload['userID']);

		// User is deleted
		if (!$user->userID) return;
		// User already is disabled
		if ($user->activationCode) return;
		// Different email address
		if (substr(hash('sha256', $user->email), 0, 8) !== $payload['email']) return;

		(new UserAction([ $user ], 'disable'))->executeAction();

		$language = LanguageFactory::getInstance()->getDefaultLanguage();
		$email = new Email();
		$email->addRecipient(new Mailbox(MAIL_ADMIN_ADDRESS, null, $language));
		$email->setSubject("Handled bounce for user ".$payload['userID']);
		$email->setBody(new AttachmentMimePart($messageFile, TIME_NOW.".eml", "message/rfc822"));
		$email->send();
	}
}
