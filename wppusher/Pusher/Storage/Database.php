<?php

namespace Pusher\Storage;

class Database
{
    public static $pusher_db_version = '1.0';

    public function cleanup()
    {
        global $wpdb;

        $table_name = pusherTableName();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom plugin table from pusherTableName()
        $rows = $wpdb->get_results("SELECT * FROM {$table_name}");

        foreach ($rows as $row) {

            if ($row->type === '1' && ! file_exists(WP_PLUGIN_DIR . "/" . $row->package)) {
                $this->delete($row->id);
                continue;
            }

            if ($row->type === '2' && ! file_exists(get_theme_root() . "/" . $row->package)) {
                $this->delete($row->id);
                continue;
            }
        }
    }

    public function delete($id)
    {
        global $wpdb;

        $table_name = pusherTableName();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->delete($table_name, array('id' => sanitize_text_field($id)));
    }

    public function install()
    {
        global $wpdb;

        $table_name = pusherTableName();

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            package varchar(255) NOT NULL,
            repository varchar(255) NOT NULL,
            branch varchar(255) NOT NULL DEFAULT 'master',
            type int NOT NULL,
            status int NOT NULL,
            ptd int NOT NULL,
            host varchar(10) NOT NULL,
            private int NOT NULL,
            subdirectory VARCHAR(255),
            UNIQUE KEY id (id)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- dbDelta requires raw CREATE TABLE SQL
        dbDelta( $sql );
    }

    public function uninstall()
    {
        global $wpdb;

        $table_name = pusherTableName();

        $sql = "DROP TABLE IF EXISTS $table_name;";

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared -- table name from pusherTableName()
        $wpdb->query($sql);
    }
}
