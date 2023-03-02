<?php

namespace Friendica\Database\Profiler;

use Friendica\Database\Capabilities\IDatabaseConnection;
use Friendica\Database\Capabilities\IDatabaseResult;
use Friendica\Util\Profiler;
use Psr\Log\LoggerInterface;

class ProfiledDBConnection implements IDatabaseConnection
{
	/** @var IDatabaseConnection */
	protected $connection;
	/** @var Profiler */
	protected $profiler;

	public function __construct(IDatabaseConnection $connection, Profiler $profiler)
	{
		$this->connection = $connection;
		$this->profiler   = $profiler;
	}

	public function execute(LoggerInterface $logger, string $sql, array $arguments = [], bool $withCallstack = false, bool $disableErrorHandling = false)
	{
		$this->profiler->startRecording('database');

		$result = $this->connection->execute($logger, $sql, $arguments, $withCallstack, $disableErrorHandling);
		if ($result instanceof IDatabaseResult) {
			$result = new ProfiledDBResult($result, $this->profiler);
		}

		$this->profiler->stopRecording();

		return $result;
	}

	public function replaceParameters(string $sql, array $arguments): string
	{
		return $this->connection->replaceParameters($sql, $arguments);
	}

	public function escape(string $parameter): string
	{
		return $this->connection->escape($parameter);
	}

	public function reconnect(): bool
	{
		return $this->connection->reconnect();
	}

	public function connect(): bool
	{
		return $this->connection->connect();
	}

	public function disconnect()
	{
		return $this->connection->disconnect();
	}

	public function getDriver(): string
	{
		return $this->connection->getDriver();
	}

	public function databaseName(LoggerInterface $logger = null): string
	{
		return $this->connection->databaseName($logger);
	}

	public function serverInfo(): string
	{
		return $this->connection->serverInfo();
	}

	public function isConnected(): bool
	{
		return $this->connection->isConnected();
	}

	public function connected(): bool
	{
		return $this->connection->connected();
	}
}
