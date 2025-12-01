# GetCited v1.0.3 — Robots.txt & llms.txt Handling Specification

**Date:** November 30, 2025  
**Target Version:** 1.0.3  
**Depends On:** v1.0.2 completed

---

## Overview

This document specifies improvements to how GetCited handles robots.txt and llms.txt files, including:

1. **Robust plugin detection** — Inspect WordPress hooks to identify what's modifying robots.txt and llms.txt
2. **One-click robots.txt rule injection** — Add GetCited rules to physical robots.txt files
3. **SEO plugin coexistence** — Detect and work alongside Yoast SEO, RankMath, and others
4. **Clean uninstall** — Remove GetCited rules when plugin is deleted
5. **llms.txt conflict detection** — Better handling when another plugin serves llms.txt

---

## Feature #1: Robust Plugin Detection System

### Purpose

Instead of just checking if SEO plugins are active, we inspect the actual WordPress hooks to determine:
- Which plugins are modifying robots.txt output
- Which plugins have registered llms.txt handling
- What priority order they run in
- Whether a physical file is overriding everything

### Implementation

**File:** `includes/class-conflict-detector.php` (NEW FILE)

```php
<?php
/**
 * Conflict detection for GetCited
 * 
 * Detects other plugins that may be handling robots.txt and llms.txt
 *
 * @package GetCited
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Conflict Detector class
 */
class GetCited_Conflict_Detector {

    /**
     * Single instance
     */
    private static $instance = null;

    /**
     * Known plugin patterns for identification
     */
    private $known_plugins = array(
        // Class/function patterns => Plugin info
        'WPSEO' => array(
            'name' => 'Yoast SEO',
            'slug' => 'yoast',
            'robots_editor' => array(
                'path' => 'SEO → Tools → File Editor',
                'url' => 'admin.php?page=wpseo_tools&tool=file-editor',
            ),
        ),
        'RankMath' => array(
            'name' => 'Rank Math',
            'slug' => 'rankmath',
            'robots_editor' => array(
                'path' => 'Rank Math → General Settings → Edit robots.txt',
                'url' => 'admin.php?page=rank-math-options-general#setting-panel-edit-robotstxt',
            ),
        ),
        'AIOSEO' => array(
            'name' => 'All in One SEO',
            'slug' => 'aioseo',
            'robots_editor' => array(
                'path' => 'All in One SEO → Tools → Robots.txt Editor',
                'url' => 'admin.php?page=aioseo-tools#/robots-editor',
            ),
        ),
        'seopress' => array(
            'name' => 'SEOPress',
            'slug' => 'seopress',
            'robots_editor' => array(
                'path' => 'SEO → Tools → robots.txt',
                'url' => 'admin.php?page=seopress-tools',
            ),
        ),
        'Jerl' => array(
            'name' => 'JERL',
            'slug' => 'jerl',
            'robots_editor' => null,
        ),
        'The_SEO_Framework' => array(
            'name' => 'The SEO Framework',
            'slug' => 'tsf',
            'robots_editor' => null,
        ),
        'Starter_SEO' => array(
            'name' => 'Starter SEO',
            'slug' => 'starter-seo',
            'robots_editor' => null,
        ),
    );

    /**
     * Get instance
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Nothing to initialize
    }

    /**
     * Detect all plugins/code hooked into robots_txt filter
     * 
     * @return array List of detected hooks with plugin identification
     */
    public function detect_robots_txt_hooks() {
        global $wp_filter;
        
        $detected = array();
        
        if ( ! isset( $wp_filter['robots_txt'] ) ) {
            return $detected;
        }
        
        foreach ( $wp_filter['robots_txt']->callbacks as $priority => $callbacks ) {
            foreach ( $callbacks as $key => $callback ) {
                $hook_info = $this->analyze_callback( $callback['function'] );
                
                // Skip our own hooks
                if ( $hook_info['is_getcited'] ) {
                    continue;
                }
                
                $hook_info['priority'] = $priority;
                $hook_info['key'] = $key;
                
                $detected[] = $hook_info;
            }
        }
        
        // Sort by priority
        usort( $detected, function( $a, $b ) {
            return $a['priority'] - $b['priority'];
        });
        
        return $detected;
    }

    /**
     * Detect all code that might be handling llms.txt requests
     * 
     * @return array List of detected handlers
     */
    public function detect_llms_txt_hooks() {
        global $wp_filter;
        
        $detected = array();
        
        // Check template_redirect hooks (common place to intercept requests)
        if ( isset( $wp_filter['template_redirect'] ) ) {
            foreach ( $wp_filter['template_redirect']->callbacks as $priority => $callbacks ) {
                foreach ( $callbacks as $key => $callback ) {
                    $hook_info = $this->analyze_callback( $callback['function'] );
                    
                    // Look for llms-related handlers
                    if ( stripos( $hook_info['identifier'], 'llms' ) !== false ) {
                        // Skip our own
                        if ( $hook_info['is_getcited'] ) {
                            continue;
                        }
                        
                        $hook_info['priority'] = $priority;
                        $hook_info['hook'] = 'template_redirect';
                        $detected[] = $hook_info;
                    }
                }
            }
        }
        
        // Check parse_request hooks
        if ( isset( $wp_filter['parse_request'] ) ) {
            foreach ( $wp_filter['parse_request']->callbacks as $priority => $callbacks ) {
                foreach ( $callbacks as $key => $callback ) {
                    $hook_info = $this->analyze_callback( $callback['function'] );
                    
                    if ( stripos( $hook_info['identifier'], 'llms' ) !== false ) {
                        if ( $hook_info['is_getcited'] ) {
                            continue;
                        }
                        
                        $hook_info['priority'] = $priority;
                        $hook_info['hook'] = 'parse_request';
                        $detected[] = $hook_info;
                    }
                }
            }
        }
        
        // Check registered rewrite rules
        $rewrite_handlers = $this->detect_llms_rewrite_rules();
        $detected = array_merge( $detected, $rewrite_handlers );
        
        return $detected;
    }

    /**
     * Check rewrite rules for llms.txt handlers
     * 
     * @return array
     */
    private function detect_llms_rewrite_rules() {
        global $wp_rewrite;
        
        $detected = array();
        $rules = $wp_rewrite->wp_rewrite_rules();
        
        if ( empty( $rules ) ) {
            return $detected;
        }
        
        foreach ( $rules as $pattern => $query ) {
            if ( stripos( $pattern, 'llms' ) !== false || stripos( $query, 'llms' ) !== false ) {
                // Try to trace back who registered this
                $owner = $this->guess_rewrite_owner( $pattern, $query );
                
                $detected[] = array(
                    'type' => 'rewrite_rule',
                    'pattern' => $pattern,
                    'query' => $query,
                    'plugin' => $owner,
                    'is_getcited' => $owner === 'getcited',
                );
            }
        }
        
        return $detected;
    }

    /**
     * Analyze a callback to extract information about it
     * 
     * @param mixed $func The callback function/method
     * @return array Analysis results
     */
    private function analyze_callback( $func ) {
        $result = array(
            'identifier' => '',
            'type' => '',
            'class' => null,
            'method' => null,
            'function' => null,
            'is_getcited' => false,
            'plugin' => null,
            'plugin_info' => null,
        );
        
        // Array callback: array( $object, 'method' ) or array( 'ClassName', 'method' )
        if ( is_array( $func ) ) {
            if ( is_object( $func[0] ) ) {
                $result['class'] = get_class( $func[0] );
                $result['type'] = 'object_method';
            } else {
                $result['class'] = $func[0];
                $result['type'] = 'static_method';
            }
            $result['method'] = $func[1];
            $result['identifier'] = $result['class'] . '::' . $result['method'];
        }
        // String callback: 'function_name'
        elseif ( is_string( $func ) ) {
            $result['function'] = $func;
            $result['identifier'] = $func;
            $result['type'] = 'function';
        }
        // Closure
        elseif ( $func instanceof Closure ) {
            $result['identifier'] = 'Closure';
            $result['type'] = 'closure';
            
            // Try to get more info via reflection
            try {
                $reflection = new ReflectionFunction( $func );
                $file = $reflection->getFileName();
                if ( $file ) {
                    // Try to identify plugin from file path
                    $result['file'] = $file;
                    $result['plugin'] = $this->identify_plugin_from_path( $file );
                }
            } catch ( Exception $e ) {
                // Ignore reflection errors
            }
        }
        // Other callable
        else {
            $result['identifier'] = 'unknown_callable';
            $result['type'] = 'unknown';
        }
        
        // Check if this is GetCited
        $result['is_getcited'] = stripos( $result['identifier'], 'GetCited' ) !== false 
                                 || stripos( $result['identifier'], 'getcited' ) !== false;
        
        // Try to identify the plugin
        if ( ! $result['is_getcited'] && ! $result['plugin'] ) {
            $result['plugin'] = $this->identify_plugin_from_callback( $result['identifier'] );
            if ( $result['plugin'] ) {
                $result['plugin_info'] = $this->get_plugin_info( $result['plugin'] );
            }
        }
        
        return $result;
    }

    /**
     * Identify plugin from callback identifier string
     * 
     * @param string $identifier The callback identifier
     * @return string|null Plugin slug or null
     */
    private function identify_plugin_from_callback( $identifier ) {
        foreach ( $this->known_plugins as $pattern => $info ) {
            if ( stripos( $identifier, $pattern ) !== false ) {
                return $info['slug'];
            }
        }
        
        return null;
    }

    /**
     * Identify plugin from file path
     * 
     * @param string $file_path Full path to a PHP file
     * @return string|null Plugin slug or null
     */
    private function identify_plugin_from_path( $file_path ) {
        // Normalize path
        $file_path = wp_normalize_path( $file_path );
        $plugins_dir = wp_normalize_path( WP_PLUGIN_DIR );
        
        // Check if file is in plugins directory
        if ( strpos( $file_path, $plugins_dir ) !== 0 ) {
            return null;
        }
        
        // Extract plugin folder name
        $relative = substr( $file_path, strlen( $plugins_dir ) + 1 );
        $parts = explode( '/', $relative );
        
        if ( ! empty( $parts[0] ) ) {
            $folder = $parts[0];
            
            // Check against known plugins
            foreach ( $this->known_plugins as $pattern => $info ) {
                if ( stripos( $folder, $info['slug'] ) !== false ) {
                    return $info['slug'];
                }
            }
            
            // Return folder name as-is
            return $folder;
        }
        
        return null;
    }

    /**
     * Try to guess who registered a rewrite rule
     * 
     * @param string $pattern The rewrite pattern
     * @param string $query The rewrite query
     * @return string|null
     */
    private function guess_rewrite_owner( $pattern, $query ) {
        // Check if it's ours
        if ( strpos( $query, 'getcited' ) !== false || strpos( $query, 'llms_txt' ) !== false ) {
            return 'getcited';
        }
        
        // Check known plugins
        foreach ( $this->known_plugins as $key => $info ) {
            if ( stripos( $query, $info['slug'] ) !== false ) {
                return $info['slug'];
            }
        }
        
        return null;
    }

    /**
     * Get detailed plugin info by slug
     * 
     * @param string $slug Plugin slug
     * @return array|null Plugin info or null
     */
    public function get_plugin_info( $slug ) {
        foreach ( $this->known_plugins as $pattern => $info ) {
            if ( $info['slug'] === $slug ) {
                return array(
                    'name' => $info['name'],
                    'slug' => $info['slug'],
                    'robots_editor' => isset( $info['robots_editor'] ) ? $info['robots_editor'] : null,
                );
            }
        }
        
        // Unknown plugin - return basic info
        return array(
            'name' => ucfirst( str_replace( '-', ' ', $slug ) ),
            'slug' => $slug,
            'robots_editor' => null,
        );
    }

    /**
     * Get summary of robots.txt handling situation
     * 
     * @return array Summary with status and details
     */
    public function get_robots_txt_summary() {
        $summary = array(
            'physical_file_exists' => file_exists( ABSPATH . 'robots.txt' ),
            'physical_file_writable' => is_writable( ABSPATH . 'robots.txt' ) || ( ! file_exists( ABSPATH . 'robots.txt' ) && is_writable( ABSPATH ) ),
            'hooks_detected' => array(),
            'seo_plugin_with_editor' => null,
            'getcited_priority' => null,
            'potential_conflicts' => array(),
        );
        
        // Detect hooks
        $hooks = $this->detect_robots_txt_hooks();
        $summary['hooks_detected'] = $hooks;
        
        // Find GetCited's priority
        global $wp_filter;
        if ( isset( $wp_filter['robots_txt'] ) ) {
            foreach ( $wp_filter['robots_txt']->callbacks as $priority => $callbacks ) {
                foreach ( $callbacks as $key => $callback ) {
                    $info = $this->analyze_callback( $callback['function'] );
                    if ( $info['is_getcited'] ) {
                        $summary['getcited_priority'] = $priority;
                        break 2;
                    }
                }
            }
        }
        
        // Find SEO plugin with editor
        foreach ( $hooks as $hook ) {
            if ( $hook['plugin_info'] && ! empty( $hook['plugin_info']['robots_editor'] ) ) {
                $summary['seo_plugin_with_editor'] = $hook['plugin_info'];
                break;
            }
        }
        
        // Identify potential conflicts
        foreach ( $hooks as $hook ) {
            if ( $hook['plugin'] && $summary['getcited_priority'] !== null ) {
                // If another plugin runs after GetCited, it might override our rules
                if ( $hook['priority'] > $summary['getcited_priority'] ) {
                    $summary['potential_conflicts'][] = array(
                        'plugin' => $hook['plugin_info'] ? $hook['plugin_info']['name'] : $hook['plugin'],
                        'issue' => 'runs_after_getcited',
                        'priority' => $hook['priority'],
                    );
                }
            }
        }
        
        return $summary;
    }

    /**
     * Get summary of llms.txt handling situation
     * 
     * @return array Summary with status and details
     */
    public function get_llms_txt_summary() {
        $summary = array(
            'physical_file_exists' => file_exists( ABSPATH . 'llms.txt' ),
            'handlers_detected' => array(),
            'getcited_registered' => false,
            'other_handlers' => array(),
        );
        
        // Detect handlers
        $handlers = $this->detect_llms_txt_hooks();
        $summary['handlers_detected'] = $handlers;
        
        // Categorize handlers
        foreach ( $handlers as $handler ) {
            if ( isset( $handler['is_getcited'] ) && $handler['is_getcited'] ) {
                $summary['getcited_registered'] = true;
            } elseif ( isset( $handler['plugin'] ) && $handler['plugin'] === 'getcited' ) {
                $summary['getcited_registered'] = true;
            } else {
                $summary['other_handlers'][] = $handler;
            }
        }
        
        return $summary;
    }

    /**
     * Check if any detected plugin is known to have an llms.txt feature
     * 
     * @return array|null Plugin info or null
     */
    public function detect_llms_txt_plugin() {
        // Currently no major SEO plugins have llms.txt support
        // This is future-proofing for when they add it
        $llms_plugins = array(
            'llms-txt' => array(
                'name' => 'LLMs.txt Plugin',
                'constant' => 'LLMS_TXT_VERSION',
            ),
            'jerl' => array(
                'name' => 'JERL',
                'constant' => 'JERL_VERSION',
            ),
        );
        
        foreach ( $llms_plugins as $slug => $info ) {
            if ( defined( $info['constant'] ) ) {
                return array(
                    'slug' => $slug,
                    'name' => $info['name'],
                );
            }
        }
        
        return null;
    }
}
```

