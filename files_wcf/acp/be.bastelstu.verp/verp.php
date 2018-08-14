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

use \wcf\system\email\Email;
use \wcf\util\CryptoUtil;

if (PHP_SAPI !== 'cli') {
	exit(1);
}
try {
	require(__DIR__.'/../../global.php');	
}
catch (\Exception $e) {
	echo "450 Service Unavailable\r\n";
	exit;
}

try {
	\wcf\system\WCF::getSession()->delete();

	stream_set_timeout(STDIN, 15);
	echo "220 ".Email::getHost()." WoltLab Suite ready\r\n";
	if (!preg_match("/^LHLO (.+)\r?\n$/", fgets(STDIN), $matches)) {
		echo "450 wtf\r\n";
		exit(1);
	}
	echo "250 Hello ".$matches[1]."\r\n";
	if (!preg_match("/^MAIL FROM:<(.+)>\r?\n$/", fgets(STDIN), $matches)) {
		echo "450 wtf\r\n";
		exit(1);
	}
	echo "250 OK\r\n";
	if (!preg_match("/^RCPT TO:<(.+)>\r?\n$/", fgets(STDIN), $matches)) {
		echo "450 wtf\r\n";
		exit(1);
	}
	$rcpt = $matches[1];

	$regex = str_replace('\\$', '(\d+)_([0-9]+)_([0-9a-f]{8})_([0-9a-f]{64})', preg_quote(MAIL_VERP_FORMAT, '/'));
	if (!preg_match("/".$regex."/", $rcpt, $matches)) {
		echo "550 Invalid email\r\n";
		exit(1);
	}
	$userID = $matches[1];
	$nonce = $matches[2]."_".$matches[3];
	$signature = $matches[4];
	if (!CryptoUtil::secureCompare(CryptoUtil::getSignature($userID.'_'.$nonce), $matches[4])) {
		echo "550 Invalid email\r\n";
		exit(1);
	}
	
	echo "250 OK, userID $userID\r\n";
	if (!preg_match("/^DATA\r?\n$/", fgets(STDIN), $matches)) {
		echo "450 wtf\r\n";
		exit(1);
	}
	echo "354 OK\r\n";
	while (!preg_match("/^\.([^.].*)?\r?\n?$/", fgets(STDIN)));
	echo "250 OK, userID $userID\r\n";
	if (!preg_match("/^QUIT\r?\n$/", fgets(STDIN), $matches)) {
		echo "450 wtf\r\n";
		exit(1);
	}
}
catch (\Throwable $e) {
	echo "450 Service Unavailable";
	try {
		\wcf\functions\exception\logThrowable($e);
	}
	catch (\Throwable $e) {
		
	}
}
