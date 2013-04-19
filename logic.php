<?php
/*!
 * Plugin Name:   logic
 * Plugin URI:    http://github.com/ryanve/logic
 * Description:   Contextual utilities for WordPress.
 * Author:        Ryan Van Etten
 * Author URI:    http://ryanvanetten.com
 * License:       MIT
 * License URI:   http://opensource.org/licenses/MIT
 * Version:       0.6.2
 */

namespace plugin\logic;
 
add_action('init', 'plugin\\logic\\Logic::init');

class Logic {

    public static function init() {
        /*
        if (current_theme_supports('plugin:logic')) {
            add_filter('@entry_attrs', function($attrs) {

            });
        }
        */
    }

    protected static function sep() {
        return apply_filters('@Logic::sep', '-');
    }
    
    public static function id($id = null) {
        return $id ? (is_object($id) ? $id->ID : $id) : get_the_ID();
    }
    
    /**
     * @return  string|false|null
     */
    public static function timeframe() {

        global $wp_query;
        static $unit;

        if (isset($unit) || empty($wp_query))
            # cached result *or* `null` before $wp_query is avail
            # codex.wordpress.org/Conditional_Tags
            return $unit; 

        if ( ! is_date())
            return $unit = false;

        if ( ! is_time())
            $unit = ( is_day()           ? 'day' 
                  : ( get_query_var('w') ? 'week' 
                  : ( is_month()         ? 'month' 
                  : ( is_year()          ? 'year' 
                  : null ) ) ) );
        else for ($units = array('second', 'minute', 'hour'); $unit = array_shift($units);)
            if (($v = get_query_var($unit)) || is_numeric($v))
                break;

        return $unit = $unit ?: true;
    }
    
    /**
     * @return  string|false|null
     */
    public static function format($id = null) {
        return current_theme_supports('post-formats') ? get_post_format($id) : false; 
    }
    
    /**
     * @return  array
     */
    public static function contexts() {
    
        static $array;
        global $wp_query;
        
        if (isset($array))
            return $array;

        # WP conditionals cannot be used until $wp_query is avail.
        # codex.wordpress.org/Conditional_Tags
        if (empty($wp_query))
            return;

        # The checks below run once and get cached in the static $array
        # php.net/manual/en/language.variables.scope.php
        # The checks are adapted from Hybrid Core
        # github.com/justintadlock/hybrid-core -> functions -> context.php

        $array = array(is_child_theme() ? 'child-theme' : 'parent-theme');
        
        if (is_user_logged_in()) {
            $array[] = 'logged-in';
            is_admin_bar_showing() and $array[] = 'admin-bar';
        } else {
            $array[] = 'logged-out'; 
        }
        
        if (is_multisite()) {
            $array[] = 'multisite';
            $array[] = 'blog-' . get_current_blog_id();
        }

        is_front_page() and $array[] = 'home';
        is_paged()      and $array[] = 'paged';
        $is_singular = is_singular();
        $array[] = $is_singular ? 'singular' : 'plural';

        if (is_home()) {
            # is_home() and is_front_page() are the same except when a "Front page" is
            # specified in Settings => Reading. In that case is_home() only returns
            # true when the "Posts page" is being displayed.
            $array[] = 'blog';
            return $array;
        }

        $object = get_queried_object();
        $id     = get_queried_object_id();
        $slug   = null;
        $type   = null;
        $unit   = null;

        if ($is_singular) {
        
            $array['type'] = $type = $object->post_type;
            $slug = sanitize_html_class($object->post_name);

            if ('attachment' == $type) {
                $mime = get_post_mime_type();
                $mime && ctype_alnum($n = strtok($mime, '/')) and $array['mime'] = $n;
            } elseif ($format = static::format($id)) {
                $array['format'] = $format;
                $array['pfor'] = implode(' ', static::terms($id, 'post_format'));
            }

        } elseif (is_search()) {
            $array[] = 'search';

        } elseif (is_404()) {
            $array[] = 'error-404';

        } elseif (is_archive()) {
        
            $array[] = 'archive';

            if (is_tag() || is_category() || is_tax()) {
                $array[] = 'taxo';
                $array['taxo'] = $object->taxonomy;
                $slug = sanitize_html_class($object->slug);                
                
            } elseif (is_post_type_archive()) {
                if ($type = get_query_var('post_type'))
                    if ($type = get_post_type_object($type))
                        $array['type'] = $type->name;

            } elseif (is_author()) {
                $array[] = 'user';
                $slug = sanitize_html_class(get_the_author_meta('user_nicename', $id));

            } elseif ($unit = static::timeframe()) {
                # In WP: a "time" is a type of "date"
                # On the rest of the planet: a "date" is a type of "time" 
                # @link codex.wordpress.org/Conditional_Tags
                # To prevent confusion related to this, I used 'timeframe' here to 
                # encompass both. Also, using 'timeframe' instead of simply 'date' 
                # and/or 'time' will help prevent css conflicts.
                $array[] = 'timeframe'; # means time-based *or* date-based
                $array['unit'] = $unit; 
                $slug = $id = null;
                $vars = array('year' => 'year', 'month' => 'monthnum', 'day' => 'day');
                if (isset($vars[$unit])) {
                    foreach ($vars as $k => $v) {
                        if ($v = (int) get_query_var($v)) {
                            $v = zeroise($v, 2);
                            null === $slug and $slug = $v;
                            $array[$k] =  $v;
                        } else break;
                    }
                }
            }
        }

        $array['slug'] = $slug;
        $array = array_filter($array, 'ctype_graph');
        is_int($id) and $array['id'] = $id;
        return $array;
    }