---

## Feature #2: Updated Health Check Using Conflict Detector

**File:** `includes/class-health-check.php`

**Update `check_robots_txt()` method:**

```php
/**
 * Check robots.txt for our rules
 */
private function check_robots_txt() {
    $robots = GetCited_Robots::instance();
    $detector = GetCited_Conflict_Detector::instance();
    $summary = $detector->get_robots_txt_summary();
    
    // Check if site discourages search engines
    $is_public = get_option( 'blog_public' );
    if ( ! $is_public ) {
        return array(
            'status' => 'error',
            'message' => __( 'Site is set to discourage search engines', 'getcited' ),
            'details' => __( 'WordPress is blocking all crawlers. Go to Settings → Reading and uncheck "Discourage search engines from indexing this site" to allow AI crawlers.', 'getcited' ),
            'action_type' => 'settings_link',
            'action_url' => admin_url( 'options-reading.php' ),
            'action_label' => __( 'Go to Reading Settings', 'getcited' ),
        );
    }
    
    // Check for physical robots.txt file
    if ( $summary['physical_file_exists'] ) {
        $rules_exist = $robots->rules_exist_in_physical_file();
        
        // Rules already present
        if ( $rules_exist ) {
            $result = array(
                'status' => 'ok',
                'message' => __( 'GetCited rules present in robots.txt', 'getcited' ),
            );
            
            // Note if SEO plugin editor is available
            if ( $summary['seo_plugin_with_editor'] ) {
                $result['details'] = sprintf(
                    /* translators: %s: SEO plugin name */
                    __( 'Physical file managed alongside %s. Edit via GetCited Crawlers page or the SEO plugin editor.', 'getcited' ),
                    $summary['seo_plugin_with_editor']['name']
                );
            }
            
            return $result;
        }
        
        // Physical file exists, rules not present
        $result = array(
            'status' => 'warning',
            'message' => __( 'Physical robots.txt exists — GetCited rules not added yet', 'getcited' ),
            'rules' => $robots->generate_rules(),
            'can_write' => $summary['physical_file_writable'],
        );
        
        // Build details message
        if ( $summary['seo_plugin_with_editor'] ) {
            $result['details'] = sprintf(
                /* translators: %s: SEO plugin name */
                __( 'A robots.txt file exists, likely managed by %s. You can add GetCited rules automatically, or add them through your SEO plugin editor.', 'getcited' ),
                $summary['seo_plugin_with_editor']['name']
            );
            $result['seo_plugin'] = $summary['seo_plugin_with_editor'];
        } elseif ( ! empty( $summary['hooks_detected'] ) ) {
            // Other plugins detected but no known editor
            $plugin_names = array();
            foreach ( $summary['hooks_detected'] as $hook ) {
                if ( $hook['plugin_info'] ) {
                    $plugin_names[] = $hook['plugin_info']['name'];
                } elseif ( $hook['plugin'] ) {
                    $plugin_names[] = ucfirst( $hook['plugin'] );
                }
            }
            $plugin_names = array_unique( $plugin_names );
            
            if ( ! empty( $plugin_names ) ) {
                $result['details'] = sprintf(
                    /* translators: %s: comma-separated list of plugin names */
                    __( 'A robots.txt file exists. Detected plugins that may modify it: %s. You can add GetCited rules automatically.', 'getcited' ),
                    implode( ', ', $plugin_names )
                );
                $result['detected_plugins'] = $plugin_names;
            } else {
                $result['details'] = __( 'A physical robots.txt file exists in your site root. GetCited can add its rules automatically.', 'getcited' );
            }
        } else {
            $result['details'] = __( 'A physical robots.txt file exists in your site root. GetCited can add its rules automatically.', 'getcited' );
        }
        
        // Set action type based on write capability
        if ( $summary['physical_file_writable'] ) {
            $result['action_type'] = 'auto_add';
            $result['action_label'] = __( 'Add Rules to robots.txt', 'getcited' );
        } else {
            $result['action_type'] = 'copy_rules';
            $result['details'] .= ' ' . __( 'Automatic addition is not available due to file permissions.', 'getcited' );
        }
        
        return $result;
    }
    
    // No physical file - using WordPress filter
    // Fetch and check robots.txt content
    $url = home_url( '/robots.txt' );
    
    $response = wp_remote_get( $url, array(
        'timeout' => 5,
        'sslverify' => false,
    ) );

    if ( is_wp_error( $response ) ) {
        return array(
            'status' => 'error',
            'message' => __( 'Could not fetch robots.txt', 'getcited' ),
            'details' => $response->get_error_message(),
        );
    }

    $code = wp_remote_retrieve_response_code( $response );
    $body = wp_remote_retrieve_body( $response );

    if ( $code !== 200 ) {
        return array(
            'status' => 'error',
            /* translators: %d: HTTP response code */
            'message' => sprintf( __( 'robots.txt returned HTTP %d', 'getcited' ), $code ),
            'details' => __( 'The robots.txt file could not be loaded. Check your server configuration.', 'getcited' ),
        );
    }

    // Check for our marker
    if ( strpos( $body, '# === GetCited AI Crawler Rules ===' ) === false ) {
        // Check if other hooks might be interfering
        if ( ! empty( $summary['potential_conflicts'] ) ) {
            $conflict_names = array_map( function( $c ) { 
                return $c['plugin']; 
            }, $summary['potential_conflicts'] );
            
            return array(
                'status' => 'warning',
                'message' => __( 'GetCited rules may be overwritten', 'getcited' ),
                'details' => sprintf(
                    /* translators: %s: comma-separated list of plugin names */
                    __( 'The following plugins run after GetCited and may be removing or overwriting the rules: %s. Try adjusting plugin load order or contact support.', 'getcited' ),
                    implode( ', ', $conflict_names )
                ),
                'hooks_info' => $summary['hooks_detected'],
            );
        }
        
        return array(
            'status' => 'warning',
            'message' => __( 'GetCited rules not found in robots.txt', 'getcited' ),
            'details' => __( 'The rules should be added automatically. Try deactivating and reactivating GetCited, or flush your permalinks by visiting Settings → Permalinks and clicking Save.', 'getcited' ),
            'action_type' => 'permalinks_link',
            'action_url' => admin_url( 'options-permalink.php' ),
            'action_label' => __( 'Go to Permalinks', 'getcited' ),
            'rules' => $robots->generate_rules(),
        );
    }

    // All good - rules present
    $result = array(
        'status' => 'ok',
        'message' => __( 'robots.txt includes GetCited rules', 'getcited' ),
    );
    
    // Note other hooks if present
    if ( ! empty( $summary['hooks_detected'] ) ) {
        $plugin_names = array();
        foreach ( $summary['hooks_detected'] as $hook ) {
            if ( $hook['plugin_info'] ) {
                $plugin_names[] = $hook['plugin_info']['name'];
            }
        }
        $plugin_names = array_unique( $plugin_names );
        
        if ( ! empty( $plugin_names ) ) {
            $result['details'] = sprintf(
                /* translators: %s: comma-separated list of plugin names */
                __( 'Also detected: %s. All plugins are cooperating correctly.', 'getcited' ),
                implode( ', ', $plugin_names )
            );
        }
    }
    
    return $result;
}
```

