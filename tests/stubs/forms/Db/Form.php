<?php

/**
 * TEST-ONLY SHAPE of nextcloud/forms lib/Db/Form.php (main, read 2026-10-01): the
 * constructor, accessors and read() payload larpinq relies on, loaded only by
 * tests/bootstrap.php when the forms app is not on the path, so the sign-up
 * listener test dispatches an event of the real class name and shape.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 */

declare(strict_types=1);

namespace OCA\Forms\Db;

class Form {
	public function __construct(
		private int $id,
		private string $title = '',
	) {
	}

	public function getId(): int {
		return $this->id;
	}

	public function read(): array {
		return [
			'id' => $this->id,
			'title' => $this->title,
		];
	}
}
