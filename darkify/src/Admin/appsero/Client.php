<?php

namespace DarkifyAppSero;

// Abort if this file is accessed directly, outside of WordPress.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Appsero Client
 *
 * This class is necessary to set project data
 */
if (!class_exists('Client')) {
class Client
{

    /**
     * The client version
     *
     * @var string
     */
    public $version = '2.0.4';

    /**
     * Hash identifier of the plugin
     *
     * @var string
     */
    public $hash;

    /**
     * Name of the plugin
     *
     * @var string
     */
    public $name;

    /**
     * The plugin/theme file path
     * @example .../wp-content/plugins/test-slug/test-slug.php
     *
     * @var string
     */
    public $file;

    /**
     * Main plugin file
     * @example test-slug/test-slug.php
     *
     * @var string
     */
    public $basename;

    /**
     * Slug of the plugin
     * @example test-slug
     *
     * @var string
     */
    public $slug;

    /**
     * The project version
     *
     * @var string
     */
    public $project_version;

    /**
     * The project type
     *
     * @var string
     */
    public $type;

    /**
     * textdomain
     *
     * @var string
     */
    public $textdomain;

    /**
     * Initialize the class
     *
     * @param string  $hash hash of the plugin
     * @param string  $name readable name of the plugin
     * @param string  $file main plugin file path
     */
    public function __construct($hash, $name, $file)
    {
        $this->hash = $hash;
        $this->name = $name;
        $this->file = $file;

        $this->set_basename_and_slug();
    }

    /**
     * Initialize insights class
     *
     * @return Appsero\Insights
     */
    public function insights()
    {

        if (!class_exists(__NAMESPACE__ . '\Insights')) {
            require_once __DIR__ . '/Insights.php';
        }

        return new Insights($this);
    }

    /**
     * API Endpoint
     *
     * @return string
     */
    
    public function endpoint()
    {
        $endpoint = apply_filters('appsero_endpoint', 'https://api.appsero.com');

        return trailingslashit($endpoint);
    }

    /**
     * Set project basename, slug and version
     *
     * @return void
     */
    protected function set_basename_and_slug()
    {

        // Removed checking for Theme slug

        $this->basename = plugin_basename($this->file);

        list($this->slug, $mainfile) = explode('/', $this->basename);

        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        // $plugin_data = get_plugin_data($this->file);

        $this->project_version = DARKIFY_VERSION;
        $this->type = 'plugin';
        $this->textdomain = $this->slug;
    }

    /**
     * Send request to remote endpoint
     *
     * @param  array  $params
     * @param  string $route
     *
     * @return array|WP_Error   Array of results including HTTP headers or WP_Error if the request failed.
     */
    public function send_request($params, $route, $blocking = false)
    {
        $url = $this->endpoint() . $route;

        $headers = array(
            'user-agent' => 'Appsero/' . md5(esc_url(home_url())) . ';',
            'Accept'     => 'application/json',
        );

        $response = wp_remote_post($url, array(
            'method'      => 'POST',
            'timeout'     => 30,
            'redirection' => 5,
            'httpversion' => '1.0',
            'blocking'    => $blocking,
            'headers'     => $headers,
            'body'        => array_merge($params, array('client' => $this->version)),
            'cookies'     => array()
        ));

        return $response;
    }

    /**
     * Check if the current server is localhost
     *
     * @return boolean
     */
    public function is_local_server()
    {
        return in_array($_SERVER['REMOTE_ADDR'], array('127.0.0.1', '::1'));
    }

    /**
     * Translate function _e()
     */
    public function _etrans($text)
    {
        call_user_func('_e', $text, $this->textdomain);
    }

    /**
     * Translate function __()
     */
    public function darkify_trans($text)
    {
        return call_user_func('__', $text, $this->textdomain);
    }
}
}