**Update `check_llms_txt()` method:**

```php
/**
 * Check llms.txt accessibility
 */
private function check_llms_txt() {
    $settings = GetCited_Settings::instance();
    $detector = GetCited_Conflict_Detector::instance();
    $summary = $detector->get_llms_txt_summary();
    
    if ( ! $settings->get( 'llms_txt_enabled' ) ) {
        return array(
            'status' => 'disabled',
            'message' => __( 'llms.txt is disabled', 'getcited' ),
            'action_type' => 'settings_link',
            'action_url' => admin_url( 'admin.php?page=getcited-llms-txt' ),
            'action_label' => __( 'Enable llms.txt', 'getcited' ),
        );
    }

    // Check for physical llms.txt file first
    if ( $summary['physical_file_exists'] ) {
        $file_path = ABSPATH . 'llms.txt';
        $content = file_get_contents( $file_path );
        
        // Check if it's ours
        if ( strpos( $content, '# Generated by GetCited' ) !== false ) {
            return array(
                'status' => 'ok',
                'message' => __( 'llms.txt physical file present (GetCited)', 'getcited' ),
                'url' => home_url( '/llms.txt' ),
            );
        }
        
        // Physical file exists but not ours
        $source = $this->identify_llms_txt_source( $content );
        
        return array(
            'status' => 'warning',
            'message' => __( 'Physical llms.txt file exists', 'getcited' ),
            'details' => $source
                ? sprintf(
                    /* translators: %s: identified source */
                    __( 'A physical llms.txt file exists, possibly created by %s. This takes precedence over GetCited. Delete the physical file to use GetCited, or edit it directly.', 'getcited' ),
                    $source
                )
                : __( 'A physical llms.txt file in your site root takes precedence over GetCited. Delete the physical file to use GetCited, or edit it to include your desired content.', 'getcited' ),
            'file_path' => $file_path,
            'action_type' => 'copy_content',
            'content' => $settings->get( 'llms_txt_content' ),
            'current_content_preview' => substr( $content, 0, 500 ),
        );
    }

    // Check for other handlers
    if ( ! empty( $summary['other_handlers'] ) ) {
        // Another plugin is handling llms.txt
        $other_plugin = null;
        foreach ( $summary['other_handlers'] as $handler ) {
            if ( ! empty( $handler['plugin'] ) ) {
                $other_plugin = $detector->get_plugin_info( $handler['plugin'] );
                break;
            }
        }
        
        // Still fetch to see what's actually served
        $url = home_url( '/llms.txt' );
        $response = wp_remote_get( $url, array( 'timeout' => 5, 'sslverify' => false ) );
        
        if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
            $body = wp_remote_retrieve_body( $response );
            
            // Check if GetCited marker is present
            if ( strpos( $body, '# Generated by GetCited' ) !== false ) {
                return array(
                    'status' => 'ok',
                    'message' => __( 'llms.txt is accessible and working', 'getcited' ),
                    'details' => $other_plugin 
                        ? sprintf(
                            /* translators: %s: plugin name */
                            __( 'Note: %s also has llms.txt handling registered, but GetCited is currently serving the file.', 'getcited' ),
                            $other_plugin['name']
                        )
                        : null,
                    'url' => $url,
                );
            }
            
            // Another plugin is serving llms.txt
            return array(
                'status' => 'warning',
                'message' => __( 'llms.txt served by another plugin', 'getcited' ),
                'details' => $other_plugin
                    ? sprintf(
                        /* translators: %s: plugin name */
                        __( '%s is currently serving llms.txt. Disable their llms.txt feature to use GetCited, or configure your content through their interface.', 'getcited' ),
                        $other_plugin['name']
                    )
                    : __( 'Another plugin is serving llms.txt. You may need to disable their llms.txt feature to use GetCited.', 'getcited' ),
                'url' => $url,
                'content_preview' => substr( $body, 0, 500 ),
                'other_plugin' => $other_plugin,
            );
        }
    }

    // Standard fetch check
    $url = home_url( '/llms.txt' );
    
    $response = wp_remote_get( $url, array(
        'timeout' => 5,
        'sslverify' => false,
    ) );

    if ( is_wp_error( $response ) ) {
        return array(
            'status' => 'error',
            'message' => __( 'Could not fetch llms.txt', 'getcited' ),
            'details' => $response->get_error_message(),
            'url' => $url,
        );
    }

    $code = wp_remote_retrieve_response_code( $response );
    $body = wp_remote_retrieve_body( $response );

    if ( $code === 404 ) {
        return array(
            'status' => 'error',
            'message' => __( 'llms.txt returns 404 Not Found', 'getcited' ),
            'details' => __( 'The rewrite rules may need flushing. Go to Settings → Permalinks and click Save Changes.', 'getcited' ),
            'action_type' => 'permalinks_link',
            'action_url' => admin_url( 'options-permalink.php' ),
            'action_label' => __( 'Go to Permalinks', 'getcited' ),
            'url' => $url,
        );
    }

    if ( $code !== 200 ) {
        return array(
            'status' => 'error',
            /* translators: %d: HTTP response code */
            'message' => sprintf( __( 'llms.txt returned HTTP %d', 'getcited' ), $code ),
            'url' => $url,
        );
    }

    // Check for our marker
    if ( strpos( $body, '# Generated by GetCited' ) === false ) {
        $source = $this->identify_llms_txt_source( $body );
        
        return array(
            'status' => 'warning',
            'message' => __( 'llms.txt served by another source', 'getcited' ),
            'details' => $source 
                ? sprintf( 
                    /* translators: %s: name of conflicting source */
                    __( '%s appears to be serving llms.txt. Disable their llms.txt feature or use theirs instead.', 'getcited' ),
                    $source
                )
                : __( 'The llms.txt file is accessible but not generated by GetCited. Another plugin or a physical file may be serving it.', 'getcited' ),
            'url' => $url,
            'content_preview' => substr( $body, 0, 500 ),
        );
    }

    return array(
        'status' => 'ok',
        'message' => __( 'llms.txt is accessible and working', 'getcited' ),
        'url' => $url,
    );
}

/**
 * Try to identify the source of llms.txt content
 * 
 * @param string $content The llms.txt content
 * @return string|false Source name or false if unknown
 */
private function identify_llms_txt_source( $content ) {
    // Check for known signatures
    $signatures = array(
        'Yoast SEO' => array( 'yoast', 'Yoast' ),
        'Rank Math' => array( 'rank-math', 'Rank Math', 'rankmath' ),
        'All in One SEO' => array( 'aioseo', 'All in One SEO', 'AIOSEO' ),
        'SEOPress' => array( 'seopress', 'SEOPress' ),
        'JERL' => array( 'jerl', 'JERL' ),
        'LLMs.txt Plugin' => array( 'llms-txt', 'LLMs.txt' ),
        'WP LLMs' => array( 'wp-llms', 'WP LLMs' ),
    );
    
    foreach ( $signatures as $name => $keywords ) {
        foreach ( $keywords as $keyword ) {
            if ( stripos( $content, $keyword ) !== false ) {
                return $name;
            }
        }
    }
    
    // Check if it looks like a manual/physical file
    if ( strpos( $content, '# Generated' ) === false ) {
        return __( 'Physical file or unknown source', 'getcited' );
    }
    
    return false;
}
```

