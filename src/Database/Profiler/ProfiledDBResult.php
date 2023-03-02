<?php

namespace Friendica\Database\Profiler;

use Friendica\Database\Capabilities\IDatabaseResult;
use Friendica\Util\Profiler;

class ProfiledDBResult implements IDatabaseResult
{
	/** @var IDatabaseResult */
	protected $result;
	/** @var Profiler */
	protected $profiler;

	public function __construct(IDatabaseResult $result, Profiler $profiler)
	{
		$this->result = $result;
		$this->profiler = $profiler;
	}

	public function getAffectedRows(): int
	{
		return $this->result->getAffectedRows();
	}

	public function toArray(bool $doClose = true, int $count = 0): array
	{
		return $this->result->toArray($doClose, $count);
	}

	public function fetch()
	{
		$this->profiler->startRecording('database');

		try {
			return $this->result->fetch();
		} finally {
			$this->profiler->stopRecording();
		}
	}

	public function close(): bool
	{
		$this->profiler->startRecording('database');

		try {
			return $this->result->close();
		} finally {
			$this->profiler->stopRecording();
		}
	}

	public function getColumnCount(): int
	{
		return $this->result->getColumnCount();
	}
}
