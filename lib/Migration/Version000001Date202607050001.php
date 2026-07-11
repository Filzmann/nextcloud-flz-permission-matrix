<?php

declare(strict_types=1);

namespace OCA\BrPermissionMatrix\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000001Date202607050001 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if (!$schema->hasTable('permission_matrix_snapshots')) {
            $table = $schema->createTable('permission_matrix_snapshots');
            $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('snapshot_uuid', Types::STRING, ['notnull' => true, 'length' => 64]);
            $table->addColumn('created_at', Types::DATETIME, ['notnull' => true]);
            $table->addColumn('created_by_uid', Types::STRING, ['notnull' => false, 'length' => 64]);
            $table->addColumn('nextcloud_version', Types::STRING, ['notnull' => true, 'length' => 64, 'default' => 'unknown']);
            $table->addColumn('group_count', Types::INTEGER, ['notnull' => true, 'default' => 0]);
            $table->addColumn('app_count', Types::INTEGER, ['notnull' => true, 'default' => 0]);
            $table->addColumn('object_count', Types::INTEGER, ['notnull' => true, 'default' => 0]);
            $table->addColumn('warning_count', Types::INTEGER, ['notnull' => true, 'default' => 0]);
            $table->addColumn('unsupported_count', Types::INTEGER, ['notnull' => true, 'default' => 0]);
            $table->addColumn('compliance_status', Types::STRING, ['notnull' => true, 'length' => 16, 'default' => 'UNKNOWN']);
            $table->addColumn('summary_json', Types::TEXT, ['notnull' => false]);
            $table->addColumn('snapshot_json', Types::TEXT, ['notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['snapshot_uuid'], 'pm_snap_uuid');
            $table->addIndex(['created_at'], 'pm_snap_created');
        }

        if (!$schema->hasTable('permission_matrix_rows')) {
            $table = $schema->createTable('permission_matrix_rows');
            $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('snapshot_uuid', Types::STRING, ['notnull' => true, 'length' => 64]);
            $table->addColumn('row_key', Types::STRING, ['notnull' => true, 'length' => 64]);
            $table->addColumn('object_type', Types::STRING, ['notnull' => true, 'length' => 64]);
            $table->addColumn('app_id', Types::STRING, ['notnull' => true, 'length' => 64]);
            $table->addColumn('object_name', Types::STRING, ['notnull' => true, 'length' => 512]);
            $table->addColumn('detail', Types::STRING, ['notnull' => true, 'length' => 512]);
            $table->addColumn('permission_type', Types::STRING, ['notnull' => true, 'length' => 128]);
            $table->addColumn('status', Types::STRING, ['notnull' => true, 'length' => 32]);
            $table->addColumn('source', Types::STRING, ['notnull' => true, 'length' => 128]);
            $table->addColumn('confidence', Types::STRING, ['notnull' => true, 'length' => 16]);
            $table->addColumn('warnings_json', Types::TEXT, ['notnull' => false]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['snapshot_uuid'], 'pm_rows_snap');
            $table->addIndex(['snapshot_uuid', 'row_key'], 'pm_rows_key');
            $table->addIndex(['app_id'], 'pm_rows_app');
            $table->addIndex(['status'], 'pm_rows_status');
        }

        if (!$schema->hasTable('permission_matrix_cells')) {
            $table = $schema->createTable('permission_matrix_cells');
            $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('snapshot_uuid', Types::STRING, ['notnull' => true, 'length' => 64]);
            $table->addColumn('row_key', Types::STRING, ['notnull' => true, 'length' => 64]);
            $table->addColumn('group_id', Types::STRING, ['notnull' => true, 'length' => 190]);
            $table->addColumn('cell_value', Types::STRING, ['notnull' => true, 'length' => 64]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['snapshot_uuid'], 'pm_cells_snap');
            $table->addIndex(['snapshot_uuid', 'row_key'], 'pm_cells_row');
            $table->addIndex(['group_id'], 'pm_cells_group');
        }

        if (!$schema->hasTable('permission_matrix_diffs')) {
            $table = $schema->createTable('permission_matrix_diffs');
            $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('baseline_uuid', Types::STRING, ['notnull' => false, 'length' => 64]);
            $table->addColumn('snapshot_uuid', Types::STRING, ['notnull' => true, 'length' => 64]);
            $table->addColumn('diff_type', Types::STRING, ['notnull' => true, 'length' => 64]);
            $table->addColumn('severity', Types::STRING, ['notnull' => true, 'length' => 16]);
            $table->addColumn('row_key', Types::STRING, ['notnull' => false, 'length' => 64]);
            $table->addColumn('group_id', Types::STRING, ['notnull' => false, 'length' => 190]);
            $table->addColumn('old_value', Types::STRING, ['notnull' => false, 'length' => 128]);
            $table->addColumn('new_value', Types::STRING, ['notnull' => false, 'length' => 128]);
            $table->addColumn('message', Types::TEXT, ['notnull' => false]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['snapshot_uuid'], 'pm_diff_snap');
            $table->addIndex(['baseline_uuid'], 'pm_diff_base');
            $table->addIndex(['diff_type'], 'pm_diff_type');
        }

        if (!$schema->hasTable('permission_matrix_exports')) {
            $table = $schema->createTable('permission_matrix_exports');
            $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('snapshot_uuid', Types::STRING, ['notnull' => true, 'length' => 64]);
            $table->addColumn('created_at', Types::DATETIME, ['notnull' => true]);
            $table->addColumn('created_by_uid', Types::STRING, ['notnull' => false, 'length' => 64]);
            $table->addColumn('format', Types::STRING, ['notnull' => true, 'length' => 16]);
            $table->addColumn('filename', Types::STRING, ['notnull' => false, 'length' => 255]);
            $table->addColumn('size_bytes', Types::BIGINT, ['notnull' => true, 'default' => 0]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['snapshot_uuid'], 'pm_exp_snap');
            $table->addIndex(['created_at'], 'pm_exp_created');
        }

        if (!$schema->hasTable('permission_matrix_audit_log')) {
            $table = $schema->createTable('permission_matrix_audit_log');
            $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('created_at', Types::DATETIME, ['notnull' => true]);
            $table->addColumn('user_id', Types::STRING, ['notnull' => false, 'length' => 64]);
            $table->addColumn('action', Types::STRING, ['notnull' => true, 'length' => 128]);
            $table->addColumn('export_generated', Types::BOOLEAN, ['notnull' => true, 'default' => false]);
            $table->addColumn('snapshot_uuid', Types::STRING, ['notnull' => false, 'length' => 64]);
            $table->addColumn('details_json', Types::TEXT, ['notnull' => false]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['created_at'], 'pm_audit_created');
            $table->addIndex(['user_id'], 'pm_audit_user');
            $table->addIndex(['action'], 'pm_audit_action');
        }

        if (!$schema->hasTable('permission_matrix_adapter_status')) {
            $table = $schema->createTable('permission_matrix_adapter_status');
            $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('snapshot_uuid', Types::STRING, ['notnull' => true, 'length' => 64]);
            $table->addColumn('app_id', Types::STRING, ['notnull' => true, 'length' => 64]);
            $table->addColumn('adapter', Types::STRING, ['notnull' => true, 'length' => 128]);
            $table->addColumn('status', Types::STRING, ['notnull' => true, 'length' => 32]);
            $table->addColumn('confidence', Types::STRING, ['notnull' => true, 'length' => 16]);
            $table->addColumn('warnings_json', Types::TEXT, ['notnull' => false]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['snapshot_uuid'], 'pm_adapt_snap');
            $table->addIndex(['app_id'], 'pm_adapt_app');
        }

        return $schema;
    }
}
