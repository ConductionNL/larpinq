<?php

/**
 * TEST-ONLY SHAPE of nextcloud/forms lib/Events/FormSubmittedEvent.php (main, read 2026-10-01): the
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
use OCA\Forms\Db\Submission;

class FormSubmittedEvent extends AbstractFormEvent {
	/**
	 * The event Forms dispatches when a form is submitted.
	 *
	 * @param Form $form The form.
	 * @param Submission $submission The submission.
	 */
	public function __construct(
		Form $form,
		private readonly Submission $submission,
	) {
		parent::__construct(form: $form);
	}

	/**
	 * The form and the submission as a webhook payload.
	 *
	 * @return array The form and the submission as a webhook payload.
	 */
	public function getWebhookSerializable(): array {
		return [
			'form' => $this->form->read(),
			'submission' => $this->submission->read(),
		];
	}
}
