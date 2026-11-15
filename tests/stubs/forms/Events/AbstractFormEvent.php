<?php

/**
 * TEST-ONLY SHAPE of nextcloud/forms lib/Events/AbstractFormEvent.php (main, read 2026-10-01): the
 * constructor, accessors and read() payload larpinq relies on, loaded only by
 * tests/bootstrap.php when the forms app is not on the path, so the sign-up
 * listener test dispatches an event of the real class name and shape.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 */

declare(strict_types=1);

namespace OCA\Forms\Events;

use OCA\Forms\Db\Form;
use OCP\EventDispatcher\Event;

abstract class AbstractFormEvent extends Event {
	/**
	 * An event about one form.
	 *
	 * @param Form $form The form.
	 */
	public function __construct(
		protected Form $form,
	) {
		parent::__construct();
	}

	/**
	 * The form the event is about.
	 *
	 * @return Form The form the event is about.
	 */
	public function getForm(): Form {
		return $this->form;
	}

	/**
	 * The event as a webhook payload.
	 *
	 * @return array The event as a webhook payload.
	 */
	public function getWebhookSerializable(): array {
		return $this->form->read();
	}
}
