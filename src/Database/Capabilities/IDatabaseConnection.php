<?php

namespace Friendica\Database\Capabilities;

use Psr\Log\LoggerInterface;

interface IDatabaseConnection
{
	/**
	 * @return IDatabaseError|IDatabaseResult
	 */
	public function execute(string $sql, ...$arguments);
	/**
	 * Replaces the ? placeholders with the parameters in the $args array
	 *
	 * @param string $sql       SQL query
	 * @param array  $arguments The parameters that are to replace the ? placeholders
	 *
	 * @return string The replaced SQL query
	 */
	public function replaceParameters(string $sql, array $arguments): string;

	public function escape(string $parameter): string;

	public function reconnect(): bool;

	public function connect(): bool;

	public function disconnect();

	public function lastInsertId(): int;

	public function getDriver(): string;
	public function databaseName(): string;
	public function serverInfo(): string;
	public function isConnected(): bool;
	public function connected(): bool;
	public function inTransaction(): bool;
	public function transaction(): bool;
	public function commit(): bool;
	public function rollback(): bool;
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
	public function fetchFirst(string $sql);
}
