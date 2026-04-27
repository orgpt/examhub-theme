<?php
/**
 * ExamHub Pro — functions.php
 * Main theme bootstrap file. Loads all modules in correct order.
 *
 * @package ExamHub
 */

defined( 'ABSPATH' ) || exit;

// ─── Constants ────────────────────────────────────────────────────────────────
define( 'EXAMHUB_VERSION',   '1.0.0' );
define( 'EXAMHUB_DIR',       get_template_directory() );
define( 'EXAMHUB_URL',       get_template_directory_uri() );
define( 'EXAMHUB_INC',       EXAMHUB_DIR . '/inc/' );
define( 'EXAMHUB_ASSETS',    EXAMHUB_URL . '/assets/' );
define( 'EXAMHUB_TEXT',      'examhub' );
define( 'EXAMHUB_LICENSE_OPTION', 'examhub_license_key_hash' );
define( 'EXAMHUB_LICENSE_HASH',   'fcf20d4effacbb1e0df80d1817ccacdcbb8fb8fbcf97c80cc0a1468f2e2e1579' );

// ─── Core Includes ─────────────────────────────────────────────────────────────
$examhub_includes = [
    'inc/helpers.php',            // Utility functions
    'inc/cpt-registration.php',   // All Custom Post Types
    'inc/acf-fields.php',         // ACF field group registration
    'inc/acf-options.php',        // ACF options pages
    'inc/taxonomies.php',         // Custom taxonomies
    'inc/theme-setup.php',        // Theme supports, nav menus
    'inc/enqueue.php',            // Scripts & styles
    'inc/install-prompt.php',     // Mobile/tablet add-to-home-screen prompt
    'inc/ajax-handlers.php',      // AJAX endpoints
    'inc/rest-api.php',           // REST API extensions
    'inc/user-roles.php',         // Custom roles & capabilities
    'inc/auth.php',               // Custom auth pages and OAuth login
    'inc/affiliate.php',          // Affiliate tracking, invites, commissions
    'inc/subscription.php',       // Subscription logic
    'inc/payment.php',            // Payment gateway routing
    'inc/payment-fawaterk.php',   // Fawaterk integration
    'inc/payment-vodafone.php',   // Vodafone Cash
    'inc/payment-manual.php',     // Manual payment
    'inc/book-store.php',         // External books store
    'inc/teacher-publishing.php', // Teacher exam submission workflow
    'inc/exam-engine.php',        // Exam session logic
    'inc/question-bank.php',      // Question bank utilities
    'inc/ai-integration.php',     // DeepSeek AI
    'inc/gamification.php',       // XP, badges, leaderboard
    'inc/analytics.php',          // Performance analytics
    'inc/pdf-import.php',         // PDF upload & OCR
    'inc/admin-columns.php',      // Admin list improvements
    'inc/mailer.php',             // HTML email templates and digests
    'inc/waitlist.php',           // Empty-state waitlist subscriptions + notifications
    'inc/shortcodes.php',         // Theme shortcodes
    'inc/template-hooks.php',     // Action/filter hooks
    'inc/security.php',           // Nonces, validation, rate limiting
];

foreach ( $examhub_includes as $file ) {
    $path = EXAMHUB_DIR . '/' . $file;
    if ( file_exists( $path ) ) {
        require_once $path;
    } else {
        // Log missing file in debug mode only
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'ExamHub: Missing include file: ' . $file );
        }
    }
}

// ─── ACF JSON Save/Load Point ─────────────────────────────────────────────────
add_filter( 'acf/settings/save_json', function() {
    return EXAMHUB_DIR . '/acf-json';
} );

add_filter( 'acf/settings/load_json', function( $paths ) {
    $paths[] = EXAMHUB_DIR . '/acf-json';
    return $paths;
} );

// ─── ACF Options Page ─────────────────────────────────────────────────────────
add_action( 'acf/init', function() {
    if ( function_exists( 'acf_add_options_page' ) ) {
        acf_add_options_page( [
            'page_title' => __( 'ExamHub Settings', 'examhub' ),
            'menu_title' => __( 'ExamHub', 'examhub' ),
            'menu_slug'  => 'examhub-settings',
            'capability' => 'manage_options',
            'icon_url'   => 'dashicons-welcome-learn-more',
            'position'   => 3,
        ] );
        acf_add_options_sub_page( [
            'page_title'  => __( 'Payment Settings', 'examhub' ),
            'menu_title'  => __( 'Payments', 'examhub' ),
            'parent_slug' => 'examhub-settings',
        ] );
        acf_add_options_sub_page( [
            'page_title'  => __( 'AI Settings', 'examhub' ),
            'menu_title'  => __( 'AI / DeepSeek', 'examhub' ),
            'parent_slug' => 'examhub-settings',
        ] );
        acf_add_options_sub_page( [
            'page_title'  => __( 'Subscription Plans', 'examhub' ),
            'menu_title'  => __( 'Plans', 'examhub' ),
            'parent_slug' => 'examhub-settings',
        ] );
        acf_add_options_sub_page( [
            'page_title'  => __( 'Gamification Settings', 'examhub' ),
            'menu_title'  => __( 'Gamification', 'examhub' ),
            'parent_slug' => 'examhub-settings',
        ] );
        acf_add_options_sub_page( [
            'page_title'  => __( 'Affiliate Settings', 'examhub' ),
            'menu_title'  => __( 'Affiliate', 'examhub' ),
            'parent_slug' => 'examhub-settings',
        ] );
        acf_add_options_sub_page( [
            'page_title'  => __( 'Email Settings', 'examhub' ),
            'menu_title'  => __( 'Emails', 'examhub' ),
            'parent_slug' => 'examhub-settings',
        ] );
        acf_add_options_sub_page( [
            'page_title'  => __( 'Book Store Settings', 'examhub' ),
            'menu_title'  => __( 'Book Store', 'examhub' ),
            'menu_slug'   => 'book-store-settings',
            'parent_slug' => 'examhub-settings',
        ] );
        acf_add_options_sub_page( [
            'page_title'  => __( 'Book Shipping & Payment', 'examhub' ),
            'menu_title'  => __( 'Book Shipping', 'examhub' ),
            'menu_slug'   => 'book-shipping-settings',
            'parent_slug' => 'examhub-settings',
        ] );
    }
} );
