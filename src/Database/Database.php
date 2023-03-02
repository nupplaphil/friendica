<?php
/**
 * @copyright Copyright (C) 2010-2023, the Friendica project
 *
 * @license GNU AGPL version 3 or any later version
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

namespace Friendica\Database;

use Friendica\Core\Config\Capability\IManageConfigValues;
use Friendica\Core\System;
use Friendica\Database\Definition\DbaDefinition;
use Friendica\Database\Definition\ViewDefinition;
use Friendica\Network\HTTPException\ServiceUnavailableException;
use Friendica\Util\DateTimeFormat;
use Friendica\Util\Profiler;
use InvalidArgumentException;
use mysqli;
use mysqli_result;
use mysqli_stmt;
use PDO;
use PDOException;
use PDOStatement;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * This class is for the low level database stuff that does driver specific things.
 */
class Database
{
	const PDO    = 'pdo';
	const MYSQLI = 'mysqli';

	const INSERT_DEFAULT = 0;
	const INSERT_UPDATE  = 1;
	const INSERT_IGNORE  = 2;

	protected $connected = false;

	/**
	 * @var IManageConfigValues
	 */
	protected $config = null;
	/**
	 * @var Profiler
	 */
	protected $profiler = null;
	/**
	 * @var LoggerInterface
	 */
	protected $logger = null;
	/** @var PDO|mysqli */
	protected $connection;
	protected $driver = '';
	protected $pdo_emulate_prepares = false;
	private $error = '';
	private $errorno = 0;
	private $affected_rows = 0;
	protected $in_transaction = false;
	protected $in_retrial = false;
	protected $testmode = false;
	private $relation = [];
	/** @var DbaDefinition */
	protected $dbaDefinition;
	/** @var ViewDefinition */
	protected $viewDefinition;

	public function __construct(IManageConfigValues $config, DbaDefinition $dbaDefinition, ViewDefinition $viewDefinition)
	{
		// We are storing these values for being able to perform a reconnect
		$this->config         = $config;
		$this->dbaDefinition  = $dbaDefinition;
		$this->viewDefinition = $viewDefinition;

		// Use dummy values - necessary for the first factory call of the logger itself
		$this->logger = new NullLogger();
		$this->profiler = new Profiler($config);

		$this->connect();
	}

	/**
	 * @param IManageConfigValues $config
	 * @param Profiler            $profiler
	 * @param LoggerInterface     $logger
	 *
	 * @return void
	 *
	 * @todo Make this method obsolet - use a clean pattern instead ...
	 */
	public function setDependency(IManageConfigValues $config, Profiler $profiler, LoggerInterface $logger)
	{
		$this->logger   = $logger;
		$this->profiler = $profiler;
		$this->config   = $config;
	}

	/**
	 * Tries to connect to database
	 *
	 * @return bool Success
	 */
	public function connect(): bool
	{
		if (!is_null($this->connection) && $this->connected()) {
			return $this->connected;
		}

		// Reset connected state
		$this->connected = false;

		$port       = 0;
		$serveraddr = trim($this->config->get('database', 'hostname') ?? '');
		$serverdata = explode(':', $serveraddr);
		$host       = trim($serverdata[0]);
		if (count($serverdata) > 1) {
			$port = trim($serverdata[1]);
		}

		if (trim($this->config->get('database', 'port') ?? 0)) {
			$port = trim($this->config->get('database', 'port') ?? 0);
		}

		$user     = trim($this->config->get('database', 'username'));
		$pass     = trim($this->config->get('database', 'password'));
		$database = trim($this->config->get('database', 'database'));
		$charset  = trim($this->config->get('database', 'charset'));
		$socket   = trim($this->config->get('database', 'socket'));

		if (!$host && !$socket || !$user) {
			return false;
		}

		$persistent = (bool)$this->config->get('database', 'persistent');

		$this->pdo_emulate_prepares = (bool)$this->config->get('database', 'pdo_emulate_prepares');

		if (!$this->config->get('database', 'disable_pdo') && class_exists('\PDO') && in_array('mysql', PDO::getAvailableDrivers())) {
			$this->driver = self::PDO;
			if ($socket) {
				$connect = 'mysql:unix_socket=' . $socket;
			} else {
				$connect = 'mysql:host=' . $host;
				if ($port > 0) {
					$connect .= ';port=' . $port;
				}
			}

			if ($charset) {
				$connect .= ';charset=' . $charset;
			}

			$connect .= ';dbname=' . $database;

			try {
				$this->connection = @new PDO($connect, $user, $pass, [PDO::ATTR_PERSISTENT => $persistent]);
				$this->connection->setAttribute(PDO::ATTR_EMULATE_PREPARES, $this->pdo_emulate_prepares);
				$this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
				$this->connected = true;
			} catch (PDOException $e) {
				$this->connected = false;
			}
		}

		if (!$this->connected && class_exists('\mysqli')) {
			$this->driver = self::MYSQLI;

			if ($socket) {
				$this->connection = @new mysqli(null, $user, $pass, $database, null, $socket);
			} elseif ($port > 0) {
				$this->connection = @new mysqli($host, $user, $pass, $database, $port);
			} else {
				$this->connection = @new mysqli($host, $user, $pass, $database);
			}

			if (!mysqli_connect_errno()) {
				$this->connected = true;

				if ($charset) {
					$this->connection->set_charset($charset);
				}
			}
		}

		// No suitable SQL driver was found.
		if (!$this->connected) {
			$this->driver     = '';
			$this->connection = null;
		}

		return $this->connected;
	}

