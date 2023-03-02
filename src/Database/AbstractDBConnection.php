<?php

namespace Friendica\Database;

use Friendica\Core\Config\Model\ReadOnlyFileConfig;
use Friendica\Core\System;
use Friendica\Database\Capabilities\IDatabaseConnection;
use Friendica\Database\Capabilities\IDatabaseError;
use Psr\Log\LoggerInterface;

abstract class AbstractDBConnection implements IDatabaseConnection
{
	/** @var bool */
	protected $connected = false;
	/** @var string  */
	protected $serverInfo = '';
	/** @var ReadOnlyFileConfig */
	protected $config;
	protected $inTransaction = false;

	abstract protected function executeInternal(string $sql, array $arguments = [], bool $withCallstack = false);

	public function __construct(ReadOnlyFileConfig $config)
	{
		$this->config = $config;
	}

	public function isConnected(): bool
	{
		return $this->connected;
	}

	public function inTransaction(): bool
	{
		return $this->inTransaction;
	}

	public function reconnect(): bool
	{
		$this->connect();
		return $this->connect();
	}

	public function databaseName(): string
	{
		$result = $this->execute( "SELECT DATABASE() AS `db`");
		$data   = $result->toArray();
		return $data[0]['db'] ?? '';
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
	protected function anyValueFallback(string $sql): string
	{
		$server_info = $this->serverInfo();
		if (version_compare($server_info, '5.7.5', '<') ||
			(stripos($server_info, 'MariaDB') !== false)) {
			$sql = str_ireplace('ANY_VALUE(', 'MIN(', $sql);
		}
		return $sql;
	}

	public function replaceParameters(string $sql, array $arguments): string
	{
		$offset = 0;
		foreach ($arguments as $param => $value) {
			if (is_int($arguments[$param]) || is_float($arguments[$param]) || is_bool($arguments[$param])) {
				$replace = intval($arguments[$param]);
			} elseif (is_null($arguments[$param])) {
				$replace = 'NULL';
			} else {
				$replace = "'" . $this->escape($arguments[$param]) . "'";
			}

			$pos = strpos($sql, '?', $offset);
			if ($pos !== false) {
				$sql = substr_replace($sql, $replace, $pos, 1);
			}
			$offset = $pos + strlen($replace);
		}
		return $sql;
	}

	/**
	 * Fetches the first row
	 *
	 * Please use DBA::selectFirst or DBA::exists whenever this is possible.
	 *
	 * Fetches the first row
	 *
	 * @param string $sql SQL statement
	 *
	 * @return array|bool first row of query or false on failure
	 * @throws \Exception
	 */
	public function fetchFirst(string $sql)
	{
		$params = DBA::getParam(func_get_args());

		$stmt = $this->execute($sql, $params);

		if (is_bool($stmt)) {
			$retval = $stmt;
		} elseif ($stmt instanceof IDatabaseResult) {
			$retval = $stmt->fetch();
			$stmt->close();
		} else {
			$retval = false;
		}

		return $retval;
	}

	public function setTransaction(bool $inTransaction = false)
	{
		$this->inTransaction = $inTransaction;
	}

	public function commit(): bool
	{
		if (!$this->performCommit()) {
			return false;
		}
		$this->inTransaction = false;
		return true;
	}

	public function execute(string $sql, array $arguments = [], bool $withCallstack = false, bool $disableErrorHandling = false)
	{
		$result = $this->executeInternal($sql, $arguments, $withCallstack);

		if ($result instanceof IDatabaseError) {
			$logger->error('DB Error', [
				'code'      => $result->getErrorNumber(),
				'error'     => $result->getError(),
				'callstack' => System::callstack(8),
				'params'    => $this->replaceParameters($sql, $arguments),
			]);

			if (!$result->getErrorNumber() == 2006) {
				$this->checkReconnect($logger, $result, $sql, $arguments);
			}
		}

		// try again
		$result = $this->executeInternal($sql, $arguments, $withCallstack);

		if ($result instanceof IDatabaseError) {
			$logger->error('DB Error', [
				'code'      => $result->getErrorNumber(),
				'error'     => $result->getError(),
				'callstack' => System::callstack(8),
				'params'    => $this->replaceParameters($sql, $arguments),
			]);

			if (!$result->getErrorNumber() == 2006) {
				$this->checkReconnect($logger, $result, $sql, $arguments, true);
			}
		}

		return $result;
	}

	protected function checkReconnect(LoggerInterface $logger, IDatabaseError $error, string $sql, array $arguments, bool $retry = false)
	{
		if (!$this->reconnect()) {
			if ($retry) {
				$logger->notice('Giving up retrial because of database error', [
					'code'  => $error->getErrorNumber(),
					'error' => $error->getError(),
				]);
			} else {
				$logger->notice('Couldn\'t reconnect after database error', [
					'code'  => $error->getErrorNumber(),
					'error' => $error->getError(),
				]);
			}

			throw new DatabaseException($error->getError(), $error->getErrorNumber(), $this->replaceParameters($sql, $arguments));
		} else {
			$logger->notice('Reconnected after database error', [
				'code'  => $error->getErrorNumber(),
				'error' => $error->getError(),
			]);
		}
	}
}
