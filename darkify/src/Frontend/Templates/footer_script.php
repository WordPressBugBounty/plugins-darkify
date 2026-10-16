<?php

use ThemeAtelier\Darkify\Includes\DarkifyUtils;

// don't call the file directly.
if (!defined('ABSPATH')) {
    exit;
}

$options = get_option('darkify');
$enable_dark_mode_switch = isset($options["enable_dark_mode_switch"]) ? $options["enable_dark_mode_switch"] : true;
$hide_on_desktop = isset($options["hide_on_desktop"]) ? $options["hide_on_desktop"] : '';
$hide_on_mobile = isset($options["hide_on_mobile"]) ? $options["hide_on_mobile"] : '';
$enable_admin_panel_dark_mode = isset($options["enable_admin_panel_dark_mode"]) ? $options["enable_admin_panel_dark_mode"] : '';
$block_editor_dark_mode = isset($options['block_editor_dark_mode']) ? $options['block_editor_dark_mode'] : false;
$hide_dark_mode_on_mobile = isset($hide_on_mobile["hide_dark_mode_on_mobile"]) ? $hide_on_mobile["hide_dark_mode_on_mobile"] : 0;
$type_of_hide_by = isset($hide_on_mobile["type_of_hide_by"]) ? $hide_on_mobile["type_of_hide_by"] : 0;

$normal_custom_css = isset($options["normal_custom_css"]) ? $options["normal_custom_css"] : "";
$custom_css = isset($options["custom_css"]) ? $options["custom_css"] : "";
$admin_custom_css = isset($options['admin_custom_css']) ? $options['admin_custom_css'] : "";

if (!is_admin()) {
    if ($enable_dark_mode_switch) {
        if (!$this->utils->is_hidden_by_user_agent($hide_on_desktop, $hide_dark_mode_on_mobile, $type_of_hide_by)) {
            include_once DarkifyUtils::darkify_locate_template('views/switch.php');
        }
    }
}
?>

<style type="text/css" class="darkify_inline_css">
    <?php
    // CSS body, not HTML: no core output-escaping function applies. The rules
    // are assembled by parseAndProcessNormalCustomCSS() and wp_strip_all_tags()
    // removes any tag-based `</style>` breakout, so the string is safe to print.
    echo wp_strip_all_tags(DarkifyUtils::parseAndProcessNormalCustomCSS($normal_custom_css)); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    ?>
</style>
<style type="text/css" class="darkify_inline_css">
    <?php
    echo wp_strip_all_tags(DarkifyUtils::parseAndProcessCustomCSS($custom_css, false)); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    ?>
</style>

<?php if (!is_admin()) { ?>
    <script type="text/javascript" class="darkify_inline_js">
        document.addEventListener("DOMContentLoaded", function(event) {
            darkify_init_alternative_dark_mode_switch();
            darkify_init_attention_effect();
            if (typeof darkify_init_draggable_floating_switch === "function") {
                darkify_init_draggable_floating_switch();
            }
        });
    </script>
<?php }