	public function setTestmode(bool $test)
	{
		$this->testmode = $test;
	}

	/**
	 * Analyze a database query and log this if some conditions are met.
	 *
	 * @param string $query The database query that will be analyzed
	 * @return void
	 * @throws \Exception
	 */
	private function logIndex(string $query)
	{

		if (!$this->config->get('system', 'db_log_index')) {
			return;
		}

		// Don't explain an explain statement
		if (strtolower(substr($query, 0, 7)) == "explain") {
			return;
		}

		// Only do the explain on "select", "update" and "delete"
		if (!in_array(strtolower(substr($query, 0, 6)), ["select", "update", "delete"])) {
			return;
		}

		$r = $this->p("EXPLAIN " . $query);
		if (!$this->isResult($r)) {
			return;
		}

		$watchlist = explode(',', $this->config->get('system', 'db_log_index_watch'));
		$denylist  = explode(',', $this->config->get('system', 'db_log_index_denylist'));

		while ($row = $this->fetch($r)) {
			if ((intval($this->config->get('system', 'db_loglimit_index')) > 0)) {
				$log = (in_array($row['key'], $watchlist) &&
					($row['rows'] >= intval($this->config->get('system', 'db_loglimit_index'))));
			} else {
				$log = false;
			}

			if ((intval($this->config->get('system', 'db_loglimit_index_high')) > 0) && ($row['rows'] >= intval($this->config->get('system', 'db_loglimit_index_high')))) {
				$log = true;
			}

			if (in_array($row['key'], $denylist) || ($row['key'] == "")) {
				$log = false;
			}

			if ($log) {
				$backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
				@file_put_contents(
					$this->config->get('system', 'db_log_index'),
					DateTimeFormat::utcNow() . "\t" .
					$row['key'] . "\t" . $row['rows'] . "\t" . $row['Extra'] . "\t" .
					basename($backtrace[1]["file"]) . "\t" .
					$backtrace[1]["line"] . "\t" . $backtrace[2]["function"] . "\t" .
					substr($query, 0, 4000) . "\n",
					FILE_APPEND
				);
			}
		}
	}

	/**
	 * Removes every not allowlisted character from the identifier string
	 *
	 * @param string $identifier
	 * @return string sanitized identifier
	 * @throws \Exception
	 */
	private function sanitizeIdentifier(string $identifier): string
	{
		return preg_replace('/[^A-Za-z0-9_\-]+/', '', $identifier);
	}

	/**
	 * Replaces ANY_VALUE() function by MIN() function,
	 * if the database server does not support ANY_VALUE().
	 *
	 * Considerations for Standard SQL, or MySQL with ONLY_FULL_GROUP_BY (default since 5.7.5).
	 * ANY_VALUE() is available from MySQL 5.7.5 https://dev.mysql.com/doc/refman/5.7/en/miscellaneous-functions.html
	 * A standard fall-back is to use MIN().
	 *
	 * @param string $sql An SQL string without the values
	 *
	 * @return string The input SQL string modified if necessary.
	 */
	public function anyValueFallback(string $sql): string
	{
		$server_info = $this->serverInfo();
		if (version_compare($server_info, '5.7.5', '<') ||
			(stripos($server_info, 'MariaDB') !== false)) {
			$sql = str_ireplace('ANY_VALUE(', 'MIN(', $sql);
		}
		return $sql;
	}

