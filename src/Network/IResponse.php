<?php
/**
 * @copyright Copyright (C) 2020, Friendica
 *
 * @license GNU APGL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 */

namespace Friendica\Network;

use Psr\Http\Message\ResponseInterface;

/**
 * This interface enhances the PSR standard Response interface with Friendica specific methods
 */
interface IResponse extends ResponseInterface
{
	/**
	 * Checks if the Response was successfully (not 400/500 code)
	 *
	 * @return bool
	 */
	public function isSuccess();

	/**
	 * True, if the call response was timed out
	 *
	 * @return bool
	 */
	public function isTimeout();

	/**
	 * Returns the URL of the original request
	 *
	 * @return string
	 */
	public function getUrl();

	/**
	 * Returns a Info array of the current Response
	 *
	 * @return array
	 */
	public function getInfo();

	/**
	 * Returns the Content-Type of the response
	 *
	 * @return string
	 */
	public function getContentType();

	/**
	 * Returns a error in case the call wasn't successful
	 *
	 * @return string
	 */
	public function getError();

	/**
	 * Returns the error number in case the call wasn't successful
	 *
	 * @return int
	 */
	public function getErrorNumber();
}
