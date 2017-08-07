<?php
/**
 * Plugin Name: ACF CPT Importer
 * Plugin URI: http://rayflores.com/plugins/
 * Description: Importer for the CPT - built with ACF, that add a new post per each line of data witin the uploaded csv file.
 * Author: Ray Flores
 * Author URI: http://rayflores.com
 * Version: 0.1
 * Requires at least: 4.0
 * Tested up to: 4.4.2
 *
 */
add_action( 'admin_init', 'rf_acf_cpt_register_importers');

/**
 * Add menu items
 */
function rf_acf_cpt_register_importers() {
    register_importer( 'rf_acf_cpt_importer', __( 'Import CPT Data (CSV)', 'rf-exhibitor-importer' ), __( 'Import new CPTs to your site via a csv file.', 'rf-acf-cpt-importer'), 'rf_acf_csv_importer' );
}

/**
 * Add menu item
 */
function rf_acf_csv_importer() {
    // Load Importer API
    require_once ABSPATH . 'wp-admin/includes/import.php';

    if ( ! class_exists( 'WP_Importer' ) ) {
        $class_wp_importer = ABSPATH . 'wp-admin/includes/class-wp-importer.php';
        if ( file_exists( $class_wp_importer ) )
            require $class_wp_importer;
    }

    // includes
    require 'importers/class-rf-acf-cpt-importer.php';

    // Dispatch
    $importer = new RF_ACF_CPT_Importer();
    $importer->dispatch();
}
