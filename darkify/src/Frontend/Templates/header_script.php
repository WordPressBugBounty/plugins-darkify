<?php

use ThemeAtelier\Darkify\Includes\DarkifyUtils;

// don't call the file directly.
if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('darkify_js_string')) {
    /**
     * Emit a value as a JavaScript string literal (quotes included).
     *
     * For anything the browser parses rather than displays — CSS selectors,
     * above all — esc_attr() is the wrong escaper inside a <script> block: it
     * HTML-encodes `>`, `&` and `"`, and script bodies are raw text, so those
     * entities reach the JS engine verbatim and corrupt the value. JSON is the
     * correct escaping for a JS string; the HEX flags additionally keep `<`,
     * `>`, `&`, `'` and `"` out of the raw output so the literal can never
     * close the surrounding <script> tag.
     *
     * @param mixed $value Value to emit (cast to string).
     * @return string A quoted JS string literal, e.g. '"#id, #id *"'.
     */
    function darkify_js_string($value)
    {
        $json = wp_json_encode(
            (string) $value,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );
        return false === $json ? '""' : $json;
    }
}

ob_start();
$options = get_option('darkify');
// Controls
$enable_default_dark_mode   = isset($options['enable_default_dark_mode']) ? $options['enable_default_dark_mode'] : '';
$enable_os_aware = isset($options['enable_os_aware']) ? $options['enable_os_aware'] : true;
$enable_keyboard_shortcut = isset($options['enable_keyboard_shortcut']) ? $options['enable_keyboard_shortcut'] : true;
$keyboard_shortcut_keys = isset($options['keyboard_shortcut_keys']) && $options['keyboard_shortcut_keys'] ? $options['keyboard_shortcut_keys'] : 'ctrl+alt+d';
/*
 * URL parameter (Controls -> Activation). The name goes into an inline script
 * and into visitors' links, so it is cut down to URL-safe characters, and a
 * name WordPress itself routes on (`p`, `s`, `page`, `name`, ...) falls back to
 * the default — as a query var it would change which page loads, or 404 it.
 */
$enable_url_parameter = isset($options['enable_url_parameter']) ? $options['enable_url_parameter'] : '';
$url_parameter_name   = isset($options['url_parameter_name']) ? preg_replace('/[^A-Za-z0-9_-]/', '', (string) $options['url_parameter_name']) : '';
if ('' === $url_parameter_name || (isset($GLOBALS['wp']->public_query_vars) && in_array($url_parameter_name, (array) $GLOBALS['wp']->public_query_vars, true))) {
    $url_parameter_name = 'darkmode';
}
$enable_time = isset($options['enable_time']) ? $options['enable_time'] : "";
$enable_time_based_dark     = isset($enable_time['enable_time_based_dark']) ? $enable_time['enable_time_based_dark'] : "";
$enable_time_fields     = isset($enable_time['enable_time_fields']) ? $enable_time['enable_time_fields'] : '';
$time_based_dark_start      = isset($enable_time_fields['time_based_dark_start']) ? $enable_time_fields['time_based_dark_start'] : "";
$time_based_dark_stop       =  isset($enable_time_fields['time_based_dark_end'])  ?  $enable_time_fields['time_based_dark_end'] : "";

// Extras
$enable_scrollbar_dark = isset($options["enable_scrollbar_dark"]) ? $options["enable_scrollbar_dark"] : true;
$enable_frontend_iframe_dark_mode = isset($options['enable_frontend_iframe_dark_mode']) ? $options['enable_frontend_iframe_dark_mode'] : true;
$enable_switch_dragging = isset($options['enable_switch_dragging']) ? $options['enable_switch_dragging'] : "";
$enable_switch_attention = isset($options['enable_switch_attention']) ? $options['enable_switch_attention'] : '';
$switch_attention_effect = isset($options['switch_attention_effect']) ? $options['switch_attention_effect'] : 'pulse';
$disallowed_elements = isset($options['disallowed_elements']) ? DarkifyUtils::normalize_restriction_class($options['disallowed_elements']) : "";

$disallowed_elements_force_to_correct = isset($options['disallowed_elements_force_to_correct']) ? $options['disallowed_elements_force_to_correct'] : true;
$allowed_elements_force_to_correct = isset($options['allowed_elements_force_to_correct']) ? $options['allowed_elements_force_to_correct'] : true;
$allowed_elements = isset($options['allowed_elements']) ? DarkifyUtils::normalize_restriction_class($options['allowed_elements']) : "";

$alternative_dark_mode_switcher = isset($options['alternative_dark_mode_switcher']) ? $options['alternative_dark_mode_switcher'] : "";


// Image Control
$brightness =  isset($options['brightness']) ? $options['brightness'] : "";
$enable_low_image_brightness    =  isset($brightness['enable_low_image_brightness']) ? $brightness['enable_low_image_brightness'] : '1';
$low_image_brightness_label     =  isset($brightness['low_image_brightness_label']) ? 100 - $brightness['low_image_brightness_label'] : 100 - "20";
$disallowed_low_brightness_images   =  isset($brightness['disallowed_low_brightness_images']) ? DarkifyUtils::normalize_image_urls($brightness['disallowed_low_brightness_images']) : "";

$grayscale      =  isset($options['grayscale']) ? $options['grayscale'] : "";
$enable_image_grayscale =  isset($grayscale['enable_image_grayscale']) ? $grayscale['enable_image_grayscale'] : "";
$image_grayscale_label  =  isset($grayscale['image_grayscale_label']) ? $grayscale['image_grayscale_label'] : "80";
$disallowed_low_grayscale_images    =  isset($grayscale['disallowed_low_grayscale_images']) ? DarkifyUtils::normalize_image_urls($grayscale['disallowed_low_grayscale_images']) : "";

$darken_background  =  isset($options['darken_background']) ? $options['darken_background'] : "";
$enable_low_image_darken    =  isset($darken_background['enable_low_image_darken']) ? $darken_background['enable_low_image_darken'] : "1";
$low_image_darken_label     =  isset($darken_background['low_image_darken_label']) ? $darken_background['low_image_darken_label'] : '60';

$enable_invert_inline_svg = isset($options['enable_invert_inline_svg']) ? $options['enable_invert_inline_svg'] : "";

/*
 * The override rows are printed into a <script> as raw JSON (like
 * darkify_allowed_btn_class below), so each value is reduced to a known-safe
 * shape here: a hex colour or one of the fixed scope keys. Rows missing either
 * colour are dropped — a half-filled repeater row is the default state of the
 * field and means nothing to the engine.
 */