---

## Feature #3: One-Click Robots.txt Rule Injection

**File:** `includes/class-robots.php`

**Add new methods:**

```php
/**
 * Check if we can write to robots.txt
 * 
 * @return bool
 */
public function can_write_physical_file() {
    $file_path = ABSPATH . 'robots.txt';
    
    // If file exists, check if writable
    if ( file_exists( $file_path ) ) {
        return is_writable( $file_path );
    }
    
    // If file doesn't exist, check if directory is writable
    return is_writable( ABSPATH );
}

/**
 * Add GetCited rules to physical robots.txt file
 * 
 * @return array Result with success status and message
 */
public function add_rules_to_physical_file() {
    $file_path = ABSPATH . 'robots.txt';
    
    // Check write permissions
    if ( ! $this->can_write_physical_file() ) {
        return array(
            'success' => false,
            'message' => __( 'Cannot write to robots.txt. Check file permissions.', 'getcited' ),
        );
    }
    
    // Initialize WP_Filesystem
    global $wp_filesystem;
    if ( ! function_exists( 'WP_Filesystem' ) ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }
    
    if ( ! WP_Filesystem() ) {
        return array(
            'success' => false,
            'message' => __( 'Could not initialize filesystem. Try again or add rules manually.', 'getcited' ),
        );
    }
    
    // Get current content or empty string
    $content = '';
    if ( $wp_filesystem->exists( $file_path ) ) {
        $content = $wp_filesystem->get_contents( $file_path );
        if ( $content === false ) {
            return array(
                'success' => false,
                'message' => __( 'Could not read existing robots.txt file.', 'getcited' ),
            );
        }
    }
    
    // Generate our rules
    $rules = $this->generate_rules();
    $marker_start = '# === GetCited AI Crawler Rules ===';
    $marker_end = '# === End GetCited Rules ===';
    
    // Check if our rules already exist
    if ( strpos( $content, $marker_start ) !== false ) {
        // Replace existing rules
        $pattern = '/' . preg_quote( $marker_start, '/' ) . '.*?' . preg_quote( $marker_end, '/' ) . '/s';
        $new_content = preg_replace( $pattern, $rules, $content );
        $action = 'updated';
    } else {
        // Append rules (with spacing)
        $new_content = rtrim( $content );
        if ( ! empty( $new_content ) ) {
            $new_content .= "\n\n";
        }
        $new_content .= $rules;
        $action = 'added';
    }
    
    // Write file
    $result = $wp_filesystem->put_contents( $file_path, $new_content, FS_CHMOD_FILE );
    
    if ( ! $result ) {
        return array(
            'success' => false,
            'message' => __( 'Failed to write robots.txt file.', 'getcited' ),
        );
    }
    
    // Clear health check cache so it re-evaluates
    delete_transient( 'getcited_health_status' );
    
    return array(
        'success' => true,
        'action' => $action,
        'message' => $action === 'updated' 
            ? __( 'GetCited rules updated in robots.txt', 'getcited' )
            : __( 'GetCited rules added to robots.txt', 'getcited' ),
    );
}

/**
 * Remove GetCited rules from physical robots.txt file
 * 
 * @return array Result with success status and message
 */
public function remove_rules_from_physical_file() {
    $file_path = ABSPATH . 'robots.txt';
    
    // Check if file exists
    if ( ! file_exists( $file_path ) ) {
        return array(
            'success' => true,
            'message' => __( 'No robots.txt file exists.', 'getcited' ),
        );
    }
    
    // Check write permissions
    if ( ! is_writable( $file_path ) ) {
        return array(
            'success' => false,
            'message' => __( 'Cannot write to robots.txt. Check file permissions.', 'getcited' ),
        );
    }
    
    // Initialize WP_Filesystem
    global $wp_filesystem;
    if ( ! function_exists( 'WP_Filesystem' ) ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }
    
    if ( ! WP_Filesystem() ) {
        return array(
            'success' => false,
            'message' => __( 'Could not initialize filesystem.', 'getcited' ),
        );
    }
    
    $content = $wp_filesystem->get_contents( $file_path );
    if ( $content === false ) {
        return array(
            'success' => false,
            'message' => __( 'Could not read robots.txt file.', 'getcited' ),
        );
    }
    
    $marker_start = '# === GetCited AI Crawler Rules ===';
    $marker_end = '# === End GetCited Rules ===';
    
    // Check if our rules exist
    if ( strpos( $content, $marker_start ) === false ) {
        return array(
            'success' => true,
            'message' => __( 'No GetCited rules found in robots.txt.', 'getcited' ),
        );
    }
    
    // Remove our rules section (including surrounding whitespace)
    $pattern = '/\n*' . preg_quote( $marker_start, '/' ) . '.*?' . preg_quote( $marker_end, '/' ) . '\n*/s';
    $new_content = preg_replace( $pattern, "\n", $content );
    $new_content = trim( $new_content ) . "\n";
    
    // Write file
    $result = $wp_filesystem->put_contents( $file_path, $new_content, FS_CHMOD_FILE );
    
    if ( ! $result ) {
        return array(
            'success' => false,
            'message' => __( 'Failed to update robots.txt file.', 'getcited' ),
        );
    }
    
    return array(
        'success' => true,
        'message' => __( 'GetCited rules removed from robots.txt', 'getcited' ),
    );
}

/**
 * Check if GetCited rules exist in physical robots.txt
 * 
 * @return bool
 */
public function rules_exist_in_physical_file() {
    $file_path = ABSPATH . 'robots.txt';
    
    if ( ! file_exists( $file_path ) ) {
        return false;
    }
    
    $content = file_get_contents( $file_path );
    return strpos( $content, '# === GetCited AI Crawler Rules ===' ) !== false;
}
```

