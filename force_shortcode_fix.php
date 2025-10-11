<?php
/**
 * Temporary fix to force display shortcode on adventures page
 * Add this code to your theme's functions.php file or create a small plugin
 */

// Force shortcode display on the adventures page
function puzzlepath_force_adventures_display($content) {
    // Only on the specific page and if we're in the main query
    if (is_page() && is_main_query() && in_the_loop()) {
        global $post;
        
        // Check if this is the adventures page by slug or title
        if ($post && (
            $post->post_name === 'puzzle-path-upcoming-adventures' ||
            strpos($post->post_title, 'Puzzle Path Upcoming Adventures') !== false ||
            strpos($post->post_title, 'Upcoming Adventures') !== false
        )) {
            // Force the shortcode to display
            $shortcode_content = do_shortcode('[puzzlepath_upcoming_adventures]');
            
            // If content is empty or very short, replace it entirely
            if (strlen(trim(strip_tags($content))) < 50) {
                return $shortcode_content;
            } else {
                // Otherwise append it
                return $content . $shortcode_content;
            }
        }
    }
    
    return $content;
}
add_filter('the_content', 'puzzlepath_force_adventures_display', 999);

/**
 * Alternative: Hook into template redirect for more control
 */
function puzzlepath_force_adventures_template() {
    if (is_page() && get_the_ID()) {
        global $post;
        
        if ($post && (
            $post->post_name === 'puzzle-path-upcoming-adventures' ||
            strpos($post->post_title, 'Puzzle Path Upcoming Adventures') !== false
        )) {
            // Force content replacement
            add_filter('the_content', function($content) {
                return do_shortcode('[puzzlepath_upcoming_adventures]');
            }, 999);
        }
    }
}
add_action('template_redirect', 'puzzlepath_force_adventures_template');
?>