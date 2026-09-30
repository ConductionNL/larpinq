<?php

/**
 * OpenRegister ObjectCreatedEvent
 *
 * This file contains the event class dispatched when an object is created
 * in the OpenRegister application.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category Event
 * @package  OCA\OpenRegister\Event
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2024 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://OpenRegister.app
 *
 * TEST-ONLY COPY of openregister/lib/Event/ObjectCreatedEvent.php (development, exported
 * under lane9/or-dev), the real class verbatim, loaded only by tests/bootstrap.php when
 * OpenRegister itself is not on the path. OpenRegisterStubDriftTest compares it with the
 * real file when the openregister source sits beside this app.
 */

namespace OCA\OpenRegister\Event;

use OCA\OpenRegister\Db\ObjectEntity;
use OCP\EventDispatcher\Event;

/**
 * Event dispatched when an object is created
 */
class ObjectCreatedEvent extends Event {

	/**
	 * The newly created object entity
	 *
	 * @var ObjectEntity The object entity that was created
	 */
	private ObjectEntity $object;

	/**
	 * Constructor for ObjectCreatedEvent
	 *
	 * @param ObjectEntity $object The object entity that was created
	 *
	 * @return void
	 */
	public function __construct(ObjectEntity $object) {
		parent::__construct();
		$this->object = $object;
	}//end __construct()

	/**
	 * Get the created object entity
	 *
	 * @return ObjectEntity The object entity that was created
	 *
	 * @spec openspec/specs/event-driven-architecture/spec.md#requirement-event-payloads-for-webhook-delivery-must-include-register-and-schema-context-for-object-events
	 */
	public function getObject(): ObjectEntity {
		return $this->object;
	}//end getObject()
}//end class
