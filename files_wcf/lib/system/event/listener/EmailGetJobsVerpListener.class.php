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

namespace wcf\system\event\listener;

/**
 * Implements Variable envelope return path.
 */
class EmailGetJobsVerpListener implements \wcf\system\event\listener\IParameterizedEventListener {
	/**
	 * @see	\wcf\system\event\listener\IParameterizedEventListener::execute()
	 */
	public function execute($eventObj, $className, $eventName, array &$parameters) {
		if (!MAIL_VERP_FORMAT) return;

		$sender = $parameters['sender']->getAddress();

		// Only mangle MAIL_FROM_ADDRESS
		if ($sender !== MAIL_FROM_ADDRESS) return;

		$recipient = $parameters['recipient']['mailbox'];

		// Only mangle mails to registered users
		if (!($recipient instanceof \wcf\system\email\UserMailbox)) return;

		$userID = $recipient->getUser()->userID;
		$email = $recipient->getUser()->email;

		try {
			$payload = $userID.'_'.substr(hash('sha256', $email), 0, 8).'_'.TIME_NOW.'_'.bin2hex(\wcf\util\CryptoUtil::randomBytes(4));
			$signature = substr(\wcf\util\CryptoUtil::getSignature($payload), 0, 32);
			$identifier = $signature.'_'.$payload;
			
			$address = str_replace('$', $identifier, MAIL_VERP_FORMAT);
			$parameters['sender'] = new \wcf\system\email\Mailbox($address);
		}
		catch (\Exception $e) {
			\wcf\functions\exception\logThrowable($e);
		}
		catch (\Throwable $e) {
			\wcf\functions\exception\logThrowable($e);
		}
	}
}
