<?php
/**
 * Plugin Name: Best Value Digital CRM
 * Description: Timesheet & client tracking (BVD CRM).
 * Author:      Dusan Stojanovic
 * Version:     1.0.0
 * License:     GPL‑2.0‑or‑later
 *
 * @package BVD\CRM
 */

if (!defined('ABSPATH')) {
    exit;
}

define('BVD_CRM_FILE', __FILE__);
define('BVD_CRM_PATH', plugin_dir_path(__FILE__));
define('BVD_CRM_URL', plugin_dir_url(__FILE__));
define('BVD_CRM_VERSION', '1.1.0');

require_once __DIR__ . '/vendor/autoload.php';

BVD\CRM\Core\Plugin::instance();