$darkify_color_overrides = array();
if (!empty($options['color_overrides']) && is_array($options['color_overrides'])) {
    $darkify_override_scopes = array('all', 'text', 'icon', 'background', 'border', 'shadow');
    foreach ($options['color_overrides'] as $darkify_override_row) {
        if (!is_array($darkify_override_row)) {
            continue;
        }

        $darkify_light = isset($darkify_override_row['light_color']) ? DarkifyUtils::sanitize_css_color($darkify_override_row['light_color']) : '';
        $darkify_dark = isset($darkify_override_row['dark_color']) ? DarkifyUtils::sanitize_css_color($darkify_override_row['dark_color']) : '';

        if (!$darkify_light || !$darkify_dark) {
            continue;
        }

        $darkify_scope = isset($darkify_override_row['override_scope']) ? $darkify_override_row['override_scope'] : 'all';
        if (!in_array($darkify_scope, $darkify_override_scopes, true)) {
            $darkify_scope = 'all';
        }

        $darkify_color_overrides[] = array(
            'light_color'    => $darkify_light,
            'dark_color'     => $darkify_dark,
            'override_scope' => $darkify_scope,
        );
    }
}

$invert         =  isset($options['invert']) ? $options['invert'] : "";
$enable_invert_images =  isset($invert['enable_invert_images']) ? $invert['enable_invert_images'] : "";
$invert_images_allowed_urls =  !empty($invert['invert_images_allowed_urls']) ? DarkifyUtils::normalize_image_urls($invert['invert_images_allowed_urls']) : "";

if ($invert_images_allowed_urls !== "") {
    $invert_images_allowed_urls_explode = explode(',', $invert_images_allowed_urls);
    $invert_images_allowed_urls_arr  =  json_encode($invert_images_allowed_urls_explode);
} else {
    $invert_images_allowed_urls_arr = "[]";
}

$normal_image_replacements = isset($options["image_replacements"][0]['normal_image']) ? $options["image_replacements"][0]['normal_image'] : '';
$dark_image_replacements = isset($options["image_replacements"][0]['dark_image']) ? $options["image_replacements"][0]['dark_image'] : '';
if ($normal_image_replacements || $dark_image_replacements) {
    $image_replacements = json_encode($options["image_replacements"]);
} else {
    $image_replacements = "";
}

// Video Control
$video_brightness =  isset($options['video_brightness']) ? $options['video_brightness'] : "";
$enable_low_video_brightness    =  isset($video_brightness['enable_low_video_brightness']) ? $video_brightness['enable_low_video_brightness'] : true;
$low_video_brightness_label     =  isset($video_brightness['low_video_brightness_label']) ? 100 - $video_brightness['low_video_brightness_label'] : 100 - "20";

$video_grayscale =  isset($options['video_grayscale']) ? $options['video_grayscale'] : '';
$enable_video_grayscale     =  isset($video_grayscale['enable_video_grayscale']) ? $video_grayscale['enable_video_grayscale'] : "";
$video_grayscale_label      =  isset($video_grayscale['video_grayscale_label']) ? $video_grayscale['video_grayscale_label']  : "80";

