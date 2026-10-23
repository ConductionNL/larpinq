<?php

/**
 * OpenRegister ObjectUpdatedEvent
 *
 * This file contains the event class dispatched when an object is updated
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
 * TEST-ONLY COPY of openregister/lib/Event/ObjectUpdatedEvent.php (development, exported
 * under lane9/or-dev), the real class verbatim but for its @spec tags, loaded only by
 * tests/bootstrap.php when OpenRegister itself is not on the path. OpenRegisterStubDriftTest
 * compares its methods with the real file when the openregister source sits beside this app.
 */

namespace OCA\OpenRegister\Event;

use OCA\OpenRegister\Db\ObjectEntity;
use OCP\EventDispatcher\Event;

/**
 * Event dispatched when an object is updated
 */
class ObjectUpdatedEvent extends Event {

	/**
	 * The updated object entity state
	 *
	 * @var ObjectEntity The object entity after update
	 */
	private ObjectEntity $newObject;

	/**
	 * The previous object entity state
	 *
	 * @var ObjectEntity|null The object entity before update (null if not available)
	 */
	private ?ObjectEntity $oldObject;

	/**
	 * Constructor for ObjectUpdatedEvent
	 *
	 * @param ObjectEntity $newObject The object entity after update
	 * @param ObjectEntity|null $oldObject The object entity before update (null if not available)
	 *
	 * @return void
	 */
	public function __construct(ObjectEntity $newObject, ?ObjectEntity $oldObject = null) {
		parent::__construct();
		$this->newObject = $newObject;
		$this->oldObject = $oldObject;
	}//end __construct()

	/**
	 * Get the updated object entity
	 *
	 * @return ObjectEntity The object entity after update
	 */
	public function getObject(): ObjectEntity {
		return $this->newObject;
	}//end getObject()

	/**
	 * Get the updated object entity
	 *
	 * @return ObjectEntity The object entity after update
	 */
	public function getNewObject(): ObjectEntity {
		return $this->newObject;
	}//end getNewObject()

	/**
	 * Get the original object entity
	 *
	 * @return ObjectEntity|null The object entity before update (null if not available)
	 */
	public function getOldObject(): ?ObjectEntity {
		return $this->oldObject;
	}//end getOldObject()
}//end class
