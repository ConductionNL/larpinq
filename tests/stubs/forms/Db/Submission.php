<?php

/**
 * TEST-ONLY SHAPE of nextcloud/forms lib/Db/Submission.php (main, read 2026-10-01): the
 * constructor, accessors and read() payload larpinq relies on, loaded only by
 * tests/bootstrap.php when the forms app is not on the path, so the sign-up
 * listener test dispatches an event of the real class name and shape.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 */

declare(strict_types=1);

namespace OCA\Forms\Db;

class Submission {
	public function __construct(
		private int $id,
		private int $formId,
		private string $userId,
		private int $timestamp = 0,
	) {
	}

	public function getUserId(): string {
		return $this->userId;
	}

	public function read(): array {
		return [
			'id' => $this->id,
			'formId' => $this->formId,
			'userId' => $this->userId,
			'timestamp' => $this->timestamp,
		];
	}
}
