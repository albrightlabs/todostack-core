<?php
declare(strict_types=1);

namespace App;

class Config
{
    private static ?Config $instance = null;
    private array $config = [];

    private function __construct()
    {
        $this->loadDefaults();
        $this->loadFromEnv();
    }

    private function loadDefaults(): void
    {
        $this->config = [
            // Site Identity
            'site_name' => 'TodoStack',
            'site_tagline' => 'Simple, fast task management',
            'site_emoji' => '',
            'site_url' => '',

            // Branding - Images
            'logo_url' => '',
            'logo_width' => '120',
            'favicon_url' => '',
            'favicon_emoji' => '',
            'favicon_letter' => '',
            'favicon_show_letter' => true,

            // Branding - Header Link
            'external_link_name' => '',
            'external_link_url' => '',
            'external_link_logo' => '',

            // Branding - Footer
            'footer_text' => '',
            'footer_show_powered_by' => true,

            // Branding - Colors
            'color_primary' => '#3b82f6',
            'color_primary_hover' => '#2563eb',

            // Security
            'admin_password' => '',

            // Features
            'feature_dark_mode' => true,

            // Paths
            'data_path' => dirname(__DIR__) . '/data/todos.json',
        ];
    }

    private function loadFromEnv(): void
    {
        $envMap = [
            // Site Identity
            'SITE_NAME' => 'site_name',
            'SITE_TAGLINE' => 'site_tagline',
            'SITE_EMOJI' => 'site_emoji',
            'SITE_URL' => 'site_url',

            // Branding - Images
            'LOGO_URL' => 'logo_url',
            'LOGO_WIDTH' => 'logo_width',
            'FAVICON_URL' => 'favicon_url',
            'FAVICON_EMOJI' => 'favicon_emoji',
            'FAVICON_LETTER' => 'favicon_letter',
            'FAVICON_SHOW_LETTER' => 'favicon_show_letter',

            // Branding - Header Link
            'EXTERNAL_LINK_NAME' => 'external_link_name',
            'EXTERNAL_LINK_URL' => 'external_link_url',
            'EXTERNAL_LINK_LOGO' => 'external_link_logo',

            // Branding - Footer
            'FOOTER_TEXT' => 'footer_text',
            'FOOTER_SHOW_POWERED_BY' => 'footer_show_powered_by',

            // Branding - Colors
            'COLOR_PRIMARY' => 'color_primary',
            'COLOR_PRIMARY_HOVER' => 'color_primary_hover',

            // Security
            'ADMIN_PASSWORD' => 'admin_password',

            // Features
            'FEATURE_DARK_MODE' => 'feature_dark_mode',
        ];

        foreach ($envMap as $envKey => $configKey) {
            $value = $_ENV[$envKey] ?? $_SERVER[$envKey] ?? null;
            if ($value !== null && $value !== '') {
                // Convert boolean strings
                if ($value === 'true') {
                    $value = true;
                } elseif ($value === 'false') {
                    $value = false;
                }
                $this->config[$configKey] = $value;
            }
        }
    }

    public static function getInstance(): Config
    {
        if (self::$instance === null) {
            self::$instance = new Config();
        }
        return self::$instance;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::getInstance()->config[$key] ?? $default;
    }

    public static function all(): array
    {
        return self::getInstance()->config;
    }

    /**
     * Check if a feature flag is enabled
     */
    public static function feature(string $name): bool
    {
        $value = self::get('feature_' . $name, false);
        if (is_string($value)) {
            return strtolower($value) === 'true' || $value === '1';
        }
        return (bool) $value;
    }

    /**
     * Get all branding-related configuration as an array for templates
     */
    public static function getBranding(): array
    {
        $instance = self::getInstance();
        return [
            'site_name' => $instance->config['site_name'],
            'site_tagline' => $instance->config['site_tagline'],
            'site_emoji' => $instance->config['site_emoji'],
            'site_url' => $instance->config['site_url'],
            'logo_url' => $instance->config['logo_url'],
            'logo_width' => $instance->config['logo_width'],
            'favicon_url' => $instance->config['favicon_url'],
            'favicon_emoji' => $instance->config['favicon_emoji'],
            'favicon_letter' => $instance->config['favicon_letter'],
            'favicon_show_letter' => $instance->config['favicon_show_letter'],
            'external_link_name' => $instance->config['external_link_name'],
            'external_link_url' => $instance->config['external_link_url'],
            'external_link_logo' => $instance->config['external_link_logo'],
            'footer_text' => $instance->config['footer_text'],
            'footer_show_powered_by' => $instance->config['footer_show_powered_by'],
            'color_primary' => $instance->config['color_primary'],
            'color_primary_hover' => $instance->config['color_primary_hover'],
        ];
    }
}
