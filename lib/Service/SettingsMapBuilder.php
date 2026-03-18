<?php

/**
 * LarpingApp SettingsMapBuilder.
 *
 * Service for building schema and register maps from import results.
 *
 * @category  Service
 * @package   OCA\LarpingApp\Service
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2024 Ruben Linde
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT: <git_id>
 * @link      https://larpingapp.com
 */

declare(strict_types=1);

namespace OCA\LarpingApp\Service;

/**
 * Service for building schema and register maps from import results.
 *
 * @category Service
 * @package  OCA\LarpingApp\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 */
class SettingsMapBuilder
{

    /**
     * LarpingApp register slug.
     *
     * @var string
     */
    private const REGISTER_SLUG = 'larpingapp';

    /**
     * Build a slug-to-ID map from imported schemas.
     *
     * @param array $schemas The imported schemas.
     *
     * @return array The slug-to-ID map.
     */
    public function buildSchemaSlugMap(array $schemas): array
    {
        $schemaMap = [];
        /** @psalm-suppress MixedAssignment Schema entries from import result */
        foreach ($schemas as $schema) {
            $this->addSchemaToMap(
                schema: $schema,
                schemaMap: $schemaMap
            );
        }

        return $schemaMap;

    }//end buildSchemaSlugMap()

    /**
     * Find the larpingapp register ID from imported registers.
     *
     * @param array $registers The imported registers.
     *
     * @return mixed The register ID or null.
     */
    public function findRegisterIdBySlug(array $registers): mixed
    {
        /** @psalm-suppress MixedAssignment Register entries from import result */
        foreach ($registers as $register) {
            $registerId = $this->extractRegisterIdIfMatch(register: $register);
            if ($registerId !== null) {
                return $registerId;
            }
        }

        return null;

    }//end findRegisterIdBySlug()

    /**
     * Add a single schema entry to the slug map.
     *
     * @param mixed $schema    The schema object or array.
     * @param array $schemaMap The map to populate.
     *
     * @return void
     */
    private function addSchemaToMap(mixed $schema, array &$schemaMap): void
    {
        $schemaArray = $this->normalizeToArray(value: $schema);
        if ($schemaArray === null) {
            return;
        }

        if (isset($schemaArray['slug']) === false) {
            return;
        }

        /** @var string|int|null $schemaId */
        $schemaId = $schemaArray['id'] ?? $schemaArray['uuid'] ?? null;
        $schemaMap[(string) $schemaArray['slug']] = $schemaId;

    }//end addSchemaToMap()

    /**
     * Extract register ID if the register matches the larpingapp slug.
     *
     * @param mixed $register The register object or array.
     *
     * @return mixed The register ID or null.
     */
    private function extractRegisterIdIfMatch(mixed $register): mixed
    {
        $registerArray = $this->normalizeToArray(value: $register);
        if ($registerArray === null) {
            return null;
        }

        if (isset($registerArray['slug']) === false) {
            return null;
        }

        if ($registerArray['slug'] !== self::REGISTER_SLUG) {
            return null;
        }

        return $registerArray['id'] ?? $registerArray['uuid'] ?? null;

    }//end extractRegisterIdIfMatch()

    /**
     * Normalize an object or array value to an array.
     *
     * @param mixed $value The value to normalize.
     *
     * @return array<array-key, mixed>|null The array or null if not normalizable.
     */
    private function normalizeToArray(mixed $value): ?array
    {
        if (is_object($value) === true && method_exists($value, 'jsonSerialize') === true) {
            /** @var \JsonSerializable $value */
            /** @var array<array-key, mixed> $result */
            $result = $value->jsonSerialize();
            return $result;
        }

        if (is_array($value) === true) {
            return $value;
        }

        return null;

    }//end normalizeToArray()
}//end class
