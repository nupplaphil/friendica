<?php

namespace Friendica\Database;

use Friendica\Database\Capabilities\IDatabaseConnection;
use Friendica\Database\Capabilities\IDatabaseResult;

class FullDatabase
{
	/** @var IDatabaseConnection */
	protected $connection;

	public function __construct(IDatabaseConnection $connection)
	{
		$this->connection = $connection;
	}

	/**
	 * Select rows from a table and fills an array with the data
	 *
	 * @param string $table     Table name in format [schema.]table
	 * @param array  $fields    Array of selected fields, empty for all
	 * @param array  $condition Array of fields for condition
	 * @param array  $params    Array of several parameters
	 * @return array Data array
	 * @throws \Exception
	 * @see   self::select
	 */
	public function selectToArray(string $table, array $fields = [], array $condition = [], array $params = []): array
	{
		$result = $this->select($table, $fields, $condition, $params);
		if ($result instanceof IDatabaseResult) {
			return $result->toArray();
		}

		return [];
	}

	/**
	 * Retrieve a single record from a table and returns it in an associative array
	 *
	 * @param string $table     Table name in format [schema.]table
	 * @param array  $fields    Array of selected fields, empty for all
	 * @param array  $condition Array of fields for condition
	 * @param array  $params    Array of several parameters
	 *
	 * @return bool|array
	 * @throws \Exception
	 * @see   $this->select
	 */
	public function selectFirst(string $table, array $fields = [], array $condition = [], array $params = [])
	{
		$params['limit'] = 1;
		$result          = $this->select($table, $fields, $condition, $params);

		if (is_bool($result)) {
			return $result;
		} else {
			$row = $result->fetch();
			$result->close();
			return $row;
		}
	}

	/**
	 * Select rows from a table
	 *
	 *
	 * Example:
	 * $table = 'post';
	 * or:
	 * $table = ['schema' => 'table'];
	 * @see DBA::buildTableString()
	 *
	 * $fields = ['id', 'uri', 'uid', 'network'];
	 *
	 * $condition = ['uid' => 1, 'network' => 'dspr', 'blocked' => true];
	 * or:
	 * $condition = ['`uid` = ? AND `network` IN (?, ?)', 1, 'dfrn', 'dspr'];
	 * @see DBA::buildCondition()
	 *
	 * $params = ['order' => ['id', 'received' => true, 'created' => 'ASC'), 'limit' => 10];
	 * @see DBA::buildParameter()
	 *
	 * $data = DBA::select($table, $fields, $condition, $params);
	 *
	 * @param string $table     Table name in format [schema.]table
	 * @param array  $fields    Array of selected fields, empty for all
	 * @param array  $condition Array of fields for condition
	 * @param array  $params    Array of several parameters
	 * @return bool|IDatabaseResult
	 * @throws \Exception
	 */
	public function select(string $table, array $fields = [], array $condition = [], array $params = [])
	{
		if (empty($table)) {
			return false;
		}

		if (count($fields) > 0) {
			$fields        = DatabaseUtils::escapeFields($fields, $params);
			$select_string = implode(', ', $fields);
		} else {
			$select_string = '*';
		}

		$table_string = DatabaseUtils::buildTableString([$table]);

		$condition_string = DatabaseUtils::buildCondition($condition);

		$param_string = DatabaseUtils::buildParameter($params);

		$sql = "SELECT " . $select_string . " FROM " . $table_string . $condition_string . $param_string;

		$result = $this->connection->execute($sql, $condition);

		if (($this->driver == self::PDO) && !empty($result) && is_string($table)) {
			$result->table = $table;
		}

		return $result;
	}

