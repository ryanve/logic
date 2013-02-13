<?php
/*!
 * Plugin Name:   logic
 * Plugin URI:    http://github.com/ryanve/logic
 * Description:   Contextual utilities for WordPress.
 * Author:        Ryan Van Etten
 * Author URI:    http://ryanvanetten.com
 * License:       MIT
 * License URI:   http://opensource.org/licenses/MIT
 * Version:       0.5.0
 */

namespace logic;

/**
 * Pluck values from an array of objects or arrays.
 * @uses wp_list_pluck() from wp-includes/functions.php
 * @param   array|object   $list
 * @param   string|number  $key
 * @return  array
 */
if ( ! \function_exists( __NAMESPACE__ . '\\pluck' ) ) {
    function pluck ( $list, $field ) {
        return \array_values( (array) \wp_list_pluck( $list, $field ) );
    }
}

/**
 * An alternative to empty() that behaves like JavaScript.
 * Contrast w/ @link php.net/manual/en/function.empty.php
 * @param   mixed    $value
 * @return  bool     true for: null|false|''|0|NAN
 */
if ( ! \function_exists( __NAMESPACE__ . '\\emp' ) ) {
    function emp ( $value ) {
        if ( !empty($value) )
            return $value !== $value; # NAN
        return null === $value || false === $value || '' === $value || 0 === $value;
    }
}

/**
 * @param   array|object  $arr  array or object to filter
 * @param   mixed=        $fn   filter callback (named or anonymous func)
 * @param   bool=         $inv  option to invert the result of the filter
 * @return  array
 */
if ( ! \function_exists( __NAMESPACE__ . '\\sift' ) ) {
    function sift ( $arr, $fn = null, $inv = null ) {
        $result = array();
        $inv = $inv === true; # require explicit to invert + ensure bool for use in loop
        foreach ( $arr as $k => $v ) # default the $fn to !emp(...)
            if ( $inv === ( $fn ? ! \call_user_func($fn, $v, $k, $arr) : emp($v) ) )
                \is_int($k) ? ( $result[] = $v ) : ( $result[$k] = $v );
        return $result;
    }
}

if ( ! \function_exists( __NAMESPACE__ . '\\kv' ) ) {
    function kv ( $arr, $glue = '' ) {
        foreach ( $arr as $k => &$v )
            $v = \is_int($k) ? $v : ( $v || \is_numeric($v) ? $k . $glue . $v : $k );
        return $arr;
    }
}

/**
 * @param    mixed    $tokens
 * @param    string=  $glue     defaults to ssv
 * @return   string
 * @link     dev.w3.org/html5/spec/common-microsyntaxes.html
 */
if ( ! \function_exists( __NAMESPACE__ . '\\token_implode' ) ) {
    function token_implode ( $tokens, $glue = ' ' ) {

        if ( \is_scalar($tokens) )
            return \trim($tokens);

        if ( ! $tokens )
            return '';

        $ret = array();
        foreach ( $tokens as $v ) # flatten
            $ret[] = token_implode($v);

        return \implode( $glue, $ret );
    }
}

/**
 * @param    string|mixed   $tokens
 * @param    string|array=  $glue    Defaults to ssv. If $glue is an array 
 *                                   then $tokens is split at any of $glue's 
 *                                   items. Otherwise $glue splits as a phrase.
 * @return   array
 * @link     dev.w3.org/html5/spec/common-microsyntaxes.html
 */
if ( ! \function_exists( __NAMESPACE__ . '\\token_explode' ) ) {
    function token_explode ( $tokens, $glue = ' ' ) {

        if ( \is_string($tokens) )
            $tokens = \trim( $tokens );
        elseif ( \is_scalar($tokens) )
            return (array) $tokens;
        else $tokens = token_implode( \is_array($glue) ? $glue[0] : $glue, $tokens );

        if ( '' === $tokens ) # could be empty after 1st or 3rd condition above
            return array();

        if ( \is_array($glue) ) # normalize multiple delims into 1
            $tokens = \str_replace( $glue, $glue = $glue[0], $tokens );

        if ( \ctype_space($glue) )
            return \preg_split( '#\s+#', $tokens );

        return \explode( $glue, $tokens );
    }
}

/**
 * @return  string|false|null
 */
if ( ! \function_exists( __NAMESPACE__ . '\\timeframe' ) ) {
    function timeframe () {
    
        global $wp_query;
        static $unit;

        if ( isset($unit) || empty($wp_query) )
            # cached result *or* `null` before $wp_query is avail
            # codex.wordpress.org/Conditional_Tags
            return $unit; 

        if ( ! is_date() )
            return $unit = false;
            
        if ( ! is_time() )
            $unit = ( \is_day()           ? 'day' 
                  : ( \get_query_var('w') ? 'week' 
                  : ( \is_month()         ? 'month' 
                  : ( \is_year()          ? 'year' 
                  : null ) ) ) );

        else for ( $units = array('second', 'minute', 'hour'); $unit = \array_shift($units); )
            if ( ( $v = \get_query_var($unit) ) || \is_numeric($v) )
                break;

        $unit or $unit = true;
        return $unit;
    }
}