	/**
	 * Executes a prepared statement that returns data
	 *
	 * @usage Example: $r = p("SELECT * FROM `post` WHERE `guid` = ?", $guid);
	 *
	 * Please only use it with complicated queries.
	 * For all regular queries please use DBA::select or DBA::exists
	 *
	 * @param string $sql SQL statement
	 *
	 * @return bool|object statement object or result object
	 * @throws \Exception
	 */
	public function p(string $sql)
	{

		$this->profiler->startRecording('database');
		$stamp1 = microtime(true);

		$params = DBA::getParam(func_get_args());

		// Renumber the array keys to be sure that they fit
		$i    = 0;
		$args = [];
		foreach ($params as $param) {
			// Avoid problems with some MySQL servers and boolean values. See issue #3645
			if (is_bool($param)) {
				$param = (int)$param;
			}
			$args[++$i] = $param;
		}

		if (!$this->connected) {
			return false;
		}

		if ((substr_count($sql, '?') != count($args)) && (count($args) > 0)) {
			// Question: Should we continue or stop the query here?
			$this->logger->warning('Query parameters mismatch.', ['query' => $sql, 'args' => $args, 'callstack' => System::callstack()]);
		}

		$sql = DBA::cleanQuery($sql);
		$sql = $this->anyValueFallback($sql);

		$orig_sql = $sql;

		if ($this->config->get('system', 'db_callstack') !== null) {
			$sql = "/*" . System::callstack() . " */ " . $sql;
		}

		$is_error            = false;
		$this->error         = '';
		$this->errorno       = 0;
		$this->affected_rows = 0;

		// We have to make some things different if this function is called from "e"
		$trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);

		if (isset($trace[1])) {
			$called_from = $trace[1];
		} else {
			// We use just something that is defined to avoid warnings
			$called_from = $trace[0];
		}
		// We are having an own error logging in the function "e"
		$called_from_e = ($called_from['function'] == 'e');

		if (!isset($this->connection)) {
			throw new ServiceUnavailableException('The Connection is empty, although connected is set true.');
		}

		switch ($this->driver) {
			case self::PDO:
				// If there are no arguments we use "query"
				if (count($args) == 0) {
					if (!$retval = $this->connection->query($this->replaceParameters($sql, $args))) {
						$errorInfo     = $this->connection->errorInfo();
						$this->error   = (string)$errorInfo[2];
						$this->errorno = (int)$errorInfo[1];
						$retval        = false;
						$is_error      = true;
						break;
					}
					$this->affected_rows = $retval->rowCount();
					break;
				}

				/** @var $stmt mysqli_stmt|PDOStatement */
				if (!$stmt = $this->connection->prepare($sql)) {
					$errorInfo     = $this->connection->errorInfo();
					$this->error   = (string)$errorInfo[2];
					$this->errorno = (int)$errorInfo[1];
					$retval        = false;
					$is_error      = true;
					break;
				}

				foreach (array_keys($args) as $param) {
					$data_type = PDO::PARAM_STR;
					if (is_int($args[$param])) {
						$data_type = PDO::PARAM_INT;
					} elseif ($args[$param] !== null) {
						$args[$param] = (string)$args[$param];
					}

					$stmt->bindParam($param, $args[$param], $data_type);
				}

				if (!$stmt->execute()) {
					$errorInfo     = $stmt->errorInfo();
					$this->error   = (string)$errorInfo[2];
					$this->errorno = (int)$errorInfo[1];
					$retval        = false;
					$is_error      = true;
				} else {
					$retval              = $stmt;
					$this->affected_rows = $retval->rowCount();
				}
				break;
			case self::MYSQLI:
				// There are SQL statements that cannot be executed with a prepared statement
				$parts           = explode(' ', $orig_sql);
				$command         = strtolower($parts[0]);
				$can_be_prepared = in_array($command, ['select', 'update', 'insert', 'delete']);

				// The fallback routine is called as well when there are no arguments
				if (!$can_be_prepared || (count($args) == 0)) {
					$retval = $this->connection->query($this->replaceParameters($sql, $args));
					if ($this->connection->errno) {
						$this->error   = (string)$this->connection->error;
						$this->errorno = (int)$this->connection->errno;
						$retval        = false;
						$is_error      = true;
					} else {
						if (isset($retval->num_rows)) {
							$this->affected_rows = $retval->num_rows;
						} else {
							$this->affected_rows = $this->connection->affected_rows;
						}
					}
					break;
				}

				$stmt = $this->connection->stmt_init();

				if (!$stmt->prepare($sql)) {
					$this->error   = (string)$stmt->error;
					$this->errorno = (int)$stmt->errno;
					$retval        = false;
					$is_error      = true;
					break;
				}

				$param_types = '';
				$values      = [];
				foreach (array_keys($args) as $param) {
					if (is_int($args[$param])) {
						$param_types .= 'i';
					} elseif (is_float($args[$param])) {
						$param_types .= 'd';
					} elseif (is_string($args[$param])) {
						$param_types .= 's';
					} elseif (is_object($args[$param]) && method_exists($args[$param], '__toString')) {
						$param_types  .= 's';
						$args[$param] = (string)$args[$param];
					} else {
						$param_types .= 'b';
					}
					$values[] = &$args[$param];
				}

				if (count($values) > 0) {
					array_unshift($values, $param_types);
					call_user_func_array([$stmt, 'bind_param'], $values);
				}

				if (!$stmt->execute()) {
					$this->error   = (string)$this->connection->error;
					$this->errorno = (int)$this->connection->errno;
					$retval        = false;
					$is_error      = true;
				} else {
					$stmt->store_result();
					$retval              = $stmt;
					$this->affected_rows = $retval->affected_rows;
				}
				break;
		}

