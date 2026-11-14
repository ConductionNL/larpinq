<?php

/**
 * An event message larpinq will not send.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @category  Service
 * @package   OCA\Larpinq\Service
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 *
 * @spec openspec/changes/communication-mail-participants/specs/event-messages/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Service;

use RuntimeException;

/**
 * Raised when a message is not sent, such as one that was sent before; the
 * message is the untranslated reason a game master reads.
 *
 * @category Service
 * @package  OCA\Larpinq\Service
 *
 * @spec openspec/changes/communication-mail-participants/specs/event-messages/spec.md#requirement-each-recipient-gets-their-own-mail-req-cmp-003
 */
class EventMessageRefusedException extends RuntimeException {
}//end class