	/**
	 * Insert a row into a table. Field value objects will be cast as string.
	 *
	 * @param string $table          Table name in format [schema.]table
	 * @param array  $param          parameter array
	 * @param int    $duplicate_mode What to do on a duplicated entry
	 *
	 * @return int|false the inserted ID or false if not inserted
	 * @throws \Exception
	 */
	public function insert(string $table, array $param, int $duplicate_mode = self::INSERT_DEFAULT)
	{
		if (empty($table) || empty($param)) {
			return false;
		}

		$param = $this->castFields($table, $param);

		$table_string = DatabaseUtils::buildTableString([$table]);

		$fields_string = implode(', ', array_map([DatabaseUtils::class, 'quoteIdentifier'], array_keys($param)));

		$values_string = substr(str_repeat("?, ", count($param)), 0, -2);

		$sql = "INSERT ";

		if ($duplicate_mode == self::INSERT_IGNORE) {
			$sql .= "IGNORE ";
		}

		$sql .= "INTO " . $table_string . " (" . $fields_string . ") VALUES (" . $values_string . ")";

		if ($duplicate_mode == self::INSERT_UPDATE) {
			$fields_string = implode(' = ?, ', array_map([DBA::class, 'quoteIdentifier'], array_keys($param)));

			$sql .= " ON DUPLICATE KEY UPDATE " . $fields_string . " = ?";

			$values = array_values($param);
			$param  = array_merge_recursive($values, $values);
		}

		$result = $this->BLA($sql, $param);
		if (!$result || ($duplicate_mode != self::INSERT_IGNORE)) {
			return $result;
		}

		return $result->getAffectedRows() > 0 ? $this->connection->lastInsertId() : false;
	}

	/**
	 * Check if data exists
	 *
	 * @param string $table     Table name in format [schema.]table
	 * @param array  $condition Array of fields for condition
	 *
	 * @return boolean Are there rows for that condition?
	 * @throws \Exception
	 * @todo Please unwrap the DBStructure::existsTable() call so this method has one behavior only: checking existence on records
	 */
	public function exists(string $table, array $condition): bool
	{
		if (empty($table)) {
			return false;
		}

		$fields = [];

		if (empty($condition)) {
			return DBStructure::existsTable($table);
		}

		reset($condition);
		$first_key = key($condition);
		if (!is_int($first_key)) {
			$fields = [$first_key];
		}

		$stmt = $this->select($table, $fields, $condition, ['limit' => 1]);

		if (is_bool($stmt)) {
			$retval = $stmt;
		} elseif ($stmt instanceof IDatabaseResult) {
			$retval = ($stmt->getAffectedRows() > 0);
			$stmt->close();
		} else  {
			$retval = false;
		}

		return $retval;
	}


	/**
	 * Delete a row from a table
	 *
	 * @param string $table      Table name
	 * @param array  $conditions Field condition(s)
	 *
	 * @return boolean was the delete successful?
	 * @throws \Exception
	 */
	public function delete(string $table, array $conditions): bool
	{
		if (empty($table) || empty($conditions)) {
			return false;
		}

		$table_string = DatabaseUtils::buildTableString([$table]);

		$condition_string = DatabaseUtils::buildCondition($conditions);

		$sql = "DELETE FROM " . $table_string . " " . $condition_string;
		return $this->connection->BLA($sql, $conditions);
	}


	/**
	 * Inserts a row with the provided data in the provided table.
	 * If the data corresponds to an existing row through a UNIQUE or PRIMARY index constraints, it updates the row instead.
	 *
	 * @param string $table Table name in format [schema.]table
	 * @param array  $param parameter array
	 * @return boolean was the insert successful?
	 * @throws \Exception
	 */
	public function replace(string $table, array $param): bool
	{
		if (empty($table) || empty($param)) {
			return false;
		}

		$param = $this->castFields($table, $param);

		$table_string = DatabaseUtils::buildTableString([$table]);

		$fields_string = implode(', ', array_map([DBA::class, 'quoteIdentifier'], array_keys($param)));

		$values_string = substr(str_repeat("?, ", count($param)), 0, -2);

		$sql = "REPLACE " . $table_string . " (" . $fields_string . ") VALUES (" . $values_string . ")";

		return $this->BLA($sql, $param);
	}