    public static function toClass() {
        $classes = array_unique(array_reduce(func_get_args(), function($result, $n) {
            return array_merge($result, is_string($n) ? preg_split('#\s+#', $n) : (array) $n);
        }, array()));
        $classes = array_map('sanitize_html_class', array_filter($classes, 'ctype_graph'));
        $classes = trim(preg_replace('#(^|\&+)(\d+\=)?#', ' ', http_build_query($classes)));
        return str_replace('=', '-', $classes);
    }

    /**
     * Get an array of terms for the specified taxonomy.
     * @param   string        $tax
     * @param   string|null   $field
     * @param   integer|null  $id
     */
    public static function terms($tax = null, $field = 'slug', $id = null) {
        if (is_string($tax) && taxonomy_exists($tax)) {
            $terms = get_the_terms($id, $tax);
            return $terms ? ($field ? array_values(wp_list_pluck($terms, $field)) : $terms) : array();
        }
        $results = array();
        if (null === $tax) # get all
            foreach (static::taxos() as $n)
                $results[$n] = static::terms($n, $field, $id);
        return $results;
    }
    
    /**
     * Get an array of taxonomy names supported by a post.
     * @param   string|integer|object  $type
     * @return  array
     */
    public static function taxos($type = null, $field = 'name') {
        global $wp_taxonomies; # expedite get_taxonomies()
        $taxos = $field ? wp_list_pluck($wp_taxonomies, $field) : $wp_taxonomies;
        $result = array();
        is_string($type) or $type = get_post_type(null === $type ? false : $type);
        if ($taxos && post_type_exists($type))
            foreach ($taxos as $name => $value)
                is_string($name) && is_object_in_taxonomy($type, $name) and $result[$name] = $value;
        return $result;
    }
    
    /**
     * Get an array of registered sidebar names.
     * @param    boolean|null  $active
     * @example  sidebars()      # all
     * @example  sidebars(true)  # active
     * @example  sidebars(false) # inactive
     * @return   array
     */
    public static function sidebars($active = null) {
        global $wp_registered_sidebars;
        $sidebars = array_keys($wp_registered_sidebars);
        return is_bool($active) ? ($active 
            ? array_filter($sidebars, 'is_active_sidebar')
            : array_diff($sidebars, array_filter($sidebars, 'is_active_sidebar'))
       ) : $sidebars;
    }
    
    public static function postData($id = null, $array = false) {
        
        $attrs = array('class' => null);
        $classes = array('hentry');
        is_sticky() and $classes[] = 'sticky';

        if (is_int($id = static::id($id))) {
            foreach (array('type' => 'get_post_type', 'status' => 'get_post_status') as $k => $fn) {
                if ($result = call_user_func($fn, $id)) {
                    #$attrs['data-' . $k] = $result;
                    $classes[] = $k . '='. $result;
                }
            }
                    
            $attrs[ 'data-id' ] = $id;
            is_singular() and $attrs['id'] = 'entry-' . $id;
                    
            /*$taxos = static::taxos( null, 'label' ); # should be the plural names
            foreach ( static::terms( $id ) as $tax => $terms )
                $attrs[ 'data-' . strtolower( 
                    sanitize_html_class( $taxos[$tax], $tax ) 
                ) ] = implode(' ', $terms);

            if ( $attrs['data-format'] ) # remove the 'post-format-' that WP adds to format term slugs
                $attrs['data-format'] = str_replace( 'post-format-', '', $attrs['data-format'] );
            */
            foreach (static::taxos() as $tax)
                foreach (static::terms($id, $tax) as $terms)
                    foreach ((array) $terms as $term)
                        strlen($term) and $classes[] = $tax . '-' . $term;
        }

        $attrs['class'] = implode(' ', $classes);
        if (true === $array)
            return $attrs;  

        foreach ($attrs as $k => &$v)
            $v = $v ? ($k . '="' . $v . '"') : $k; # $attrs is all assoc
        return implode(' ', $attrs);
    }

}#class