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

namespace Friendica\Network\Fetch;

use Friendica\Network\IResponse;

/**
 * Interface for fetching results of URLs
 */
interface IFetch
{
	/**
	 * Curl wrapper
	 *
	 * If binary flag is true, return binary results.
	 * Set the cookiejar argument to a string (e.g. "/tmp/friendica-cookies.txt")
	 * to preserve cookies from one request to the next.
	 *
	 * @param string $url             URL to fetch
	 * @param bool   $binary          default false
	 *                                TRUE if asked to return binary results (file download)
	 * @param int    $timeout         Timeout in seconds, default system config value or 60 seconds
	 * @param string $accept_content  supply Accept: header with 'accept_content' as the value
	 * @param string $cookiejar       Path to cookie jar file
	 *
	 * @return string The fetched content
	 */
	public function url(string $url, bool $binary = false, int $timeout = 0, string $accept_content = '', string $cookiejar = '');

	/**
	 * Curl wrapper with array of return values.
	 *
	 * Inner workings and parameters are the same as @ref fetchUrl but returns an array with
	 * all the information collected during the fetch.
	 *
	 * @param string $url             URL to fetch
	 * @param bool   $binary          default false
	 *                                TRUE if asked to return binary results (file download)
	 * @param int    $timeout         Timeout in seconds, default system config value or 60 seconds
	 * @param string $accept_content  supply Accept: header with 'accept_content' as the value
	 * @param string $cookiejar       Path to cookie jar file
	 *
	 * @return IResponse With all relevant information, 'body' contains the actual fetched content.
	 */
	public function urlFull(string $url, bool $binary = false, int $timeout = 0, string $accept_content = '', string $cookiejar = '');

	/**
	 * fetches an URL.
	 *
	 * @param string $url        URL to fetch
	 * @param bool   $binary     default false
	 *                           TRUE if asked to return binary results (file download)
	 * @param array  $opts       (optional parameters) assoziative array with:
	 *                           'accept_content' => supply Accept: header with 'accept_content' as the value
	 *                           'timeout' => int Timeout in seconds, default system config value or 60 seconds
	 *                           'http_auth' => username:password
	 *                           'novalidate' => do not validate SSL certs, default is to validate using our CA list
	 *                           'nobody' => only return the header
	 *                           'cookiejar' => path to cookie jar file
	 *                           'header' => header array
	 *
	 * @return IResponse
	 */
	public function curl(string $url, bool $binary = false, array $opts = []);
}
