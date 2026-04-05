<?php
/**
 * ExamHub - Mobile/tablet install prompt helpers.
 *
 * @package ExamHub
 */

defined( 'ABSPATH' ) || exit;

/**
 * Returns the icon used for add-to-home-screen prompts.
 *
 * @return string
 */
function examhub_get_install_prompt_icon_url() {
    return apply_filters(
        'examhub_install_prompt_icon_url',
        'https://imthan.com/wp-content/uploads/2026/03/cropped-imthanicon-1.png'
    );
}

/**
 * Builds a short app name for the manifest.
 *
 * @return string
 */
function examhub_get_install_prompt_short_name() {
    $site_name  = wp_strip_all_tags( get_bloginfo( 'name' ) );
    $short_name = function_exists( 'mb_substr' ) ? mb_substr( $site_name, 0, 12 ) : substr( $site_name, 0, 12 );

    return $short_name ?: $site_name;
}

/**
 * Outputs mobile app meta tags.
 *
 * Keeps display mode as "browser" so the shortcut opens the normal browser.
 *
 * @return void
 */
function examhub_output_install_prompt_meta() {
    if ( is_admin() ) {
        return;
    }

    $manifest_url = add_query_arg( 'examhub_manifest', '1', home_url( '/' ) );
    $icon_url     = examhub_get_install_prompt_icon_url();
    ?>
    <link rel="manifest" href="<?php echo esc_url( $manifest_url ); ?>">
    <link rel="apple-touch-icon" sizes="512x512" href="<?php echo esc_url( $icon_url ); ?>">
    <?php
}
add_action( 'wp_head', 'examhub_output_install_prompt_meta', 5 );

/**
 * Serves the web app manifest.
 *
 * @return void
 */
function examhub_render_install_prompt_manifest() {
    if ( is_admin() || ! isset( $_GET['examhub_manifest'] ) ) {
        return;
    }

    $site_name        = wp_strip_all_tags( get_bloginfo( 'name' ) );
    $site_description = wp_strip_all_tags( get_bloginfo( 'description' ) );
    $home_url         = trailingslashit( home_url( '/' ) );
    $icon_url         = examhub_get_install_prompt_icon_url();
    $manifest         = [
        'id'               => $home_url,
        'name'             => $site_name,
        'short_name'       => examhub_get_install_prompt_short_name(),
        'description'      => $site_description ?: $site_name,
        'start_url'        => $home_url,
        'scope'            => $home_url,
        'display'          => 'browser',
        'background_color' => '#0d0f14',
        'theme_color'      => '#0d0f14',
        'icons'            => [
            [
                'src'     => $icon_url,
                'sizes'   => '512x512',
                'type'    => 'image/png',
                'purpose' => 'any maskable',
            ],
        ],
    ];

    nocache_headers();
    header( 'Content-Type: application/manifest+json; charset=' . get_bloginfo( 'charset' ) );

    echo wp_json_encode( $manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
    exit;
}
add_action( 'template_redirect', 'examhub_render_install_prompt_manifest', 0 );

/**
 * Renders the mobile/tablet install prompt container.
 *
 * @return void
 */
function examhub_render_install_prompt_markup() {
    if ( is_admin() ) {
        return;
    }

    $icon_url = examhub_get_install_prompt_icon_url();
    ?>
    <aside class="eh-install-prompt" id="eh-install-prompt" hidden aria-live="polite" aria-label="<?php esc_attr_e( 'إضافة اختصار الموقع', 'examhub' ); ?>">
        <button type="button" class="eh-install-prompt-close" id="eh-install-prompt-close" aria-label="<?php esc_attr_e( 'إغلاق', 'examhub' ); ?>">
            <i class="bi bi-x-lg" aria-hidden="true"></i>
        </button>
        <div class="eh-install-prompt-icon" aria-hidden="true">
            <img src="<?php echo esc_url( $icon_url ); ?>" alt="">
        </div>
        <div class="eh-install-prompt-copy">
            <h2 class="eh-install-prompt-title"><?php esc_html_e( 'أضف أيقونة الموقع إلى جهازك', 'examhub' ); ?></h2>
            <p class="eh-install-prompt-text"><?php esc_html_e( 'احفظ اختصار الموقع على الشاشة الرئيسية للوصول السريع من الموبايل أو التابلت.', 'examhub' ); ?></p>
            <div class="eh-install-prompt-actions">
                <button type="button" class="btn btn-primary btn-sm" id="eh-install-prompt-action"><?php esc_html_e( 'إضافة الأيقونة', 'examhub' ); ?></button>
                <button type="button" class="btn btn-ghost btn-sm" id="eh-install-prompt-dismiss"><?php esc_html_e( 'لاحقًا', 'examhub' ); ?></button>
            </div>
            <div class="eh-install-prompt-guide" id="eh-install-prompt-guide" hidden></div>
        </div>
    </aside>
    <?php
}
add_action( 'wp_footer', 'examhub_render_install_prompt_markup', 20 );