---

## Feature #4: AJAX Handlers

**File:** `includes/class-dashboard.php`

**Add to constructor:**

```php
add_action( 'wp_ajax_getcited_add_robots_rules', array( $this, 'ajax_add_robots_rules' ) );
add_action( 'wp_ajax_getcited_remove_robots_rules', array( $this, 'ajax_remove_robots_rules' ) );
```

**Add methods:**

```php
/**
 * AJAX: Add rules to physical robots.txt
 */
public function ajax_add_robots_rules() {
    check_ajax_referer( 'getcited_admin', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'Permission denied' ) );
    }

    $robots = GetCited_Robots::instance();
    $result = $robots->add_rules_to_physical_file();

    if ( $result['success'] ) {
        wp_send_json_success( $result );
    } else {
        wp_send_json_error( $result );
    }
}

/**
 * AJAX: Remove rules from physical robots.txt
 */
public function ajax_remove_robots_rules() {
    check_ajax_referer( 'getcited_admin', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'Permission denied' ) );
    }

    $robots = GetCited_Robots::instance();
    $result = $robots->remove_rules_from_physical_file();

    if ( $result['success'] ) {
        wp_send_json_success( $result );
    } else {
        wp_send_json_error( $result );
    }
}
```

---

## Feature #5: Dashboard UI for Auto-Add

