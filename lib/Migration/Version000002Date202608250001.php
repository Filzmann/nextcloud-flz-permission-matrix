<?php

declare(strict_types=1);

namespace OCA\FilzmannPermissionMatrix\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/** Additive, wiederholbare Historie app-lokaler Admin-Vollzugriffszeiträume. */
final class Version000002Date202608250001 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();
        if (!$schema->hasTable('pm_admin_access')) {
            $table = $schema->createTable('pm_admin_access');
            $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('target_uid', Types::STRING, ['length' => 64, 'notnull' => true]);
            $table->addColumn('granted_by', Types::STRING, ['length' => 64, 'notnull' => true]);
            $table->addColumn('starts_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $table->addColumn('ends_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $table->addColumn('revoked_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
            $table->addColumn('revoked_by', Types::STRING, ['length' => 64, 'notnull' => false]);
            $table->addColumn('created_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['target_uid', 'starts_at', 'ends_at'], 'pm_admin_target_time');
            $table->addIndex(['granted_by', 'starts_at'], 'pm_admin_grantor_time');
        }
        return $schema;
    }
}


