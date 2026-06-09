<?php

// Copyright (C) 2010-2024, the Friendica project
// SPDX-FileCopyrightText: 2010-2024 the Friendica project
//
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Friendica\Content\Conversation;

/**
 * Lightweight rendering context for threaded conversation templates.
 */
readonly class RenderThreadContext
{
	public function __construct(
		private string $mode,
		private bool $preview,
		private bool $writable,
		private int $profileOwner,
	) {}

	public function getMode(): string
	{
		return $this->mode;
	}

	public function isWritable(): bool
	{
		return $this->writable;
	}

	public function isPreview(): bool
	{
		return $this->preview;
	}

	public function getProfileOwner(): int
	{
		return $this->profileOwner;
	}
}