**File:** `templates/dashboard.php`

**Update health check details section:**

```php
<?php if ( ! empty( $check['action_type'] ) ) : ?>
    <div class="details-actions">
        
        <?php if ( $check['action_type'] === 'auto_add' ) : ?>
            <!-- One-click add button -->
            <button type="button" class="button button-primary getcited-add-robots-rules">
                <span class="dashicons dashicons-plus-alt2"></span>
                <?php echo esc_html( $check['action_label'] ); ?>
            </button>
            <span class="getcited-action-status"></span>
            
            <?php if ( ! empty( $check['seo_plugin'] ) ) : ?>
                <p class="alternative-action">
                    <?php esc_html_e( 'Or add manually via:', 'getcited' ); ?>
                    <a href="<?php echo esc_url( admin_url( $check['seo_plugin']['robots_editor']['url'] ) ); ?>">
                        <?php echo esc_html( $check['seo_plugin']['robots_editor']['path'] ); ?>
                    </a>
                </p>
            <?php endif; ?>
            
            <?php if ( ! empty( $check['detected_plugins'] ) ) : ?>
                <p class="detected-plugins">
                    <?php 
                    printf(
                        /* translators: %s: comma-separated list of plugin names */
                        esc_html__( 'Detected plugins modifying robots.txt: %s', 'getcited' ),
                        esc_html( implode( ', ', $check['detected_plugins'] ) )
                    ); 
                    ?>
                </p>
            <?php endif; ?>
            
            <!-- Expandable rules preview -->
            <button type="button" class="button getcited-toggle-rules">
                <?php esc_html_e( 'Preview Rules', 'getcited' ); ?>
            </button>
            <div class="getcited-rules-preview" style="display: none;">
                <pre class="rules-code"><?php echo esc_html( $check['rules'] ); ?></pre>
            </div>
            
        <?php elseif ( $check['action_type'] === 'copy_rules' && ! empty( $check['rules'] ) ) : ?>
            <!-- Copy rules (fallback when can't write) -->
            <div class="getcited-rules-preview">
                <pre class="rules-code"><?php echo esc_html( $check['rules'] ); ?></pre>
                <button type="button" class="button getcited-copy-rules" data-rules="<?php echo esc_attr( $check['rules'] ); ?>">
                    <span class="dashicons dashicons-clipboard"></span>
                    <?php esc_html_e( 'Copy Rules to Clipboard', 'getcited' ); ?>
                </button>
            </div>
            
            <?php if ( ! empty( $check['seo_plugin'] ) ) : ?>
                <p class="paste-location">
                    <?php esc_html_e( 'Paste into:', 'getcited' ); ?>
                    <a href="<?php echo esc_url( admin_url( $check['seo_plugin']['robots_editor']['url'] ) ); ?>">
                        <?php echo esc_html( $check['seo_plugin']['robots_editor']['path'] ); ?>
                    </a>
                </p>
            <?php endif; ?>
            
        <?php elseif ( $check['action_type'] === 'copy_content' && ! empty( $check['content'] ) ) : ?>
            <!-- Copy llms.txt content -->
            <p class="current-content-label"><?php esc_html_e( 'Current file content (first 500 chars):', 'getcited' ); ?></p>
            <pre class="content-preview"><?php echo esc_html( $check['current_content_preview'] ); ?></pre>
            
            <p class="getcited-content-label"><?php esc_html_e( 'GetCited content to use:', 'getcited' ); ?></p>
            <div class="getcited-rules-preview">
                <pre class="rules-code"><?php echo esc_html( $check['content'] ); ?></pre>
                <button type="button" class="button getcited-copy-rules" data-rules="<?php echo esc_attr( $check['content'] ); ?>">
                    <span class="dashicons dashicons-clipboard"></span>
                    <?php esc_html_e( 'Copy Content to Clipboard', 'getcited' ); ?>
                </button>
            </div>
            
        <?php elseif ( ! empty( $check['action_url'] ) && ! empty( $check['action_label'] ) ) : ?>
            <!-- Link to settings -->
            <a href="<?php echo esc_url( $check['action_url'] ); ?>" class="button">
                <?php echo esc_html( $check['action_label'] ); ?>
            </a>
        <?php endif; ?>
        
        <?php if ( ! empty( $check['content_preview'] ) && $check['action_type'] !== 'copy_content' ) : ?>
            <div class="conflict-preview">
                <p class="preview-label"><?php esc_html_e( 'Current content preview:', 'getcited' ); ?></p>
                <pre class="content-preview"><?php echo esc_html( $check['content_preview'] ); ?></pre>
            </div>
        <?php endif; ?>
        
    </div>
<?php endif; ?>
```

