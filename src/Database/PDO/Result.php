<?php

namespace Friendica\Database\PDO;

use Friendica\Database\AbstractDBResult;
use Friendica\Database\Capabilities\IDatabaseResult;
use PDO;
use PDOStatement;

class Result extends AbstractDBResult implements IDatabaseResult
{
	/** @var PDOStatement */
	protected $statement;
	/** @var int */
	protected $affectedRows;

	public function __construct(PDOStatement $statement)
	{
		$this->statement    = $statement;
		$this->affectedRows = $statement->rowCount();
	}

	public function getColumnCount(): int
	{
		return $this->statement->columnCount();
	}

	public function getAffectedRows(): int
	{
		return $this->affectedRows;
	}

	public function fetch(): array
	{
		return $this->statement->fetch(PDO::FETCH_ASSOC);
	}

	public function close(): bool
	{
		return $this->statement->closeCursor();
	}
}
