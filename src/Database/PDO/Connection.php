<?php

namespace Friendica\Database\PDO;

use Friendica\Core\System;
use Friendica\Database\AbstractDBConnection;
use Friendica\Database\Capabilities\IDatabaseConnection;
use Friendica\Database\Capabilities\IDatabaseResult;
use PDO;

class Connection extends AbstractDBConnection implements IDatabaseConnection
{
	public const TYPE = 'PDO';

	/** @var PDO */
	protected $connection;

	public function getDriver(): string
	{
		return self::TYPE;
	}

	public function getConnection(): PDO
	{
		return $this->connection;
	}

	public function lastInsertId(): int
	{
		return (int) $this->connection->lastInsertId();
	}

	public function escape(string $parameter): string
	{
		if ($this->connected) {
			return substr(@$this->connection->quote($parameter), 1, -1);
		} else {
			return str_replace("'", "\\'", $parameter);
		}
	}

	public function transaction(): bool
	{
		if (!$this->performCommit()) {
			return false;
		}

		if (!$this->connection->inTransaction() && !$this->connection->beginTransaction()) {
			return false;
		}

		$this->inTransaction = true;
		return true;
	}

	protected function performCommit(): bool
	{
		if (!$this->connection->inTransaction()) {
			return true;
		}

		return $this->connection->commit();
	}

	public function rollback(): bool
	{
		if (!$this->connection->inTransaction()) {
			$ret = true;
		} else {
			$ret = $this->connection->rollBack();
		}

		$this->inTransaction = false;
		return $ret;
	}

	public function disconnect()
	{
		$this->connection = null;
		$this->connected   = false;
	}

	public function connected(): bool
	{
		if (!$this->connected) {
			return false;
		}

		$result = $this->execute( "Select 1");
		if ($result instanceof IDatabaseResult &&
			$row = $result->toArray() ?? []) {
			return $row[0]['1'] ?? false == '1';
		} else {
			return false;
		}
	}

	public function serverInfo(): string
	{
		if (empty($this->serverInfo)) {
			$this->serverInfo = $this->connection->getAttribute(PDO::ATTR_SERVER_INFO);
		}

		return $this->serverInfo;
	}

	protected function executeInternal(string $sql, array $arguments = [], bool $withCallstack = false)
	{
		if ($withCallstack) {
			$sql = "/*" . System::callstack() . " */ " . $sql;
		}

		if (count($arguments) === 0) {
			if ($statement = $this->connection->query($this->replaceParameters($sql, $arguments))) {
				return new Result($statement);
			} else {
				return Error::fromConnection($this->connection);
			}
		}

		if (!$statement = $this->connection->prepare($sql)) {
			return Error::fromConnection($this->connection);
		} else {
			foreach (array_keys($arguments) as $param) {
				$data_type = PDO::PARAM_STR;
				if (is_int($arguments[$param])) {
					$data_type = PDO::PARAM_INT;
				} else if ($arguments[$param] !== null) {
					$arguments[$param] = (string)$arguments[$param];
				}

				$statement->bindParam($param, $arguments[$param], $data_type);
			}

			if (!$statement->execute()) {
				return Error::fromStatement($statement);
			} else {
				return new Result($statement);
			}
		}
	}
}
