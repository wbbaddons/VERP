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

use \wcf\system\verp\LmtpService;

if (PHP_SAPI !== 'cli') {
	exit(1);
}
try {
	require(__DIR__.'/../../global.php');	
}
catch (\Exception $e) {
	$file = fopen("php://fd/3", "wb");
	fwrite($file, "451 Service unavailable\r\n");
	fclose($file);
	exit(1);
}

\wcf\system\WCF::getSession()->delete();

$lmtp = new LmtpService(3);
$lmtp->handle();
