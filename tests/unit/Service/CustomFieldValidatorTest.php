<?php

/**
 * Where a value of a field without a visibility belongs.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Service
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/character-custom-fields/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Service;

use OCA\Larpinq\Service\CustomFieldValidator;
use PHPUnit\Framework\TestCase;

/**
 * A field without a visibility is an owner field.
 *
 * @covers \OCA\Larpinq\Service\CustomFieldValidator
 */
class CustomFieldValidatorTest extends TestCase {

	private const SCARS = ['scars' => ['key' => 'scars', 'fieldType' => 'number']];

	/**
	 * Its value is accepted in customFields.
	 *
	 * @return void
	 */
	public function testAFieldWithoutVisibilityIsAcceptedInCustomFields(): void {
		$errors = (new CustomFieldValidator())->validate(property: 'customFields', values: ['scars' => 2], definitions: self::SCARS);

		self::assertSame(expected: [], actual: $errors);
	}//end testAFieldWithoutVisibilityIsAcceptedInCustomFields()

	/**
	 * Its value is refused in customFieldsPrivate.
	 *
	 * @return void
	 */
	public function testAFieldWithoutVisibilityIsRefusedInCustomFieldsPrivate(): void {
		$errors = (new CustomFieldValidator())->validate(property: 'customFieldsPrivate', values: ['scars' => 2], definitions: self::SCARS);

		self::assertSame(expected: ['scars' => 'This field belongs in customFields.'], actual: $errors);
	}//end testAFieldWithoutVisibilityIsRefusedInCustomFieldsPrivate()
}//end class