		// See issue https://github.com/friendica/friendica/issues/8572
		// Ensure that we always get an error message on an error.
		if ($is_error && empty($this->errorno)) {
			$this->errorno = -1;
		}

		if ($is_error && empty($this->error)) {
			$this->error = 'Unknown database error';
		}

		// We are having an own error logging in the function "e"
		if (($this->errorno != 0) && !$called_from_e) {
			// We have to preserve the error code, somewhere in the logging it get lost
			$error   = $this->error;
			$errorno = $this->errorno;

			if ($this->testmode) {
				throw new DatabaseException($error, $errorno, $this->replaceParameters($sql, $args));
			}

			$this->logger->error('DB Error', [
				'code'      => $errorno,
				'error'     => $error,
				'callstack' => System::callstack(8),
				'params'    => $this->replaceParameters($sql, $args),
			]);

			// On a lost connection we try to reconnect - but only once.
			if ($errorno == 2006) {
				if ($this->in_retrial || !$this->reconnect()) {
					// It doesn't make sense to continue when the database connection was lost
					if ($this->in_retrial) {
						$this->logger->notice('Giving up retrial because of database error', [
							'code'  => $errorno,
							'error' => $error,
						]);
					} else {
						$this->logger->notice('Couldn\'t reconnect after database error', [
							'code'  => $errorno,
							'error' => $error,
						]);
					}
					exit(1);
				} else {
					// We try it again
					$this->logger->notice('Reconnected after database error', [
						'code'  => $errorno,
						'error' => $error,
					]);
					$this->in_retrial = true;
					$ret              = $this->p($sql, $args);
					$this->in_retrial = false;
					return $ret;
				}
			}

			$this->error   = (string)$error;
			$this->errorno = (int)$errorno;
		}

		$this->profiler->stopRecording();

