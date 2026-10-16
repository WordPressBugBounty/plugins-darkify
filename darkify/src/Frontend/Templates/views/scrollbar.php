<?php
// don't call the file directly.
if (!defined('ABSPATH')) {
    exit;
}

/*
 * Fall back to the colours the settings screen offers as defaults, so an option
 * saved before these fields existed still produces valid CSS rather than an
 * empty declaration.
 */
$darkify_track = !empty($dark_mode_scrollbar_track_bg) ? $dark_mode_scrollbar_track_bg : '#29292a';
$darkify_thumb = !empty($dark_mode_scrollbar_thumb_bg) ? $dark_mode_scrollbar_thumb_bg : '#52565a';
?>
<style type="text/css" class="darkify_inline_css">
    /*
     * `color-scheme` is what actually makes the browser's own furniture dark:
     * the scrollbar it paints for the page, the ones it paints for every nested
     * scrolling box, and the controls it renders itself. The ::-webkit- rules
     * below can only reach the first of those, and only in Chromium and Safari —
     * which is why the page could be fully dark while the scrollbar beside it
     * stayed light grey.
     */
    html.darkify_dark_mode_enabled {
        color-scheme: dark;
        /* Standard property: Firefox, and Chromium 121+. */
        scrollbar-color: <?php echo esc_attr($darkify_thumb); ?> <?php echo esc_attr($darkify_track); ?>;
    }

    /* Chromium and Safari, including scrolling boxes inside the page. */
    html.darkify_dark_mode_enabled::-webkit-scrollbar,
    html.darkify_dark_mode_enabled *::-webkit-scrollbar {
        width: 12px;
        height: 12px;
        background: <?php echo esc_attr($darkify_track); ?> !important;
    }

    html.darkify_dark_mode_enabled::-webkit-scrollbar-track,
    html.darkify_dark_mode_enabled *::-webkit-scrollbar-track {
        background: <?php echo esc_attr($darkify_track); ?> !important;
    }

    /*
     * The transparent border plus content-box clipping is what gives the thumb
     * its inset pill shape instead of a full-width slab — the difference
     * between a scrollbar that looks designed and one that looks like a default.
     */
    html.darkify_dark_mode_enabled::-webkit-scrollbar-thumb,
    html.darkify_dark_mode_enabled *::-webkit-scrollbar-thumb {
        background-color: <?php echo esc_attr($darkify_thumb); ?> !important;
        border: 3px solid transparent !important;
        border-radius: 8px !important;
        background-clip: content-box !important;
    }

    html.darkify_dark_mode_enabled::-webkit-scrollbar-corner,
    html.darkify_dark_mode_enabled *::-webkit-scrollbar-corner {
        background-color: <?php echo esc_attr($darkify_track); ?> !important;
    }

    html.darkify_dark_mode_enabled::-webkit-scrollbar-button,
    html.darkify_dark_mode_enabled *::-webkit-scrollbar-button {
        background-color: transparent !important;
        background-repeat: no-repeat !important;
        background-size: contain !important;
        background-position: center !important;
    }
</style>