---

## Feature #6: JavaScript Handlers

**File:** `assets/js/admin.js`

**Add to `initHealthCheck()` function:**

```javascript
// Add rules to robots.txt button
healthSection.querySelectorAll('.getcited-add-robots-rules').forEach(btn => {
    btn.addEventListener('click', function() {
        const statusEl = this.nextElementSibling;
        const originalHTML = this.innerHTML;
        
        this.disabled = true;
        this.innerHTML = '<span class="dashicons dashicons-update spin"></span> Adding...';
        
        ajax('getcited_add_robots_rules')
            .then(response => {
                this.disabled = false;
                
                if (response.success) {
                    this.innerHTML = '<span class="dashicons dashicons-yes"></span> ' + response.data.message;
                    this.classList.add('success');
                    
                    // Reload after short delay to show updated status
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    this.innerHTML = originalHTML;
                    if (statusEl && statusEl.classList.contains('getcited-action-status')) {
                        statusEl.textContent = response.data.message;
                        statusEl.classList.add('error');
                    }
                }
            })
            .catch(error => {
                this.disabled = false;
                this.innerHTML = originalHTML;
                console.error('Add robots rules error:', error);
                if (statusEl && statusEl.classList.contains('getcited-action-status')) {
                    statusEl.textContent = 'An error occurred. Please try again.';
                    statusEl.classList.add('error');
                }
            });
    });
});

// Toggle rules preview
healthSection.querySelectorAll('.getcited-toggle-rules').forEach(btn => {
    btn.addEventListener('click', function() {
        const preview = this.nextElementSibling;
        if (preview && preview.classList.contains('getcited-rules-preview')) {
            const isHidden = preview.style.display === 'none';
            preview.style.display = isHidden ? 'block' : 'none';
            this.textContent = isHidden ? 'Hide Rules' : 'Preview Rules';
        }
    });
});

// Copy to clipboard buttons
healthSection.querySelectorAll('.getcited-copy-rules').forEach(btn => {
    btn.addEventListener('click', function() {
        const rules = this.dataset.rules;
        
        navigator.clipboard.writeText(rules).then(() => {
            const originalHTML = this.innerHTML;
            this.innerHTML = '<span class="dashicons dashicons-yes"></span> ' + getcitedAdmin.strings.copied;
            this.classList.add('copied');
            
            setTimeout(() => {
                this.innerHTML = originalHTML;
                this.classList.remove('copied');
            }, 2000);
        }).catch(err => {
            console.error('Clipboard write failed:', err);
            // Fallback for older browsers
            const textarea = document.createElement('textarea');
            textarea.value = rules;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            
            try {
                document.execCommand('copy');
                const originalHTML = this.innerHTML;
                this.innerHTML = '<span class="dashicons dashicons-yes"></span> ' + getcitedAdmin.strings.copied;
                setTimeout(() => {
                    this.innerHTML = originalHTML;
                }, 2000);
            } catch (e) {
                console.error('Fallback copy failed:', e);
            }
            
            document.body.removeChild(textarea);
        });
    });
});
```

---

## Feature #7: CSS Additions

**File:** `assets/css/admin.css`

```css
/* ==========================================================================
   Health Check - Auto Add & Conflict Detection
   ========================================================================== */

.dashicons.spin {
    animation: getcited-spin 1s linear infinite;
}

@keyframes getcited-spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.getcited-add-robots-rules {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.getcited-add-robots-rules .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
}

.getcited-add-robots-rules.success {
    background-color: var(--getcited-success);
    border-color: var(--getcited-success);
    color: #fff;
    pointer-events: none;
}

.getcited-action-status {
    margin-left: var(--getcited-space-sm);
    font-size: 13px;
    vertical-align: middle;
}

.getcited-action-status.error {
    color: var(--getcited-error);
}

.alternative-action,
.paste-location,
.detected-plugins {
    margin-top: var(--getcited-space-sm);
    font-size: 12px;
    color: var(--getcited-gray-500);
}

.alternative-action a,
.paste-location a {
    color: var(--getcited-primary);
    text-decoration: none;
}

.alternative-action a:hover,
.paste-location a:hover {
    text-decoration: underline;
}

.getcited-toggle-rules {
    margin-top: var(--getcited-space-md);
}

.getcited-rules-preview {
    margin-top: var(--getcited-space-md);
    background: var(--getcited-gray-800);
    border-radius: var(--getcited-radius-sm);
    padding: var(--getcited-space-md);
}

.getcited-rules-preview .rules-code {
    margin: 0 0 var(--getcited-space-md) 0;
    padding: 0;
    background: transparent;
    color: var(--getcited-gray-100);
    font-family: 'SF Mono', 'Monaco', 'Consolas', monospace;
    font-size: 11px;
    line-height: 1.5;
    white-space: pre-wrap;
    max-height: 250px;
    overflow-y: auto;
}

.getcited-rules-preview .getcited-copy-rules {
    background: var(--getcited-gray-700);
    border-color: var(--getcited-gray-600);
    color: var(--getcited-gray-100);
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.getcited-rules-preview .getcited-copy-rules:hover {
    background: var(--getcited-gray-600);
    border-color: var(--getcited-gray-500);
    color: #fff;
}

.getcited-rules-preview .getcited-copy-rules.copied {
    background: var(--getcited-success);
    border-color: var(--getcited-success);
}

.getcited-rules-preview .getcited-copy-rules .dashicons {
    font-size: 14px;
    width: 14px;
    height: 14px;
}

.conflict-preview,
.current-content-label,
.getcited-content-label {
    margin-top: var(--getcited-space-md);
}

.preview-label,
.current-content-label,
.getcited-content-label {
    font-size: 12px;
    color: var(--getcited-gray-500);
    margin-bottom: var(--getcited-space-xs);
}

.content-preview {
    background: var(--getcited-gray-100);
    border: 1px solid var(--getcited-gray-200);
    border-radius: var(--getcited-radius-sm);
    padding: var(--getcited-space-sm);
    font-family: 'SF Mono', 'Monaco', 'Consolas', monospace;
    font-size: 11px;
    line-height: 1.5;
    white-space: pre-wrap;
    max-height: 150px;
    overflow-y: auto;
    color: var(--getcited-gray-600);
}
```

---

## Feature #8: Clean Uninstall

**File:** `uninstall.php`

**Full replacement:**