		if ($this->config->get('system', 'db_log')) {
			$stamp2   = microtime(true);
			$duration = (float)($stamp2 - $stamp1);

			if (($duration > $this->config->get('system', 'db_loglimit'))) {
				$duration  = round($duration, 3);
				$backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

				@file_put_contents(
					$this->config->get('system', 'db_log'),
					DateTimeFormat::utcNow() . "\t" . $duration . "\t" .
					basename($backtrace[1]["file"]) . "\t" .
					$backtrace[1]["line"] . "\t" . $backtrace[2]["function"] . "\t" .
					substr($this->replaceParameters($sql, $args), 0, 4000) . "\n",
					FILE_APPEND
				);
			}
		}
		return $retval;
	}

	/**
	 * Executes a prepared statement like UPDATE or INSERT that doesn't return data
	 *
	 * Please use DBA::delete, DBA::insert, DBA::update, ... instead
	 *
	 * @param string $sql SQL statement
	 *
	 * @return boolean Was the query successfull? False is returned only if an error occurred
	 * @throws \Exception
	 */
	public function e(string $sql): bool
	{
		$retval = false;

		$this->profiler->startRecording('database_write');

		$params = DBA::getParam(func_get_args());

		// In a case of a deadlock we are repeating the query 20 times
		$timeout = 20;

		do {
			$stmt = $this->p($sql, $params);

			if (is_bool($stmt)) {
				$retval = $stmt;
			} elseif (is_object($stmt)) {
				$retval = true;
			} else {
				$retval = false;
			}

			$this->close($stmt);

		} while (($this->errorno == 1213) && (--$timeout > 0));

		if ($this->errorno != 0) {
			// We have to preserve the error code, somewhere in the logging it get lost
			$error   = $this->error;
			$errorno = $this->errorno;

			if ($this->testmode) {
				throw new DatabaseException($error, $errorno, $this->replaceParameters($sql, $params));
			}

			$this->logger->error('DB Error', [
				'code'      => $errorno,
				'error'     => $error,
				'callstack' => System::callstack(8),
				'params'    => $this->replaceParameters($sql, $params),
			]);

			// On a lost connection we simply quit.
			// A reconnect like in $this->p could be dangerous with modifications
			if ($errorno == 2006) {
				$this->logger->notice('Giving up because of database error', [
					'code'  => $errorno,
					'error' => $error,
				]);
				exit(1);
			}

			$this->error   = $error;
			$this->errorno = $errorno;
		}

		$this->profiler->stopRecording();

		return $retval;
	}

	/**
	 * Cast field types according to the table definition
	 *
	 * @param string $table
	 * @param array  $fields
	 * @return array casted fields
	 */
	public function castFields(string $table, array $fields): array
	{
		// When there is no data, we don't need to do something
		if (empty($fields)) {
			return $fields;
		}

		// We only need to cast fields with PDO
		if ($this->driver != self::PDO) {
			return $fields;
		}

		// We only need to cast when emulating the prepares
		if (!$this->connection->getAttribute(PDO::ATTR_EMULATE_PREPARES)) {
			return $fields;
		}

		$types = [];

		$tables = $this->dbaDefinition->getAll();
		if (empty($tables[$table])) {
			// When a matching table wasn't found we check if it is a view
			$views = $this->viewDefinition->getAll();
			if (empty($views[$table])) {
				return $fields;
			}

			foreach (array_keys($fields) as $field) {
				if (!empty($views[$table]['fields'][$field])) {
					$viewdef = $views[$table]['fields'][$field];
					if (!empty($tables[$viewdef[0]]['fields'][$viewdef[1]]['type'])) {
						$types[$field] = $tables[$viewdef[0]]['fields'][$viewdef[1]]['type'];
					}
				}
			}
		} else {
			foreach ($tables[$table]['fields'] as $field => $definition) {
				$types[$field] = $definition['type'];
			}
		}

		foreach ($fields as $field => $content) {
			if (is_null($content) || empty($types[$field])) {
				continue;
			}

			if ((substr($types[$field], 0, 7) == 'tinyint') || (substr($types[$field], 0, 8) == 'smallint') ||
				(substr($types[$field], 0, 9) == 'mediumint') || (substr($types[$field], 0, 3) == 'int') ||
				(substr($types[$field], 0, 6) == 'bigint') || (substr($types[$field], 0, 7) == 'boolean')) {
				$fields[$field] = (int)$content;
			}
			if ((substr($types[$field], 0, 5) == 'float') || (substr($types[$field], 0, 6) == 'double')) {
				$fields[$field] = (float)$content;
			}
		}

		return $fields;
	}

	/**
	 * Return a list of database processes
	 *
	 * @return array
	 *      'list' => List of processes, separated in their different states
	 *      'amount' => Number of concurrent database processes
	 * @throws \Exception
	 */
	public function processlist(): array
	{
		$ret  = $this->p('SHOW PROCESSLIST');
		$data = $this->toArray($ret);

		$processes = 0;
		$states    = [];
		foreach ($data as $process) {
			$state = trim($process['State']);

			// Filter out all non blocking processes
			if (!in_array($state, ['', 'init', 'statistics', 'updating'])) {
				++$states[$state];
				++$processes;
			}
		}

		$statelist = '';
		foreach ($states as $state => $usage) {
			if ($statelist != '') {
				$statelist .= ', ';
			}
			$statelist .= $state . ': ' . $usage;
		}
		return (['list' => $statelist, 'amount' => $processes]);
	}

	/**
	 * Fetch a database variable
	 *
	 * @param string $name
	 * @return string|null content or null if inexistent
	 * @throws \Exception
	 */
	public function getVariable(string $name)
	{
		$result = $this->fetchFirst("SHOW GLOBAL VARIABLES WHERE `Variable_name` = ?", $name);
		return $result['Value'] ?? null;
	}

	/**
	 * Checks if $array is a filled array with at least one entry.
	 *
	 * @param mixed $array A filled array with at least one entry
	 * @return boolean Whether $array is a filled array or an object with rows
	 */
	public function isResult($array): bool
	{
		// It could be a return value from an update statement
		if (is_bool($array)) {
			return $array;
		}

		if (is_object($array)) {
			return $this->numRows($array) > 0;
		}

		return (is_array($array) && (count($array) > 0));
	}
}
