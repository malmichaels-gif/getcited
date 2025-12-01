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
        usort( $detected, function ( $a, $b ) {
            return $a['priority'] - $b['priority'];
        } );

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
        } elseif ( is_string( $func ) ) {
            // String callback: 'function_name'
            $result['function'] = $func;
            $result['identifier'] = $func;
            $result['type'] = 'function';
        } elseif ( $func instanceof Closure ) {
            // Closure
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
        } else {
            // Other callable
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
