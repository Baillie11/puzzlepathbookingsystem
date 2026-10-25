<?php
/**
 * Plugin Name: PuzzlePath Booking Look
 * Description: Koko landing-page styling and group wording for Book Today. No booking or payment logic changes.
 * Version: 1.0.1
 */
defined('ABSPATH') || exit;

// Change display copy only; retain field names, IDs and quantity behaviour.
add_filter('do_shortcode_tag', function ($output, $tag) {
    if (!is_page('book-today') || $tag !== 'puzzlepath_booking_form') {
        return $output;
    }
    return str_replace(
        array('Number of Carloads:', 'Number of Quests:', 'Select 1 for one carload.'),
        array('Number of Groups (up to 5 people per group):', 'Number of Groups (up to 5 people per group):', 'Select 1 for one group.'),
        $output
    );
}, 10, 2);

add_filter('body_class', function ($classes) {
    if (is_page('book-today')) {
        $classes[] = 'pp-booking-look';
    }
    return $classes;
});

add_action('wp_enqueue_scripts', function () {
    if (!is_page('book-today')) {
        return;
    }
    wp_enqueue_style('puzzlepath-booking-look', plugin_dir_url(__FILE__) . 'assets/booking-look.css', array(), filemtime(__DIR__ . '/assets/booking-look.css'));
}, 100);