```php
<?php
/**
 * GetCited Uninstall
 *
 * @package GetCited
 */

// If uninstall not called from WordPress, exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Get settings to check keep_on_delete preference
$settings = get_option( 'getcited_settings', array() );
$keep_on_delete = isset( $settings['keep_on_delete'] ) && $settings['keep_on_delete'];

if ( $keep_on_delete ) {
    // Minimal cleanup - only transients and cron
    delete_transient( 'getcited_crawler_list' );
    delete_transient( 'getcited_health_status' );
    delete_transient( 'getcited_llms_txt_status' );
    wp_clear_scheduled_hook( 'getcited_daily_cron' );
    return;
}

// === Full Cleanup ===

// 1. Remove GetCited rules from physical robots.txt (if present)
$robots_file = ABSPATH . 'robots.txt';
if ( file_exists( $robots_file ) && is_writable( $robots_file ) ) {
    $content = file_get_contents( $robots_file );
    $marker_start = '# === GetCited AI Crawler Rules ===';
    $marker_end = '# === End GetCited Rules ===';
    
    if ( strpos( $content, $marker_start ) !== false ) {
        // Remove our rules section (including surrounding whitespace)
        $pattern = '/\n*' . preg_quote( $marker_start, '/' ) . '.*?' . preg_quote( $marker_end, '/' ) . '\n*/s';
        $new_content = preg_replace( $pattern, "\n", $content );
        $new_content = trim( $new_content ) . "\n";
        
        // Only write if we actually removed something
        if ( $new_content !== $content ) {
            file_put_contents( $robots_file, $new_content );
        }
    }
}

// 2. Delete all transients
delete_transient( 'getcited_crawler_list' );
delete_transient( 'getcited_health_status' );
delete_transient( 'getcited_llms_txt_status' );
delete_transient( 'getcited_activation_redirect' );

// Delete citability cache transients
global $wpdb;
$wpdb->query(
    "DELETE FROM {$wpdb->options} 
     WHERE option_name LIKE '_transient_getcited_citability_cache_%'
     OR option_name LIKE '_transient_timeout_getcited_citability_cache_%'"
);

// 3. Delete post meta
$wpdb->query(
    "DELETE FROM {$wpdb->postmeta} 
     WHERE meta_key LIKE '_getcited_%'"
);

// 4. Delete options
delete_option( 'getcited_settings' );
delete_option( 'getcited_version' );
delete_option( 'getcited_db_version' );

// 5. Clear scheduled hooks
wp_clear_scheduled_hook( 'getcited_daily_cron' );

// 6. Flush rewrite rules (to remove llms.txt rule)
flush_rewrite_rules();
```

---

## Feature #9: Load Conflict Detector

**File:** `getcited.php`

**Add to includes section (around line 75):**

```php
require_once GETCITED_PLUGIN_DIR . 'includes/class-conflict-detector.php';
```

---

## Summary Table

| Feature | Description | Files |
|---------|-------------|-------|
| Conflict Detector | New class to inspect WordPress hooks and identify plugins | `class-conflict-detector.php` (NEW) |
| Updated Health Check | Uses conflict detector for smarter messaging | `class-health-check.php` |
| One-Click Add | Write rules to physical robots.txt | `class-robots.php` |
| AJAX Handlers | Handle add/remove rules requests | `class-dashboard.php` |
| Dashboard UI | Buttons, previews, plugin detection display | `templates/dashboard.php` |
| JavaScript | Add rules, toggle preview, copy clipboard | `assets/js/admin.js` |
| CSS | Styling for new UI elements | `assets/css/admin.css` |
| Clean Uninstall | Remove rules from robots.txt on delete | `uninstall.php` |
| Loader | Include new class | `getcited.php` |

---

## Testing Checklist

### Conflict Detection

- [ ] Install Yoast SEO alongside GetCited
  - [ ] Health check should detect Yoast hooks on robots.txt
  - [ ] Should show link to Yoast's File Editor
  - [ ] Check `identifier` shows `WPSEO_...` class name
  
- [ ] Install RankMath alongside GetCited
  - [ ] Health check should detect RankMath hooks
  - [ ] Should show link to RankMath's robots.txt editor
  
- [ ] No SEO plugin installed
  - [ ] Health check should still work
  - [ ] Should not show SEO plugin editor links
  
- [ ] Unknown plugin modifying robots.txt
  - [ ] Should detect hook exists
  - [ ] Should show plugin folder name if identifiable

### robots.txt Physical File

- [ ] No physical file exists
  - [ ] Health check uses WordPress filter
  - [ ] Shows OK status
  
- [ ] Create physical robots.txt manually
  - [ ] Health check detects it
  - [ ] Shows "Add Rules" button
  - [ ] Click button → rules added → page reloads with OK
  
- [ ] Physical file not writable
  - [ ] Health check shows copy button instead
  - [ ] Copy button works
  
- [ ] Rules already present
  - [ ] Health check shows OK
  - [ ] Update crawler settings, click Add Rules again
  - [ ] Rules updated, not duplicated

### llms.txt Conflict

- [ ] GetCited serving llms.txt
  - [ ] Health check shows OK
  
- [ ] Create physical llms.txt file
  - [ ] Health check detects it
  - [ ] Shows appropriate warning
  
- [ ] Content has identifiable source
  - [ ] Source name appears in warning

### Uninstall

- [ ] Set keep_on_delete = false
  - [ ] Uninstall plugin
  - [ ] GetCited rules removed from robots.txt
  - [ ] All options/transients/post meta deleted
  
- [ ] Set keep_on_delete = true
  - [ ] Uninstall plugin
  - [ ] GetCited rules remain in robots.txt
  - [ ] Settings preserved

---

## Changelog Entry

```
= 1.0.3 =
* Added: Robust plugin detection via WordPress hook inspection
* Added: One-click "Add Rules to robots.txt" for physical files
* Added: Automatic detection of Yoast SEO, Rank Math, AIOSEO, SEOPress robots.txt editors
* Added: Smart conflict detection showing which plugins modify robots.txt and in what order
* Added: Physical llms.txt file detection with source identification
* Added: Clean removal of GetCited rules from robots.txt on plugin deletion
* Improved: Health check now shows detailed plugin coexistence information
* Improved: Better guidance when other plugins are detected
* Improved: Identifies potential conflicts when plugins run after GetCited
```

---

## Files Summary

| File | Status | Description |
|------|--------|-------------|
| `includes/class-conflict-detector.php` | NEW | Hook inspection and plugin detection |
| `includes/class-health-check.php` | MODIFY | Use conflict detector, smarter messaging |
| `includes/class-robots.php` | MODIFY | Add physical file read/write methods |
| `includes/class-dashboard.php` | MODIFY | Add AJAX handlers |
| `templates/dashboard.php` | MODIFY | Add auto-add UI |
| `assets/js/admin.js` | MODIFY | Add button handlers |
| `assets/css/admin.css` | MODIFY | Add new styles |
| `uninstall.php` | MODIFY | Add robots.txt cleanup |
| `getcited.php` | MODIFY | Include new class |