$normal_video_replacements = isset($options["video_replacements"][0]["normal_video"]) ? $options["video_replacements"][0]["normal_video"] : '';
$dark_video_replacements = isset($options["video_replacements"][0]["dark_video"]) ? $options["video_replacements"][0]["dark_video"] : '';
if ($normal_video_replacements || $dark_video_replacements) {
    $video_replacements = json_encode($options["video_replacements"]);
} else {
    $video_replacements = "[]";
}
if (!is_admin()) { ?>
    <?php
    /*
     * Release the pre-paint guard when the engine is done, not on a fixed timer.
     *
     * This used to be a flat `setTimeout(..., 800)`. On a warm reload that is
     * invisible — every asset is in cache, the engine's first walk lands early,
     * and it drops both classes itself long before the timer. On a HARD reload
     * it is the flicker: nothing is served from cache, so the engine script and
     * the stylesheets it reads are re-fetched and the first walk lands well
     * after 800ms. The timer fired first, un-hid `body` while the page still
     * carried the theme's LIGHT colours, and the walk then flipped the whole
     * page to dark a few hundred ms later. That flip is what the visitor sees
     * flickering.
     *
     * The timeout's real job is the failsafe: if the engine never runs (script
     * 404s, blocked by an optimiser, JS disabled mid-flight) the page must not
     * stay hidden. So the guard now comes off on a condition rather than a
     * duration:
     *
     *   - engine still to paint -> keep waiting; it removes the classes itself
     *     the moment its first walk has real colours on the page.
     *   - parsing finished and `window.darkify_engine_loaded` still unset ->
     *     the engine is not on this page and never will be, so release at once.
     *     That is strictly quicker than the old fixed 800ms in the case the
     *     timer actually existed for.
     *   - 4000ms -> release regardless, so no failure mode can leave the page
     *     hidden. Comfortably above the slowest measured hard reload.
     *
     * Parse state is the gate, not the presence of the engine's script tag: on
     * a large page the tag can sit hundreds of KB into <head> and simply not be
     * parsed yet at the moment we look, which reads as "no engine" on exactly
     * the slow load this is meant to protect.
     *
     * Only `darkify_fouc_guard` is polled. Once that is gone the engine has
     * demonstrably painted, so `darkify_prepaint` can be left to it; the two are
     * still dropped together if the failsafe ever has to fire, which preserves
     * the original guarantee that neither class can outlive the page load.
     *
     * No `>`/`<`/`&` anywhere in the snippet, so the ceiling is counted in
     * 100ms ticks (40 = 4s) and every test is an `===`/`!==`. wp_kses
     * entity-encodes all three characters inside script text — `n>=40` reaches
     * the browser as `n&gt;=40` and dies with a SyntaxError — which is also why
     * the gates are nested ifs rather than `&&`.
     */
    ?>
    <?php
    /*
     * URL parameter. Must stay ABOVE the FOUC snippet below: it works by writing
     * the visitor's remembered choice (`darkify_last_state`) before anything
     * reads it, so the pre-paint snippet, the engine's own start-up check and
     * every later page load all follow the link without a second code path —
     * exactly as if the visitor had used the switch. An unrecognised value is
     * ignored rather than treated as "off".
     *
     * Same wp_kses rules as the snippet below: no `>`, `<` or `&`.
     */
    if ($enable_url_parameter) { ?>
    <script type="text/javascript" class="darkify_inline_js">(function(){try{var v=new URLSearchParams(window.location.search).get('<?php echo esc_attr($url_parameter_name); ?>');if(v===null){return;}var m={'1':'1','on':'1','true':'1','dark':'1','0':'0','off':'0','false':'0','light':'0'};var k=v.toLowerCase();if(m.hasOwnProperty(k)){localStorage.darkify_last_state=m[k];}}catch(e){}}());</script>
    <?php } ?>
    <script type="text/javascript" class="darkify_inline_js">(function(){var s=localStorage.darkify_last_state,t=localStorage.darkify_selected_theme||'set1';if(s==='1'){var h=document.documentElement;h.classList.add('darkify_dark_mode_enabled');h.classList.add('darkify-'+t);h.classList.add('darkify_prepaint');h.classList.add('darkify_fouc_guard');var r=function(){h.classList.remove('darkify_prepaint');h.classList.remove('darkify_fouc_guard');};var n=0;var p=function(){if(!h.classList.contains('darkify_fouc_guard')){return;}n=n+1;if(n===40){r();return;}if(document.readyState!=='loading'){if(!window.darkify_engine_loaded){r();return;}}setTimeout(p,100);};setTimeout(p,100);}}());</script>
    <style type="text/css" class="darkify_inline_css">
        :root {
            <?php
            $dark_mode_color_palettes = [
                'set1' => [
                    'bg' => isset($options["dark_mode_color_set1"]) ? $options["dark_mode_color_set1"]['background'] : '#0F0F0F',
                    'secondary_bg' => isset($options["dark_mode_color_set1"]) ? $options["dark_mode_color_set1"]['secondary_background'] : '#171717',
                    'text_color' => isset($options["dark_mode_link_color_set1"]) ? $options["dark_mode_link_color_set1"]['text'] : '#BEBEBE',
                    'link_color' => isset($options["dark_mode_link_color_set1"]) ? $options["dark_mode_link_color_set1"]['color'] : '#E7E7E7',
                    'link_hover_color' => isset($options["dark_mode_link_color_set1"]) ? $options["dark_mode_link_color_set1"]['hover'] : '#BEBEBE',
                    'input_bg' => isset($options["dark_mode_input_color_set1"]) ? $options["dark_mode_input_color_set1"]['background'] : '#2D2D2D',
                    'input_text_color' => isset($options["dark_mode_input_color_set1"]) ? $options["dark_mode_input_color_set1"]['color'] : '#BEBEBE',
                    'input_placeholder_color' => isset($options["dark_mode_input_color_set1"]) ? $options["dark_mode_input_color_set1"]['placeholder'] : '#BEBEBE',
                    'border_color' => isset($options["dark_mode_border_color_set1"]) ? $options["dark_mode_border_color_set1"] : '#4A4A4A',
                    'btn_text_color' => isset($options["dark_mode_btn_color_set1"]) ? $options["dark_mode_btn_color_set1"]['color'] : '#BEBEBE',
                    'btn_bg' => isset($options["dark_mode_btn_color_set1"]) ? $options["dark_mode_btn_color_set1"]['background'] : '#4A4A4A',
                    'btn_text_hover_color' => isset($options["dark_mode_btn_color_set1"]) ? $options["dark_mode_btn_color_set1"]['hover_color'] : '#BEBEBE',
                    'btn_hover_bg' => isset($options["dark_mode_btn_color_set1"]) ? $options["dark_mode_btn_color_set1"]['hover_background'] : '#2D2D2D',
                    'btn_border_color' => isset($options["dark_mode_btn_color_set1"]['border']) ? $options["dark_mode_btn_color_set1"]['border'] : '#4A4A4A',
                    'btn_hover_border_color' => isset($options["dark_mode_btn_color_set1"]['hover_border']) ? $options["dark_mode_btn_color_set1"]['hover_border'] : '#2D2D2D',
                ],
                'set2' => [
                    'bg' => isset($options["dark_mode_color_set2"]) ? $options["dark_mode_color_set2"]['background'] : '#092635',
                    'secondary_bg' => isset($options["dark_mode_color_set2"]) ? $options["dark_mode_color_set2"]['secondary_background'] : '#081E29',
                    'text_color' => isset($options["dark_mode_link_color_set2"]) ? $options["dark_mode_link_color_set2"]['text'] : '#BEE0F1',
                    'link_color' => isset($options["dark_mode_link_color_set2"]) ? $options["dark_mode_link_color_set2"]['color'] : '#2B97CD',
                    'link_hover_color' => isset($options["dark_mode_link_color_set2"]) ? $options["dark_mode_link_color_set2"]['hover'] : '#BEE0F1',
                    'input_bg' => isset($options["dark_mode_input_color_set2"]) ? $options["dark_mode_input_color_set2"]['background'] : '#2D2D2D',
                    'input_text_color' => isset($options["dark_mode_input_color_set2"]) ? $options["dark_mode_input_color_set2"]['color'] : '#BEE0F1',
                    'input_placeholder_color' => isset($options["dark_mode_input_color_set2"]) ? $options["dark_mode_input_color_set2"]['placeholder'] : '#BEE0F1',
                    'border_color' => isset($options["dark_mode_border_color_set2"]) ? $options["dark_mode_border_color_set2"] : '#195979',
                    'btn_text_color' => isset($options["dark_mode_btn_color_set2"]) ? $options["dark_mode_btn_color_set2"]['color'] : '#BEE0F1',
                    'btn_bg' => isset($options["dark_mode_btn_color_set2"]) ? $options["dark_mode_btn_color_set2"]['background'] : '#195979',
                    'btn_text_hover_color' => isset($options["dark_mode_btn_color_set2"]) ? $options["dark_mode_btn_color_set2"]['hover_color'] : '#BEE0F1',
                    'btn_hover_bg' => isset($options["dark_mode_btn_color_set2"]) ? $options["dark_mode_btn_color_set2"]['hover_background'] : '#2D2D2D',
                    'btn_border_color' => isset($options["dark_mode_btn_color_set2"]['border']) ? $options["dark_mode_btn_color_set2"]['border'] : '#195979',
                    'btn_hover_border_color' => isset($options["dark_mode_btn_color_set2"]['hover_border']) ? $options["dark_mode_btn_color_set2"]['hover_border'] : '#2D2D2D',
                ],
                'set3' => [
                    'bg' => isset($options["dark_mode_color_set3"]) ? $options["dark_mode_color_set3"]['background'] : '#211e3c',
                    'secondary_bg' => isset($options["dark_mode_color_set3"]) ? $options["dark_mode_color_set3"]['secondary_background'] : '#302C57',
                    'text_color' => isset($options["dark_mode_link_color_set3"]) ? $options["dark_mode_link_color_set3"]['text'] : '#B1BBD8',
                    'link_color' => isset($options["dark_mode_link_color_set3"]) ? $options["dark_mode_link_color_set3"]['color'] : '#8071fb',
                    'link_hover_color' => isset($options["dark_mode_link_color_set3"]) ? $options["dark_mode_link_color_set3"]['hover'] : '#B1BBD8',
                    'input_bg' => isset($options["dark_mode_input_color_set3"]) ? $options["dark_mode_input_color_set3"]['background'] : '#2A264D',
                    'input_text_color' => isset($options["dark_mode_input_color_set3"]) ? $options["dark_mode_input_color_set3"]['color'] : '#B1BBD8',
                    'input_placeholder_color' => isset($options["dark_mode_input_color_set3"]) ? $options["dark_mode_input_color_set3"]['placeholder'] : '#B1BBD8',
                    'border_color' => isset($options["dark_mode_border_color_set3"]) ? $options["dark_mode_border_color_set3"] : '#4E478D',
                    'btn_text_color' => isset($options["dark_mode_btn_color_set3"]) ? $options["dark_mode_btn_color_set3"]['color'] : '#B1BBD8',
                    'btn_bg' => isset($options["dark_mode_btn_color_set3"]) ? $options["dark_mode_btn_color_set3"]['background'] : '#4E478D',
                    'btn_text_hover_color' => isset($options["dark_mode_btn_color_set3"]) ? $options["dark_mode_btn_color_set3"]['hover_color'] : '#B1BBD8',
                    'btn_hover_bg' => isset($options["dark_mode_btn_color_set3"]) ? $options["dark_mode_btn_color_set3"]['hover_background'] : '#2A264D',
                    'btn_border_color' => isset($options["dark_mode_btn_color_set3"]['border']) ? $options["dark_mode_btn_color_set3"]['border'] : '#4E478D',
                    'btn_hover_border_color' => isset($options["dark_mode_btn_color_set3"]['hover_border']) ? $options["dark_mode_btn_color_set3"]['hover_border'] : '#2A264D',
                ],
                'set4' => [
                    'bg' => isset($options["dark_mode_color_set4"]) ? $options["dark_mode_color_set4"]['background'] : '#1d253a',
                    'secondary_bg' => isset($options["dark_mode_color_set4"]) ? $options["dark_mode_color_set4"]['secondary_background'] : '#2B3655',
                    'text_color' => isset($options["dark_mode_link_color_set4"]) ? $options["dark_mode_link_color_set4"]['text'] : '#B1BBD8',
                    'link_color' => isset($options["dark_mode_link_color_set4"]) ? $options["dark_mode_link_color_set4"]['color'] : '#638eff',
                    'link_hover_color' => isset($options["dark_mode_link_color_set4"]) ? $options["dark_mode_link_color_set4"]['hover'] : '#B1BBD8',
                    'input_bg' => isset($options["dark_mode_input_color_set4"]) ? $options["dark_mode_input_color_set4"]['background'] : '#242F4C',
                    'input_text_color' => isset($options["dark_mode_input_color_set4"]) ? $options["dark_mode_input_color_set4"]['color'] : '#B1BBD8',
                    'input_placeholder_color' => isset($options["dark_mode_input_color_set4"]) ? $options["dark_mode_input_color_set4"]['placeholder'] : '#B1BBD8',
                    'border_color' => isset($options["dark_mode_border_color_set4"]) ? $options["dark_mode_border_color_set4"] : '#46598C',
                    'btn_text_color' => isset($options["dark_mode_btn_color_set4"]) ? $options["dark_mode_btn_color_set4"]['color'] : '#B1BBD8',
                    'btn_bg' => isset($options["dark_mode_btn_color_set4"]) ? $options["dark_mode_btn_color_set4"]['background'] : '#46598C',
                    'btn_text_hover_color' => isset($options["dark_mode_btn_color_set4"]) ? $options["dark_mode_btn_color_set4"]['hover_color'] : '#B1BBD8',
                    'btn_hover_bg' => isset($options["dark_mode_btn_color_set4"]) ? $options["dark_mode_btn_color_set4"]['hover_background'] : '#303D60',
                    'btn_border_color' => isset($options["dark_mode_btn_color_set4"]['border']) ? $options["dark_mode_btn_color_set4"]['border'] : '#46598C',
                    'btn_hover_border_color' => isset($options["dark_mode_btn_color_set4"]['hover_border']) ? $options["dark_mode_btn_color_set4"]['hover_border'] : '#303D60',
                ],
                'set5' => [
                    'bg' => isset($options["dark_mode_color_set5"]) ? $options["dark_mode_color_set5"]['background'] : '#1b1823',
                    'secondary_bg' => isset($options["dark_mode_color_set5"]) ? $options["dark_mode_color_set5"]['secondary_background'] : '#352f44',
                    'text_color' => isset($options["dark_mode_link_color_set5"]) ? $options["dark_mode_link_color_set5"]['text'] : '#C6C1D5',
                    'link_color' => isset($options["dark_mode_link_color_set5"]) ? $options["dark_mode_link_color_set5"]['color'] : '#b094ff',
                    'link_hover_color' => isset($options["dark_mode_link_color_set5"]) ? $options["dark_mode_link_color_set5"]['hover'] : '#C6C1D5',
                    'input_bg' => isset($options["dark_mode_input_color_set5"]) ? $options["dark_mode_input_color_set5"]['background'] : '#403953',
                    'input_text_color' => isset($options["dark_mode_input_color_set5"]) ? $options["dark_mode_input_color_set5"]['color'] : '#C6C1D5',
                    'input_placeholder_color' => isset($options["dark_mode_input_color_set5"]) ? $options["dark_mode_input_color_set5"]['placeholder'] : '#C6C1D5',
                    'border_color' => isset($options["dark_mode_border_color_set5"]) ? $options["dark_mode_border_color_set5"] : '#54496f',
                    'btn_text_color' => isset($options["dark_mode_btn_color_set5"]) ? $options["dark_mode_btn_color_set5"]['color'] : '#C6C1D5',
                    'btn_bg' => isset($options["dark_mode_btn_color_set5"]) ? $options["dark_mode_btn_color_set5"]['background'] : '#54496f',
                    'btn_text_hover_color' => isset($options["dark_mode_btn_color_set5"]) ? $options["dark_mode_btn_color_set5"]['hover_color'] : '#C6C1D5',
                    'btn_hover_bg' => isset($options["dark_mode_btn_color_set5"]) ? $options["dark_mode_btn_color_set5"]['hover_background'] : '#403953',
                    'btn_border_color' => isset($options["dark_mode_btn_color_set5"]['border']) ? $options["dark_mode_btn_color_set5"]['border'] : '#54496f',
                    'btn_hover_border_color' => isset($options["dark_mode_btn_color_set5"]['hover_border']) ? $options["dark_mode_btn_color_set5"]['hover_border'] : '#403953',
                ],
                'set6' => [
                    'bg' => isset($options["dark_mode_color_set6"]) ? $options["dark_mode_color_set6"]['background'] : '#082032',
                    'secondary_bg' => isset($options["dark_mode_color_set6"]) ? $options["dark_mode_color_set6"]['secondary_background'] : '#061825',
                    'text_color' => isset($options["dark_mode_link_color_set6"]) ? $options["dark_mode_link_color_set6"]['text'] : '#B5D9F3',
                    'link_color' => isset($options["dark_mode_link_color_set6"]) ? $options["dark_mode_link_color_set6"]['color'] : '#61bbff',
                    'link_hover_color' => isset($options["dark_mode_link_color_set6"]) ? $options["dark_mode_link_color_set6"]['hover'] : '#B5D9F3',
                    'input_bg' => isset($options["dark_mode_input_color_set6"]) ? $options["dark_mode_input_color_set6"]['background'] : '#0E3755',
                    'input_text_color' => isset($options["dark_mode_input_color_set6"]) ? $options["dark_mode_input_color_set6"]['color'] : '#B5D9F3',
                    'input_placeholder_color' => isset($options["dark_mode_input_color_set6"]) ? $options["dark_mode_input_color_set6"]['placeholder'] : '#B5D9F3',
                    'border_color' => isset($options["dark_mode_border_color_set6"]) ? $options["dark_mode_border_color_set6"] : '#144E78',
                    'btn_text_color' => isset($options["dark_mode_btn_color_set6"]) ? $options["dark_mode_btn_color_set6"]['color'] : '#B5D9F3',
                    'btn_bg' => isset($options["dark_mode_btn_color_set6"]) ? $options["dark_mode_btn_color_set6"]['background'] : '#144E78',
                    'btn_text_hover_color' => isset($options["dark_mode_btn_color_set6"]) ? $options["dark_mode_btn_color_set6"]['hover_color'] : '#B5D9F3',
                    'btn_hover_bg' => isset($options["dark_mode_btn_color_set6"]) ? $options["dark_mode_btn_color_set6"]['hover_background'] : '#0E3755',
                    'btn_border_color' => isset($options["dark_mode_btn_color_set6"]['border']) ? $options["dark_mode_btn_color_set6"]['border'] : '#144E78',
                    'btn_hover_border_color' => isset($options["dark_mode_btn_color_set6"]['hover_border']) ? $options["dark_mode_btn_color_set6"]['hover_border'] : '#0E3755',
                ],
                'set7' => [
                    'bg' => isset($options["dark_mode_color_set7"]) ? $options["dark_mode_color_set7"]['background'] : '#07161b',
                    'secondary_bg' => isset($options["dark_mode_color_set7"]) ? $options["dark_mode_color_set7"]['secondary_background'] : '#0d242e',
                    'text_color' => isset($options["dark_mode_link_color_set7"]) ? $options["dark_mode_link_color_set7"]['text'] : '#C5E6F0',
                    'link_color' => isset($options["dark_mode_link_color_set7"]) ? $options["dark_mode_link_color_set7"]['color'] : '#33c0ec',
                    'link_hover_color' => isset($options["dark_mode_link_color_set7"]) ? $options["dark_mode_link_color_set7"]['hover'] : '#C5E6F0',
                    'input_bg' => isset($options["dark_mode_input_color_set7"]) ? $options["dark_mode_input_color_set7"]['background'] : '#19495A',
                    'input_text_color' => isset($options["dark_mode_input_color_set7"]) ? $options["dark_mode_input_color_set7"]['color'] : '#C5E6F0',
                    'input_placeholder_color' => isset($options["dark_mode_input_color_set7"]) ? $options["dark_mode_input_color_set7"]['placeholder'] : '#C5E6F0',
                    'border_color' => isset($options["dark_mode_border_color_set7"]) ? $options["dark_mode_border_color_set7"] : '#286F8D',
                    'btn_text_color' => isset($options["dark_mode_btn_color_set7"]) ? $options["dark_mode_btn_color_set7"]['color'] : '#C5E6F0',
                    'btn_bg' => isset($options["dark_mode_btn_color_set7"]) ? $options["dark_mode_btn_color_set7"]['background'] : '#286F8D',
                    'btn_text_hover_color' => isset($options["dark_mode_btn_color_set7"]) ? $options["dark_mode_btn_color_set7"]['hover_color'] : '#C5E6F0',
                    'btn_hover_bg' => isset($options["dark_mode_btn_color_set7"]) ? $options["dark_mode_btn_color_set7"]['hover_background'] : '#19495A',
                    'btn_border_color' => isset($options["dark_mode_btn_color_set7"]['border']) ? $options["dark_mode_btn_color_set7"]['border'] : '#286F8D',
                    'btn_hover_border_color' => isset($options["dark_mode_btn_color_set7"]['hover_border']) ? $options["dark_mode_btn_color_set7"]['hover_border'] : '#19495A',
                ],
                'set8' => [
                    'bg' => isset($options["dark_mode_color_set8"]) ? $options["dark_mode_color_set8"]['background'] : '#15274C',
                    'secondary_bg' => isset($options["dark_mode_color_set8"]) ? $options["dark_mode_color_set8"]['secondary_background'] : '#112244',
                    'text_color' => isset($options["dark_mode_link_color_set8"]) ? $options["dark_mode_link_color_set8"]['text'] : '#ACC1EA',
                    'link_color' => isset($options["dark_mode_link_color_set8"]) ? $options["dark_mode_link_color_set8"]['color'] : '#6197ff',
                    'link_hover_color' => isset($options["dark_mode_link_color_set8"]) ? $options["dark_mode_link_color_set8"]['hover'] : '#ACC1EA',
                    'input_bg' => isset($options["dark_mode_input_color_set8"]) ? $options["dark_mode_input_color_set8"]['background'] : '#1a2f5c',
                    'input_text_color' => isset($options["dark_mode_input_color_set8"]) ? $options["dark_mode_input_color_set8"]['color'] : '#ACC1EA',
                    'input_placeholder_color' => isset($options["dark_mode_input_color_set8"]) ? $options["dark_mode_input_color_set8"]['placeholder'] : '#ACC1EA',
                    'border_color' => isset($options["dark_mode_border_color_set8"]) ? $options["dark_mode_border_color_set8"] : '#244892',
                    'btn_text_color' => isset($options["dark_mode_btn_color_set8"]) ? $options["dark_mode_btn_color_set8"]['color'] : '#ACC1EA',
                    'btn_bg' => isset($options["dark_mode_btn_color_set8"]) ? $options["dark_mode_btn_color_set8"]['background'] : '#244892',
                    'btn_text_hover_color' => isset($options["dark_mode_btn_color_set8"]) ? $options["dark_mode_btn_color_set8"]['hover_color'] : '#ACC1EA',
                    'btn_hover_bg' => isset($options["dark_mode_btn_color_set8"]) ? $options["dark_mode_btn_color_set8"]['hover_background'] : '#1a2f5c',
                    'btn_border_color' => isset($options["dark_mode_btn_color_set8"]['border']) ? $options["dark_mode_btn_color_set8"]['border'] : '#244892',
                    'btn_hover_border_color' => isset($options["dark_mode_btn_color_set8"]['hover_border']) ? $options["dark_mode_btn_color_set8"]['hover_border'] : '#1a2f5c',
                ],
                'set9' => [
                    'bg' => isset($options["dark_mode_color_set9"]) ? $options["dark_mode_color_set9"]['background'] : '#04261d',
                    'secondary_bg' => isset($options["dark_mode_color_set9"]) ? $options["dark_mode_color_set9"]['secondary_background'] : '#021e16',
                    'text_color' => isset($options["dark_mode_link_color_set9"]) ? $options["dark_mode_link_color_set9"]['text'] : '#C1D2BB',
                    'link_color' => isset($options["dark_mode_link_color_set9"]) ? $options["dark_mode_link_color_set9"]['color'] : '#00d29a',
                    'link_hover_color' => isset($options["dark_mode_link_color_set9"]) ? $options["dark_mode_link_color_set9"]['hover'] : '#C1D2BB',
                    'input_bg' => isset($options["dark_mode_input_color_set9"]) ? $options["dark_mode_input_color_set9"]['background'] : '#073d2f',
                    'input_text_color' => isset($options["dark_mode_input_color_set9"]) ? $options["dark_mode_input_color_set9"]['color'] : '#C1D2BB',
                    'input_placeholder_color' => isset($options["dark_mode_input_color_set9"]) ? $options["dark_mode_input_color_set9"]['placeholder'] : '#C1D2BB',
                    'border_color' => isset($options["dark_mode_border_color_set9"]) ? $options["dark_mode_border_color_set9"] : '#095541',
                    'btn_text_color' => isset($options["dark_mode_btn_color_set9"]) ? $options["dark_mode_btn_color_set9"]['color'] : '#C1D2BB',
                    'btn_bg' => isset($options["dark_mode_btn_color_set9"]) ? $options["dark_mode_btn_color_set9"]['background'] : '#095541',
                    'btn_text_hover_color' => isset($options["dark_mode_btn_color_set9"]) ? $options["dark_mode_btn_color_set9"]['hover_color'] : '#C1D2BB',
                    'btn_hover_bg' => isset($options["dark_mode_btn_color_set9"]) ? $options["dark_mode_btn_color_set9"]['hover_background'] : '#073d2f',
                    'btn_border_color' => isset($options["dark_mode_btn_color_set9"]['border']) ? $options["dark_mode_btn_color_set9"]['border'] : '#095541',
                    'btn_hover_border_color' => isset($options["dark_mode_btn_color_set9"]['hover_border']) ? $options["dark_mode_btn_color_set9"]['hover_border'] : '#073d2f',
                ],
                'set10' => [
                    'bg' => isset($options["dark_mode_color_set10"]) ? $options["dark_mode_color_set10"]['background'] : '#171004',
                    'secondary_bg' => isset($options["dark_mode_color_set10"]) ? $options["dark_mode_color_set10"]['secondary_background'] : '#211706',
                    'text_color' => isset($options["dark_mode_link_color_set10"]) ? $options["dark_mode_link_color_set10"]['text'] : '#E0D2BD',
                    'link_color' => isset($options["dark_mode_link_color_set10"]) ? $options["dark_mode_link_color_set10"]['color'] : '#e09525',
                    'link_hover_color' => isset($options["dark_mode_link_color_set10"]) ? $options["dark_mode_link_color_set10"]['hover'] : '#E0D2BD',
                    'input_bg' => isset($options["dark_mode_input_color_set10"]) ? $options["dark_mode_input_color_set10"]['background'] : '#372911',
                    'input_text_color' => isset($options["dark_mode_input_color_set10"]) ? $options["dark_mode_input_color_set10"]['color'] : '#E0D2BD',
                    'input_placeholder_color' => isset($options["dark_mode_input_color_set10"]) ? $options["dark_mode_input_color_set10"]['placeholder'] : '#E0D2BD',
                    'border_color' => isset($options["dark_mode_border_color_set10"]) ? $options["dark_mode_border_color_set10"] : '#5D4010',
                    'btn_text_color' => isset($options["dark_mode_btn_color_set10"]) ? $options["dark_mode_btn_color_set10"]['color'] : '#E0D2BD',
                    'btn_bg' => isset($options["dark_mode_btn_color_set10"]) ? $options["dark_mode_btn_color_set10"]['background'] : '#5D4010',
                    'btn_text_hover_color' => isset($options["dark_mode_btn_color_set10"]) ? $options["dark_mode_btn_color_set10"]['hover_color'] : '#E0D2BD',
                    'btn_hover_bg' => isset($options["dark_mode_btn_color_set10"]) ? $options["dark_mode_btn_color_set10"]['hover_background'] : '#372911',
                    'btn_border_color' => isset($options["dark_mode_btn_color_set10"]['border']) ? $options["dark_mode_btn_color_set10"]['border'] : '#5D4010',
                    'btn_hover_border_color' => isset($options["dark_mode_btn_color_set10"]['hover_border']) ? $options["dark_mode_btn_color_set10"]['hover_border'] : '#372911',
                ],
                'set11' => [
                    'bg' => isset($options["dark_mode_color_set11"]) ? $options["dark_mode_color_set11"]['background'] : '#19081b',
                    'secondary_bg' => isset($options["dark_mode_color_set11"]) ? $options["dark_mode_color_set11"]['secondary_background'] : '#210a24',
                    'text_color' => isset($options["dark_mode_link_color_set11"]) ? $options["dark_mode_link_color_set11"]['text'] : '#c09dc2',
                    'link_color' => isset($options["dark_mode_link_color_set11"]) ? $options["dark_mode_link_color_set11"]['color'] : '#c832d4',
                    'link_hover_color' => isset($options["dark_mode_link_color_set11"]) ? $options["dark_mode_link_color_set11"]['hover'] : '#c09dc2',
                    'input_bg' => isset($options["dark_mode_input_color_set11"]) ? $options["dark_mode_input_color_set11"]['background'] : '#2c082f',
                    'input_text_color' => isset($options["dark_mode_input_color_set11"]) ? $options["dark_mode_input_color_set11"]['color'] : '#c09dc2',
                    'input_placeholder_color' => isset($options["dark_mode_input_color_set11"]) ? $options["dark_mode_input_color_set11"]['placeholder'] : '#c09dc2',
                    'border_color' => isset($options["dark_mode_border_color_set11"]) ? $options["dark_mode_border_color_set11"] : '#440649',
                    'btn_text_color' => isset($options["dark_mode_btn_color_set11"]) ? $options["dark_mode_btn_color_set11"]['color'] : '#c09dc2',
                    'btn_bg' => isset($options["dark_mode_btn_color_set11"]) ? $options["dark_mode_btn_color_set11"]['background'] : '#440649',
                    'btn_text_hover_color' => isset($options["dark_mode_btn_color_set11"]) ? $options["dark_mode_btn_color_set11"]['hover_color'] : '#c09dc2',
                    'btn_hover_bg' => isset($options["dark_mode_btn_color_set11"]) ? $options["dark_mode_btn_color_set11"]['hover_background'] : '#2c082f',
                    'btn_border_color' => isset($options["dark_mode_btn_color_set11"]['border']) ? $options["dark_mode_btn_color_set11"]['border'] : '#440649',
                    'btn_hover_border_color' => isset($options["dark_mode_btn_color_set11"]['hover_border']) ? $options["dark_mode_btn_color_set11"]['hover_border'] : '#2c082f',
                ],
            ];

            $selected_set_key = isset($options['color_pallets']) ? $options['color_pallets'] : 'set1';
            $selected_set = $dark_mode_color_palettes[$selected_set_key] ?? $dark_mode_color_palettes['set1'];

            // Output as CSS variables
            foreach ($selected_set as $key => $value) {
                echo '--darkify_dark_mode_' . esc_attr($key) . ': ' . esc_attr($value) . ';';
            }
            ?>
        }
    </style>
<?php } else {
    /*
     * `dark` on <html> is the de-facto convention a React admin themes itself
     * off — Tailwind's class strategy, shadcn/ui, and Darkify's own React admin
     * (`@custom-variant dark (&:is(.dark *))`) all key on exactly this class.
     *
     * It used to be added only on Darkify's own SPA pages, which is why a
     * React-based admin screen from ANOTHER plugin stayed stubbornly light while
     * the rest of wp-admin went dark: such an app doesn't take its colours from
     * the cascade at all, it reads design tokens that only switch when `dark` is
     * present, and that class never arrived. Mirroring Admin Panel Dark Mode
     * onto it here — on every admin screen, not just ours — lets any app
     * following the convention inherit the theme with no plugin-specific
     * handling on either side.
     *
     * Setting it in <head> means the very first paint is already dark; the
     * runtime half of the mirror lives in client_main.js so the admin-bar switch
     * re-themes those screens live too. The `h.classList.add('dark')` call is
     * emitted as a static literal in the snippet below.
     */
    /*
     * `d` gates this FOUC snippet on Admin Panel Dark Mode actually being ON.
     * It used to be implicit: with the option off this template was never
     * included on an admin screen. Darkify's own SPA screens now always
     * include it (so the admin-bar icon can toggle without a reload), so
     * without this check a stale `darkify_admin_panel_last_state = "1"` would
     * darken the screen even though the option is off.
     *
     * Nested ifs, NOT `s==='1'&&d==='1'`: this whole template is printed
     * through wp_kses() (see the bottom of the file), whose entity
     * normalization rewrites a bare `&` to `&amp;` — script content is raw
     * text, so `&&` would reach the browser as `&amp;&amp;` and kill the
     * snippet with a SyntaxError. Keep this snippet free of `&` (and `<`).
     */
    /*
     * Effective admin darkening for THIS screen. Block Editor Dark Mode is
     * independent of Admin Panel Dark Mode: the block editor darkens when Block
     * Editor Dark Mode is on even if Admin Panel Dark Mode is off. Reused for the
     * runtime gate (darkify_admin_panel_dark_enabled) below.
     */
    $darkify_screen = \function_exists('get_current_screen') ? \get_current_screen() : null;
    $darkify_is_block_editor = $darkify_screen && \method_exists($darkify_screen, 'is_block_editor') && $darkify_screen->is_block_editor();
    $darkify_block_editor_dark = !empty($options['block_editor_dark_mode']);
    $darkify_effective_admin_dark = (!empty($options['enable_admin_panel_dark_mode']) || ($darkify_is_block_editor && $darkify_block_editor_dark)) ? '1' : '0';
    $darkify_admin_dark_on = $darkify_effective_admin_dark;
    ?>
    <script type="text/javascript" class="darkify_inline_js">(function(){var s=localStorage.darkify_admin_panel_last_state,t=localStorage.darkify_selected_theme||'set1',d='<?php echo esc_attr($darkify_admin_dark_on); ?>';if(s==='1'){if(d==='1'){var h=document.documentElement;h.classList.add('darkify_dark_mode_enabled');h.classList.add('darkify-'+t);h.classList.add('dark');}}}());</script>
    <style type="text/css" class="darkify_inline_css">
        :root {
            --darkify_dark_mode_bg: #181a1b;
            --darkify_dark_mode_secondary_bg: #202324;
            --darkify_dark_mode_text_color: #c8c4bd;
            --darkify_dark_mode_link_color: #6aafe2;
            --darkify_dark_mode_link_hover_color: #4f94c3;
            --darkify_dark_mode_input_bg: #2D2D2D;
            --darkify_dark_mode_input_text_color: #BEBEBE;
            --darkify_dark_mode_input_placeholder_color: #989898;
            --darkify_dark_mode_border_color: #4A4A4A;
            --darkify_dark_mode_btn_bg: #2D2D2D;
            --darkify_dark_mode_btn_text_color: #BEBEBE;
            --darkify_dark_mode_btn_hover_bg: #BEBEBE;
            --darkify_dark_mode_btn_text_hover_color: #2D2D2D;
            --darkify_dark_mode_btn_border_color: #2D2D2D;
            --darkify_dark_mode_btn_hover_border_color: #BEBEBE;
        }
    </style>
<?php }
if ($enable_scrollbar_dark) {
    $dark_mode_scrollbar_track_bg = !empty($options["scrollbar_color"]) ? $options["scrollbar_color"]['dark_mode_scrollbar_track_bg'] : '';
    $dark_mode_scrollbar_thumb_bg = !empty($options["scrollbar_color"]) ? $options["scrollbar_color"]['dark_mode_scrollbar_thumb_bg'] : '';
    include DarkifyUtils::darkify_locate_template('views/scrollbar.php');
}
?>

