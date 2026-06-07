<?php

// Copyright (C) 2010-2024, the Friendica project
// SPDX-FileCopyrightText: 2010-2024 the Friendica project
//
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Friendica\Content\Conversation;

/**
 * Lightweight rendering context for threaded conversation templates.
 */
class RenderThreadContext
{
	public function __construct(
		private readonly string $mode,
		private readonly bool $preview,
		private readonly bool $writable,
		private readonly int $profileOwner,
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