/**
 * @return  string|false|null
 */
if ( ! \function_exists( __NAMESPACE__ . '\\format' ) ) {
    function format ( $id = null ) {
        return \current_theme_supports('post-formats') ? \get_post_format( $id ) : false; 
    }
}

/**
 * Get an array of contexts
 * @return  array
 */
if ( ! \function_exists( __NAMESPACE__ . '\\contexts' ) ) {
    function contexts () {

        static $array;
        global $wp_query;
        
        if ( isset($array) )
            return $array;

        # WP conditionals cannot be used until $wp_query is avail.
        # codex.wordpress.org/Conditional_Tags
        if ( empty($wp_query) )
            return;

        # The checks below run once and get cached in the static $array
        # php.net/manual/en/language.variables.scope.php
        
        # The checks are adapted from Hybrid Core
        # github.com/justintadlock/hybrid-core -> functions -> context.php
        
        $array = array( \is_child_theme() ? 'child-theme' : 'parent-theme' );
        
        if ( is_user_logged_in() ) {
            $array[] = 'logged-in';
            is_admin_bar_showing() and $array[] = 'admin-bar';
        } else {
            $array[] = 'logged-out';
        }
        
        if ( is_multisite() ) {
            $array[] = 'multisite';
            $array[] = 'blog-' . \get_current_blog_id();
        }

        is_front_page() and $array[] = 'home';
        is_paged()      and $array[] = 'paged';
        $is_singular = is_singular();
        $array[] = $is_singular ? 'singular' : 'plural';

        if ( is_home() ) {
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

        if ( $is_singular ) {

            $array['type'] = $type = $object->post_type;
            $slug = sanitize_html_class( $object->post_name );
            
            if ( 'attachment' == $type ) {
                # en.wikipedia.org/wiki/Internet_media_type
                $mime = get_post_mime_type();
                $mime && \ctype_alnum( $n = \strtok($mime, '/') ) and $array['mime'] = $n;

            } elseif ( $format = format($id) ) {
                $array['format'] = $format;
                $array['pfor'] = implode( ' ', terms( $id, 'post_format' ) );
            }

        } elseif ( is_search() ) {
            $array[] = 'search';

        } elseif ( is_404() ) {
            $array[] = 'error-404';

        } elseif ( is_archive() ) {
        
            $array[] = 'archive';

            if ( is_tag() || is_category() || is_tax() ) {
                $array[] = 'taxo';
                $array['taxo'] = $object->taxonomy;
                $slug = sanitize_html_class( $object->slug );
                
            } elseif ( is_post_type_archive() ) {
                if ( $type = get_query_var('post_type') )
                    if ( $type = get_post_type_object($type) )
                        $array['type'] = $type->name;

            } elseif ( is_author() ) {
                $array[] = 'user';
                $slug = sanitize_html_class( get_the_author_meta( 'user_nicename', $id ) );

            } elseif ( $unit = timeframe() ) {

                # In WP: a "time" is a type of "date"
                # On the rest of the planet: a "date" is a type of "time" 
                # @link codex.wordpress.org/Conditional_Tags
                # To prevent confusion related to this, I used 'timeframe' here to 
                # encompass both. Also, using 'timeframe' instead of simply 'date' 
                # and/or 'time' will help prevent css conflicts.
                
                $array[] = 'timeframe'; # means time-based *or* date-based
                $array['unit'] = $unit; 
                $slug = $id = null;
                $vars = array( 'year' => 'year', 'month' => 'monthnum', 'day' => 'day' );
                
                if ( isset( $vars[ $unit ] ) ) {
                    foreach ( $vars as $n ) {
                        if ( ( $n = (int) get_query_var($n) ) )
                            $id = ( $id ? $id . '-' : '' ) . ( $slug = zeroise( $n, 2 ) );
                        else break;
                    }
                }

            }
        }
        
        $array = sift( $array );
        $id and $array['id'] = $id;
        emp($slug) or $array['slug'] = $slug;  
        return $array;

    }
}

if ( ! \function_exists( __NAMESPACE__ . '\\classes' ) ) {
    function classes () {

        static $classes;
        static $unis;

        if ( null !== $classes )
            return $classes;
        if ( emp( $classes = contexts() ) )
            return $classes = null;

        if ( ! isset($unis) || ! did_action('wp_loaded') ) {
            # prevent re-running the filter after the 'wp_loaded' action
            # codex.wordpress.org/Plugin_API/Action_Reference
            $unis = array( 'no-js', 'custom', 'wp' );
            $unis = apply_filters( '@universal_classes', $unis );
            $unis = token_explode( $unis );
        }
        
        $classes = \array_merge( $unis, $classes );
        $classes = kv( $classes, '=' );
        $classes = \array_unique( sift($classes) );
        return token_implode( $classes );
    }
}

/**
 * Get an array of terms for the specified taxonomy.
 * @param   string        $tax
 * @param   string|null   $field
 * @param   integer|null  $id
 */
if ( ! \function_exists( __NAMESPACE__ . '\\terms' ) ) {
    function terms ( $id = null, $tax = null, $field = 'slug' ) {

        if ( \is_string($tax) && taxonomy_exists($tax) ) {
            if ( \is_array( $terms = get_the_terms($id, $tax) ) )
                return $field ? pluck( $terms, $field ) : $terms;  
            return array();
        }

        $results = array();
        if ( null === $tax ) # get all
            foreach ( taxos() as $n )
                $results[$n] = terms( $id, $n, $field );

        return $results;
    }
}

/**
 * Get an array of taxonomy names supported by a post.
 * @param   string|integer|object  $type
 * @return  array
 */
if ( ! \function_exists( __NAMESPACE__ . '\\taxos' ) ) {
    function taxos ( $type = null, $field = 'name' ) {
        global $wp_taxonomies; # expedite get_taxonomies()
        $taxos = $field ? \wp_list_pluck( $wp_taxonomies, $field ) : $wp_taxonomies;
        $result = array();
        \is_string( $type ) or $type = \get_post_type( null === $type ? false : $type );

        if ( $taxos && post_type_exists( $type ) )
            foreach ( $taxos as $name => $value )
                \is_string($name) && is_object_in_taxonomy($type, $name) and $result[$name] = $value;

        return $result;
    }
}

/**
 * Get an array of registered sidebar names.
 * @param    boolean|null  $active
 * @return   array
 * @example  sidebars()      # all
 * @example  sidebars(true)  # active
 * @example  sidebars(false) # inactive
 */
if ( ! \function_exists( __NAMESPACE__ . '\\sidebars' ) ) {
    function sidebars ( $active = null ) {
        global $wp_registered_sidebars;
        $sidebars = \array_keys( $wp_registered_sidebars );
        return \is_bool( $active ) ? sift( $sidebars, 'is_active_sidebar', ! $active ) : $sidebars;
    }
}

/**
 *
 *
 */
if ( ! \function_exists( __NAMESPACE__ . '\\entry_attrs' ) ) {
    function entry_attrs ( $id = null, $array = false ) {
        
        $attrs = array('class' => null);
        $classes = array('hentry');
        is_sticky() and $classes[] = 'sticky';
        
        if ( ! $id )
            $id = \get_the_ID();
        elseif ( \is_object($id) )
            $id = $id->ID;
        
        if ( \is_int($id) ) {
            foreach ( array( '\\get_post_type', '\\get_post_status' ) as $fn )
                if ( $result = \call_user_func( $fn, $id ) )
                    #$attrs[ 'data-' . \array_pop( \explode( '_', $fn ) ) ] = $result;
                    $classes[] = \array_pop( \explode( '_', $fn ) ) . '='. $result;
                    
            $attrs[ 'data-id' ] = $id;
            is_singular() and $attrs['id'] = 'entry-' . $id;
                    
            /*$taxos = taxos( null, 'label' ); # should be the plural names
            foreach ( terms( $id ) as $tax => $terms )
                $attrs[ 'data-' . \strtolower( 
                    sanitize_html_class( $taxos[$tax], $tax ) 
                ) ] = \implode( ' ', $terms );

            if ( $attrs['data-format'] ) # remove the 'post-format-' that WP adds to format term slugs
                $attrs['data-format'] = \str_replace( 'post-format-', '', $attrs['data-format'] );
            */
            foreach ( taxos() as $tax )
                foreach ( terms( $id, $tax ) as $terms )
                    foreach ( (array) $terms as $term )
                        \strlen( $term ) and $classes[] = $tax . '-' . $term;
        }

        $attrs['class'] = token_implode( $classes );        
        if ( true === $array )
            return $attrs;  

        foreach ( $attrs as $k => &$v ) # $attrs is all associative
            $v = $v ? ( $k . '="' . $v . '"' ) : $k;
        return \implode( ' ', $attrs );
    }
}

# testing
# add_filter( '@entry_attrs', function ( $tag ) {
    # return entry_attrs();
    //return implode( ' ', sidebars() );
    //return $atts;
# });

#end