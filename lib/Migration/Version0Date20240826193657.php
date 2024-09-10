<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LarpingApp\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;


/**
 * FIXME Auto-generated migration step: Please modify to your needs!
 */
class Version0Date20240826193657 extends SimpleMigrationStep {

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 */
	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
	}

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/**
		 * @var ISchemaWrapper $schema
		 */
		$schema = $schemaClosure();

		if (!$schema->hasTable('larpingapp_abilities')) {
			$table = $schema->createTable('larpingapp_abilities');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'length' => 20]);
			$table->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 255]);
			$table->addColumn('description', Types::TEXT, ['notnull' => false]);
			$table->setPrimaryKey(['id']);
		}

		if (!$schema->hasTable('larpingapp_settings')) {
			$table = $schema->createTable('larpingapp_settings');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'length' => 20]);
			$table->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 255]);
			$table->addColumn('description', Types::TEXT, ['notnull' => false]);
			$table->setPrimaryKey(['id']);
		}

		if (!$schema->hasTable('larpingapp_players')) {
			$table = $schema->createTable('larpingapp_players');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'length' => 20]);
			$table->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 255]);
			$table->addColumn('description', Types::TEXT, ['notnull' => false]);
			$table->setPrimaryKey(['id']);
		}

		if (!$schema->hasTable('larpingapp_characters')) {
			$table = $schema->createTable('larpingapp_characters');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'length' => 20]);
			$table->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 255]);
			$table->addColumn('oc_name', Types::STRING, ['notnull' => false, 'length' => 255]);
			$table->addColumn('description', Types::TEXT, ['notnull' => false]);
			$table->addColumn('background', Types::TEXT, ['notnull' => false]);
			$table->addColumn('items_and_money', Types::TEXT, ['notnull' => false]);
			$table->addColumn('notice', Types::TEXT, ['notnull' => false]);
			$table->addColumn('faith', Types::STRING, ['notnull' => false, 'length' => 255]);
			$table->addColumn('sl_notes_public', Types::TEXT, ['notnull' => false]);
			$table->addColumn('sl_notes_private', Types::TEXT, ['notnull' => false]);
			$table->addColumn('card', Types::STRING, ['notnull' => false, 'length' => 255]);
			$table->addColumn('stats', Types::JSON, ['notnull' => false]);
			$table->addColumn('gold', Types::INTEGER, ['notnull' => false]);
			$table->addColumn('silver', Types::INTEGER, ['notnull' => false]);
			$table->addColumn('copper', Types::INTEGER, ['notnull' => false]);
			$table->addColumn('events', Types::JSON, ['notnull' => false]);
			$table->addColumn('skills', Types::JSON, ['notnull' => false]);
			$table->addColumn('items', Types::JSON, ['notnull' => false]);
			$table->addColumn('conditions', Types::JSON, ['notnull' => false]);
			$table->addColumn('type', Types::STRING, ['notnull' => true, 'length' => 50, 'default' => 'player']);
			$table->addColumn('approved', Types::STRING, ['notnull' => false, 'length' => 50]);
			$table->setPrimaryKey(['id']);
		}

		if (!$schema->hasTable('larpingapp_conditions')) {
			$table = $schema->createTable('larpingapp_conditions');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'length' => 20]);
			$table->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 255]);
			$table->addColumn('description', Types::TEXT, ['notnull' => false]);
			$table->addColumn('effect', Types::TEXT, ['notnull' => false]);
			$table->addColumn('effects', Types::JSON, ['notnull' => false]);
			$table->addColumn('unique', Types::BOOLEAN, ['notnull' => true, 'default' => false]);
			$table->setPrimaryKey(['id']);
		}

		if (!$schema->hasTable('larpingapp_effects')) {
			$table = $schema->createTable('larpingapp_effects');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'length' => 20]);
			$table->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 255]);
			$table->addColumn('description', Types::TEXT, ['notnull' => false]);
			$table->addColumn('stat_id', Types::STRING, ['notnull' => false, 'length' => 255]);
			$table->addColumn('modifier', Types::INTEGER, ['notnull' => false]);
			$table->addColumn('modification', Types::STRING, ['notnull' => true, 'length' => 50, 'default' => 'positive']);
			$table->addColumn('cumulative', Types::STRING, ['notnull' => true, 'length' => 50, 'default' => 'non-cumulative']);
			$table->setPrimaryKey(['id']);
		}

		if (!$schema->hasTable('larpingapp_events')) {
			$table = $schema->createTable('larpingapp_events');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'length' => 20]);
			$table->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 255]);
			$table->addColumn('description', Types::TEXT, ['notnull' => false]);
			$table->addColumn('players', Types::JSON, ['notnull' => false]);
			$table->addColumn('effects', Types::JSON, ['notnull' => false]);
			$table->addColumn('start_date', Types::STRING, ['notnull' => false]);
			$table->addColumn('end_date', Types::STRING, ['notnull' => false]);
			$table->addColumn('location', Types::STRING, ['notnull' => false, 'length' => 255]);
			$table->setPrimaryKey(['id']);
		}

		if (!$schema->hasTable('larpingapp_items')) {
			$table = $schema->createTable('larpingapp_items');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'length' => 20]);
			$table->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 255]);
			$table->addColumn('description', Types::TEXT, ['notnull' => false]);
			$table->addColumn('effect', Types::TEXT, ['notnull' => false]);
			$table->addColumn('effects', Types::JSON, ['notnull' => false]);
			$table->addColumn('unique', Types::BOOLEAN, ['notnull' => true, 'default' => true]);
			$table->setPrimaryKey(['id']);
		}

		if (!$schema->hasTable('larpingapp_skills')) {
			$table = $schema->createTable('larpingapp_skills');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'length' => 20]);
			$table->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 255]);
			$table->addColumn('description', Types::TEXT, ['notnull' => false]);
			$table->addColumn('effect', Types::TEXT, ['notnull' => false]);
			$table->addColumn('effects', Types::JSON, ['notnull' => false]);
			$table->addColumn('required_skills', Types::JSON, ['notnull' => false]);
			$table->addColumn('required_stats', Types::JSON, ['notnull' => false]);
			$table->addColumn('required_conditions', Types::JSON, ['notnull' => false]);
			$table->addColumn('required_effects', Types::JSON, ['notnull' => false]);
			$table->addColumn('required_score', Types::INTEGER, ['notnull' => false]);
			$table->setPrimaryKey(['id']);
		}

		if (!$schema->hasTable('larpingapp_templates')) {
			$table = $schema->createTable('larpingapp_templates');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'length' => 20]);
			$table->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 255]);
			$table->addColumn('description', Types::TEXT, ['notnull' => false]);
			$table->addColumn('template', Types::TEXT, ['notnull' => false]);
			$table->setPrimaryKey(['id']);
		}

		return $schema;
	}

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
	}
}
