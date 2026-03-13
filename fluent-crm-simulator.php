<?php

defined('ABSPATH') || exit('Direct access not allowed.');

/*
Plugin Name: FluentCRM Simulator
Description: Automatically generates test campaigns and simulates email engagement (opens/clicks) for FluentCRM. A developer tool for testing at scale.
Version: 1.0.1
Author: Hasanuzzaman
Author URI: https://hasanuzzaman.com
Plugin URI: https://github.com/shamim0902/fluent-crm-simulator
License: GPLv2 or later
Text Domain: fluent-crm-simulator
*/

define('FCRMSIM_VERSION', '1.0.1');
define('FCRMSIM_PLUGIN_FILE', __FILE__);
define('FCRMSIM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FCRMSIM_PLUGIN_URL', plugin_dir_url(__FILE__));

// Register custom cron interval
add_filter('cron_schedules', function ($schedules) {
    $schedules['fcrmsim_five_minutes'] = [
        'interval' => 300,
        'display'  => 'Every Five Minutes (FCRM Simulator)',
    ];
    return $schedules;
});

add_action('plugins_loaded', function () {
    if (!fcrmsim_check_dependencies()) {
        return;
    }

    // PSR-4 autoloader
    spl_autoload_register(function ($class) {
        $prefix = 'FluentCrmSimulator\\';
        $base_dir = FCRMSIM_PLUGIN_DIR . 'includes/';
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }
        $relative_class = substr($class, $len);
        $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
        if (file_exists($file)) {
            require $file;
        }
    });

    (new \FluentCrmSimulator\SimulatorAdmin())->register();
    (new \FluentCrmSimulator\SimulatorScheduler())->register();

    // GitHub auto-updater
    new \FluentCrmSimulator\PluginManager\GitHubUpdater(
        FCRMSIM_PLUGIN_FILE,
        'shamim0902/fluent-crm-simulator',
        FCRMSIM_VERSION
    );

    // "Check Update" link in plugin row
    add_filter('plugin_row_meta', function ($links, $pluginFile) {
        if (plugin_basename(FCRMSIM_PLUGIN_FILE) !== $pluginFile) {
            return $links;
        }

        $checkUpdateUrl = esc_url(admin_url('plugins.php?fcrmsim-check-update=' . time()));
        $links['check_update'] = '<a style="color: #583fad;font-weight: 600;" href="' . $checkUpdateUrl . '">' . esc_html__('Check Update', 'fluent-crm-simulator') . '</a>';

        return $links;
    }, 10, 2);
}, 20);

register_deactivation_hook(__FILE__, function () {
    if (class_exists('\FluentCrmSimulator\SimulatorScheduler')) {
        \FluentCrmSimulator\SimulatorScheduler::clearSchedule();
    }
});

function fcrmsim_check_dependencies()
{
    if (!defined('FLUENTCRM')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p>';
            echo '<strong>FluentCRM Simulator</strong> requires FluentCRM to be installed and activated.';
            echo '</p></div>';
        });
        return false;
    }

    return true;
}