<script type="text/javascript" class="darkify_inline_js">
    var darkify_switch_unique_id = "<?php echo esc_attr($this->unique_id); ?>";
    var darkify_is_this_admin_panel = "<?php echo esc_attr(is_admin() ? "1" : "0"); ?>";
    <?php
    /*
     * Admin Panel Dark Mode, exposed so the admin engine can refuse to darken
     * while the option is off. Darkify's own SPA screens load the engine even
     * then, so the admin-bar icon can be toggled without a reload, which means
     * the engine has to honour the option itself rather than relying on simply
     * not being enqueued.
     */
    ?>
    var darkify_admin_panel_dark_enabled = "<?php echo esc_attr(isset($darkify_effective_admin_dark) ? $darkify_effective_admin_dark : (!empty($options['enable_admin_panel_dark_mode']) ? '1' : '0')); ?>";
    <?php
    /*
     * Site-wide palette defaults for the Dark Reader admin engine, one for the
     * block editor and one for everything else. "auto" means "let Dark Reader
     * derive every colour", which is the default and the recommended value.
     *
     * These are defaults, not overrides: the editor's toolbar dropdown writes a
     * per-user choice to localStorage and that still wins, which is how that
     * control has always behaved.
     */
    ?>
    var darkify_admin_palette_default = "<?php echo esc_attr(!empty($options['admin_panel_palette']) ? $options['admin_panel_palette'] : 'auto'); ?>";
    var darkify_editor_palette_default = "<?php echo esc_attr(!empty($options['block_editor_palette']) ? $options['block_editor_palette'] : 'auto'); ?>";
    var darkify_editor_palette_switcher = "<?php echo esc_attr(!isset($options['show_block_editor_palette']) || !empty($options['show_block_editor_palette']) ? '1' : ''); ?>";
    var darkify_enable_default_dark_mode = "<?php echo esc_attr($enable_default_dark_mode); ?>";
    var darkify_enable_os_aware = "<?php echo esc_attr($enable_os_aware); ?>";
    var darkify_enable_keyboard_shortcut = "<?php echo esc_attr($enable_keyboard_shortcut); ?>";
    var darkify_keyboard_shortcut_keys = "<?php echo esc_attr($keyboard_shortcut_keys); ?>";
    var darkify_enable_time_based_dark = "<?php echo esc_attr($enable_time_based_dark); ?>";
    var darkify_time_based_dark_start = "<?php echo esc_attr($time_based_dark_start ? $time_based_dark_start : "19:00"); ?>";
    var darkify_time_based_dark_stop = "<?php echo esc_attr($time_based_dark_stop ? $time_based_dark_stop : "07:00"); ?>";
    var darkify_enable_switch_dragging = "<?php echo esc_attr($enable_switch_dragging); ?>";
    var darkify_enable_switch_attention = "<?php echo esc_attr($enable_switch_attention); ?>";
    var darkify_switch_attention_effect = "<?php echo esc_attr($switch_attention_effect); ?>";

    var darkify_alternative_dark_mode_switch = "<?php echo esc_attr($alternative_dark_mode_switcher); ?>";
    var darkify_enable_frontend_iframe_dark_mode = "<?php echo esc_attr($enable_frontend_iframe_dark_mode); ?>";
    var darkify_enable_low_image_brightness = "<?php echo esc_attr($enable_low_image_brightness); ?>";
    var darkify_image_brightness_to = "<?php echo esc_attr($low_image_brightness_label); ?>";
    var darkify_disallowed_low_brightness_images = "<?php echo esc_attr($disallowed_low_brightness_images); ?>";
    var darkify_enable_image_grayscale = "<?php echo esc_attr($enable_image_grayscale); ?>";
    var darkify_image_grayscale_to = "<?php echo esc_attr($image_grayscale_label); ?>";
    var darkify_disallowed_grayscale_images = "<?php echo esc_attr($disallowed_low_grayscale_images); ?>";
    var darkify_enable_bg_image_darken = "<?php echo esc_attr($enable_low_image_darken); ?>";
    var darkify_bg_image_darken_to = "<?php echo esc_attr($low_image_darken_label); ?>";
    var darkify_enable_invert_inline_svg = "<?php echo esc_attr($enable_invert_inline_svg); ?>";
    var darkify_enable_invert_images = "<?php echo esc_attr($enable_invert_images); ?>";
    var darkify_invert_images_allowed_urls = "<?php echo esc_attr($invert_images_allowed_urls_arr); ?>";
    var darkify_image_replacements = "<?php echo esc_attr($image_replacements) ?>";
    var darkify_enable_low_video_brightness = "<?php echo esc_attr($enable_low_video_brightness); ?>";
    var darkify_video_brightness_to = "<?php echo esc_attr($low_video_brightness_label); ?>";
    var darkify_enable_video_grayscale = "<?php echo esc_attr($enable_video_grayscale); ?>";
    var darkify_video_grayscale_to = "<?php echo esc_attr($video_grayscale_label); ?>";
    var darkify_video_replacements = "<?PHP echo esc_attr($video_replacements); ?>";
    <?php
    /*
     * These four carry CSS selectors, not display text, and they are read back
     * by element.matches() in client_main.js. esc_attr() would HTML-encode the
     * selector combinators (`>` becomes `&gt;`, `&` becomes `&amp;`), and a
     * <script> body is raw text — the browser never decodes those back. One
     * encoded combinator anywhere in the list makes the whole comma-joined
     * selector a SyntaxError, matches() throws, and Disallowed Elements
     * silently stops restricting anything. Custom CSS selectors are folded into
     * the disallowed list automatically (see generateDisallowedElementsStr), so
     * a perfectly ordinary `.card > .title` rule was enough to trigger it.
     * darkify_js_string() emits a proper JSON string literal instead, which
     * keeps the selector intact and is still safe to sit inside <script>
     * (tags/ampersands are \u-escaped).
     */
    ?>
    var darkify_allowed_elements = <?php echo darkify_js_string($this->utils->generateAllowedElementsStr($options)); ?>;
    var darkify_allowed_elements_raw = <?php echo darkify_js_string($allowed_elements); ?>;
    var darkify_allowed_elements_force_to_correct = "<?php echo esc_attr($allowed_elements_force_to_correct); ?>";

    var darkify_disallowed_elements = <?php echo darkify_js_string($this->utils->generateDisallowedElementsStr($options, $this->external_support)); ?>;
    var darkify_disallowed_elements_raw = <?php echo darkify_js_string($disallowed_elements); ?>;
    var darkify_disallowed_elements_force_to_correct = "<?php echo esc_attr($disallowed_elements_force_to_correct); ?>";

    var darkify_allowed_btn_class = <?php echo json_encode($this->utils->addButtonClassByDarkify($options)); ?>;

    var darkify_color_overrides = <?php echo wp_json_encode($darkify_color_overrides); ?>;
</script>
<?php
$output = ob_get_clean();
$tags_allowed_in_output = array(
    'style' => array('type' => array(), 'class' => array()),
    'script' => array('type' => array(), 'class' => array())
);
echo wp_kses($this->utils->minify_string($output), $tags_allowed_in_output);
