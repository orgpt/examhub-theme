<?php
/**
 * ExamHub — Theme Setup
 * WordPress theme supports, navigation menus, image sizes.
 *
 * @package ExamHub
 */

defined( 'ABSPATH' ) || exit;

function examhub_theme_setup() {

    // Text domain
    load_theme_textdomain( EXAMHUB_TEXT, EXAMHUB_DIR . '/languages' );

    // Theme supports
    add_theme_support( 'title-tag' );
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'custom-logo', [
        'height'      => 60,
        'width'       => 200,
        'flex-height' => true,
        'flex-width'  => true,
    ] );
    add_theme_support( 'html5', [ 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ] );
    add_theme_support( 'automatic-feed-links' );
    add_theme_support( 'customize-selective-refresh-widgets' );
    add_theme_support( 'wp-block-styles' );
    add_theme_support( 'responsive-embeds' );
    add_theme_support( 'align-wide' );
    add_theme_support( 'editor-styles' );

    // Navigation menus
    register_nav_menus( [
        'primary'   => __( 'القائمة الرئيسية', 'examhub' ),
        'student'   => __( 'قائمة الطالب', 'examhub' ),
        'footer'    => __( 'قائمة التذييل', 'examhub' ),
        'mobile'    => __( 'قائمة الجوال', 'examhub' ),
    ] );

    // Custom image sizes
    add_image_size( 'exam-thumbnail',   400, 250, true );
    add_image_size( 'subject-icon',     100, 100, true );
    add_image_size( 'badge-icon',       80,  80,  true );
    add_image_size( 'question-image',   800, 600, false );
    add_image_size( 'avatar-small',     50,  50,  true );
    add_image_size( 'avatar-medium',    100, 100, true );

    // RTL support
    add_editor_style( 'assets/css/editor.css' );
}
add_action( 'after_setup_theme', 'examhub_theme_setup' );

/**
 * Set content width.
 */
function examhub_content_width() {
    $GLOBALS['content_width'] = apply_filters( 'examhub_content_width', 960 );
}
add_action( 'after_setup_theme', 'examhub_content_width', 0 );

/**
 * RTL: Add dir="rtl" lang="ar" to body if default language is Arabic.
 */
function examhub_body_classes( $classes ) {
    $classes[] = 'examhub-theme';
    $classes[] = 'dark-theme';
    $classes[] = function_exists( 'examhub_is_theme_activated' ) && examhub_is_theme_activated()
        ? 'examhub-activated'
        : 'examhub-locked';

    if ( is_user_logged_in() ) {
        $classes[] = 'user-logged-in';
        $sub = examhub_get_user_subscription_status( get_current_user_id() );
        $classes[] = 'sub-' . $sub['state']; // sub-free, sub-subscribed, etc.
    } else {
        $classes[] = 'user-guest';
    }

    if ( is_singular( 'eh_exam' ) ) {
        $classes[] = 'exam-page';
    }

    return $classes;
}
add_filter( 'body_class', 'examhub_body_classes' );

/**
 * Disable Gutenberg on exam/question CPTs for cleaner edit experience.
 */
function examhub_disable_gutenberg( $is_enabled, $post_type ) {
    $acf_only = [ 'eh_question', 'eh_exam', 'eh_result', 'eh_subscription', 'eh_payment', 'eh_badge' ];
    if ( in_array( $post_type, $acf_only ) ) {
        return false;
    }
    return $is_enabled;
}
add_filter( 'use_block_editor_for_post_type', 'examhub_disable_gutenberg', 10, 2 );

/**
 * Add widget areas.
 */
function examhub_widgets_init() {
    register_sidebar( [
        'name'          => __( 'الشريط الجانبي للامتحانات', 'examhub' ),
        'id'            => 'exam-sidebar',
        'before_widget' => '<div id="%1$s" class="widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<h3 class="widget-title">',
        'after_title'   => '</h3>',
    ] );
    register_sidebar( [
        'name'          => __( 'التذييل — عمود 1', 'examhub' ),
        'id'            => 'footer-1',
        'before_widget' => '<div id="%1$s" class="widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<h4 class="widget-title">',
        'after_title'   => '</h4>',
    ] );
}
add_action( 'widgets_init', 'examhub_widgets_init' );

/**
 * Custom page title for exam pages.
 */
function examhub_document_title( $title ) {
    if ( is_singular( 'eh_exam' ) ) {
        $title['title'] = get_the_title() . ' - ' . __( 'امتحان', 'examhub' );
    }
    return $title;
}
add_filter( 'document_title_parts', 'examhub_document_title' );