if (is_admin() && $block_editor_dark_mode) {
?><style type="text/css" class="darkify_inline_css">
        <?php echo esc_html(DarkifyUtils::parseAndProcessCustomCSS($admin_custom_css, 'yes')); ?>
    </style>
    <?php
    /* Block editor dark switch — injected whenever Block Editor Dark Mode is on,
       independently of Admin Panel Dark Mode. */
    if (class_exists('WP_Block_Type_Registry')) {

            if (get_current_screen() && 'post' === get_current_screen()->base && ('post' === get_current_screen()->post_type || 'page' === get_current_screen()->post_type)) { ?>
                <script type="text/javascript" class="darkify_inline_js">
                    (function checkForWP() {
                        if (
                            typeof wp === 'undefined' ||
                            typeof wp.domReady === 'undefined' ||
                            typeof wp.plugins === 'undefined' ||
                            typeof wp.element === 'undefined'
                        ) {
                            return setTimeout(checkForWP, 200);
                        }

                        wp.domReady(function() {

                            function injectDarkify() {
                                // Target the LEFT side toolbar
                                const target =
                                    document.querySelector('.editor-header__toolbar') ||
                                    document.querySelector('.edit-post-header-toolbar') ||
                                    document.querySelector('.editor-document-tools');

                                if (!target) return false;
                                if (target.querySelector('.darkify-dropdown')) return true;

                                const themes = [{
                                        // Hands the colours back to Dark Reader's own
                                        // per-colour derivation instead of pinning a
                                        // background and a text colour. First in the
                                        // list and the default, because a derived
                                        // palette keeps distinctions a fixed one
                                        // flattens.
                                        value: 'auto',
                                        label: 'Auto',
                                        cls: 'auto_palette'
                                    },
                                    {
                                        value: 'set1',
                                        label: 'Carbon Mist',
                                        cls: 'carbon_mist'
                                    },
                                    {
                                        value: 'set3',
                                        label: 'Midnight Reverie',
                                        cls: 'midnight_reverie'
                                    },
                                    {
                                        value: 'set9',
                                        label: 'Verdant Depths',
                                        cls: 'verdant_depths'
                                    },
                                    {
                                        value: 'set6',
                                        label: 'Celestial Tide',
                                        cls: 'celestial_tide'
                                    },
                                    {
                                        value: 'set10',
                                        label: 'Emberwood',
                                        cls: 'emberwood'
                                    },
                                    {
                                        value: 'set2',
                                        label: 'Glacier Drift',
                                        cls: 'glacier_drift',
                                        pro: true
                                    },
                                    {
                                        value: 'set4',
                                        label: 'Indigo Veil',
                                        cls: 'indigo_veil',
                                        pro: true
                                    },
                                    {
                                        value: 'set5',
                                        label: 'Duskmire',
                                        cls: 'duskmire',
                                        pro: true
                                    },
                                    {
                                        value: 'set7',
                                        label: 'Tidal Frost',
                                        cls: 'tidal_frost',
                                        pro: true
                                    },
                                    {
                                        value: 'set8',
                                        label: 'Blue Bastion',
                                        cls: 'blue_bastion',
                                        pro: true
                                    },
                                    {
                                        value: 'set11',
                                        label: 'Crimson Veil',
                                        cls: 'crimson_veil',
                                        pro: true
                                    },
                                ];

                                // Dark mode toggle button
                                const button = document.createElement('button');
                                button.className = 'darkify_block_editor_switch darkify_ignore';
                                button.innerHTML = '<div class="icon"></div>';
                                button.onclick = function() {
                                    darkify_switch_trigger();
                                };

                                // Dropdown wrapper
                                const wrapper = document.createElement('div');
                                wrapper.className = 'darkify-dropdown';
                                wrapper.style.cssText = 'position:relative;display:inline-flex;align-items:center;';

                                const selected = document.createElement('div');
                                selected.className = 'darkify-selected';

                                const list = document.createElement('div');
                                list.className = 'darkify-options';

                                themes.forEach(t => {
                                    const item = document.createElement('div');
                                    if (t.pro) {
                                        item.className = 'darkify-option darkify-pro-option';
                                        item.innerHTML = `<span class="theme_color ${t.cls}"></span> ${t.label}<span class="darkify-option-pro-badge">PRO</span>`;
                                        item.title = 'Upgrade to Pro to unlock this theme';
                                    } else {
                                        item.className = 'darkify-option';
                                        item.innerHTML = `<span class="theme_color ${t.cls}"></span> ${t.label}`;
                                        item.onclick = () => {
                                            selected.innerHTML = `<span class="theme_color ${t.cls}"></span> ${t.label}`;
                                            localStorage.darkify_selected_theme = t.value;
                                            darkify_theme_select(t.value);
                                            list.classList.remove('show');
                                        };
                                    }
                                    list.appendChild(item);
                                });

                                // Load saved theme — fall back to first free theme if saved value is pro-only
                                // Per-user choice first, then the site default from
                                // settings, then Auto — the same precedence the engine
                                // applies, so the label always names what is painted.
                                const savedVal = localStorage.darkify_selected_theme
                                    || (typeof darkify_editor_palette_default !== 'undefined' ? darkify_editor_palette_default : '')
                                    || 'auto';
                                const savedTheme = themes.find(t => t.value === savedVal && !t.pro) || themes[0];
                                selected.innerHTML = `<span class="theme_color ${savedTheme.cls}"></span> ${savedTheme.label}`;

                                selected.onclick = (e) => {
                                    e.stopPropagation();
                                    list.classList.toggle('show');
                                };

                                document.addEventListener('click', function(e) {
                                    if (!wrapper.contains(e.target)) list.classList.remove('show');
                                });

                                wrapper.appendChild(selected);
                                wrapper.appendChild(list);

                                // Append to END of left toolbar
                                target.appendChild(button);
                                target.appendChild(wrapper);

                                return true;
                            }

                            if (!injectDarkify()) {
                                const interval = setInterval(function() {
                                    if (injectDarkify()) clearInterval(interval);
                                }, 300);
                                setTimeout(function() {
                                    clearInterval(interval);
                                }, 10000);
                            }
                        });
                    })();
                </script>
<?php }
        }
    }
