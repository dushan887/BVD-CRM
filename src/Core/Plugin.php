<?php

declare(strict_types=1);

namespace BVD\CRM\Core;

use BVD\CRM\Core\Assets;
use BVD\CRM\Core\Installer;

use BVD\CRM\Models\Client;
use BVD\CRM\Models\Employee;
use BVD\CRM\Models\Job;
use BVD\CRM\Models\Timesheet;

final class Plugin
{
    private static ?self $instance = null;

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    private function __construct()
    {
        $this->register_hooks();
    }

    private function register_hooks(): void
    {
        register_activation_hook(BVD_CRM_FILE, [Installer::class, 'activate']);

        add_action('plugins_loaded', [$this, 'boot']);
    }

    public function boot(): void
    {
        // Load textdomain if ever needed.
        // load_plugin_textdomain('bvd-crm', false, dirname(plugin_basename(BVD_CRM_FILE)) . '/languages');

        // Core services.
        Assets::register();
        Installer::maybeUpgrade();
        
        global $wpdb;
        Client::boot($wpdb->prefix);
        Employee::boot($wpdb->prefix);
        Job::boot($wpdb->prefix);
        Timesheet::boot($wpdb->prefix);


        // Admin, Front, AJAX, Shortcodes, etc.
        if (is_admin()) {
            (new \BVD\CRM\Admin\Menu())->register();
            (new \BVD\CRM\Admin\Ajax())->register();
        } else {
            (new \BVD\CRM\Front\Ajax())->register();
            (new \BVD\CRM\Front\Shortcodes\ClientSummary())->register();
        }
    }
}
