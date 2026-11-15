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
	/**
	 * A Forms form with an id and a title.
	 *
	 * @param int $id The id.
	 * @param string $title The title.
	 */
	public function __construct(
		private int $id,
		private string $title = '',
	) {
	}

	/**
	 * The form id.
	 *
	 * @return int The form id.
	 */
	public function getId(): int {
		return $this->id;
	}

	/**
	 * The form as Forms serialises it.
	 *
	 * @return array The form as Forms serialises it.
	 */
	public function read(): array {
		return [
			'id' => $this->id,
			'title' => $this->title,
		];
	}
}