	/**
	 * Counts the rows from a table satisfying the provided condition
	 *
	 * @param string $table     Table name in format [schema.]table
	 * @param array  $condition Array of fields for condition
	 * @param array  $params    Array of several parameters
	 *
	 * @return int Count of rows
	 *
	 * Example:
	 * $table = "post";
	 *
	 * $condition = ["uid" => 1, "network" => 'dspr'];
	 * or:
	 * $condition = ["`uid` = ? AND `network` IN (?, ?)", 1, 'dfrn', 'dspr'];
	 *
	 * $count = DBA::count($table, $condition);
	 * @throws \Exception
	 */
	public function count(string $table, array $condition = [], array $params = []): int
	{
		if (empty($table)) {
			throw new \InvalidArgumentException('Parameter "table" cannot be empty.');
		}

		$table_string = DatabaseUtils::buildTableString([$table]);

		$condition_string = DatabaseUtils::buildCondition($condition);

		if (empty($params['expression'])) {
			$expression = '*';
		} elseif (!empty($params['distinct'])) {
			$expression = "DISTINCT " . DatabaseUtils::quoteIdentifier($params['expression']);
		} else {
			$expression = DatabaseUtils::quoteIdentifier($params['expression']);
		}

		$sql = "SELECT COUNT(" . $expression . ") AS `count` FROM " . $table_string . $condition_string;

		$row = $this->connection->fetchFirst($sql, $condition);

		if (!isset($row['count'])) {
			return 0;
		} else {
			return (int)$row['count'];
		}
	}

	/**
	 * Updates rows in the database. Field value objects will be cast as string.
	 *
	 * When $old_fields is set to an array,
	 * the system will only do an update if the fields in that array changed.
	 *
	 * Attention:
	 * Only the values in $old_fields are compared.
	 * This is an intentional behaviour.
	 *
	 * Example:
	 * We include the timestamp field in $fields but not in $old_fields.
	 * Then the row will only get the new timestamp when the other fields had changed.
	 *
	 * When $old_fields is set to a boolean value the system will do this compare itself.
	 * When $old_fields is set to "true" the system will do an insert if the row doesn't exists.
	 *
	 * Attention:
	 * Only set $old_fields to a boolean value when you are sure that you will update a single row.
	 * When you set $old_fields to "true" then $fields must contain all relevant fields!
	 *
	 * @param string        $table      Table name in format [schema.]table
	 * @param array         $fields     contains the fields that are updated
	 * @param array         $condition  condition array with the key values
	 * @param array|boolean $old_fields array with the old field values that are about to be replaced (true = update on duplicate, false = don't update identical fields)
	 * @param array         $params     Parameters: "ignore" If set to "true" then the update is done with the ignore parameter
	 *
	 * @return boolean was the update successfull?
	 * @throws \Exception
	 * @todo Implement "bool $update_on_duplicate" to avoid mixed type for $old_fields
	 */
	public function update(string $table, array $fields, array $condition, $old_fields = [], array $params = []): bool
	{
		if (empty($table) || empty($fields) || empty($condition)) {
			return false;
		}

		if (is_bool($old_fields)) {
			$do_insert = $old_fields;

			$old_fields = $this->selectFirst($table, [], $condition);

			if (is_bool($old_fields)) {
				if ($do_insert) {
					$values = array_merge($condition, $fields);
					return $this->replace($table, $values);
				}
				$old_fields = [];
			}
		}

		foreach ($old_fields as $fieldname => $content) {
			if (isset($fields[$fieldname]) && !is_null($content) && ($fields[$fieldname] == $content)) {
				unset($fields[$fieldname]);
			}
		}

		if (count($fields) == 0) {
			return true;
		}

		$fields = $this->castFields($table, $fields);

		$table_string = DBA::buildTableString([$table]);

		$condition_string = DBA::buildCondition($condition);

		if (!empty($params['ignore'])) {
			$ignore = 'IGNORE ';
		} else {
			$ignore = '';
		}

		$sql = "UPDATE " . $ignore . $table_string . " SET "
			   . implode(" = ?, ", array_map([DBA::class, 'quoteIdentifier'], array_keys($fields))) . " = ?"
			   . $condition_string;

		// Combines the updated fields parameter values with the condition parameter values
		$params = array_merge(array_values($fields), $condition);

		return $this->e($sql, $params);
	}
}
