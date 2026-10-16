<?php
defined('ABSPATH') || exit();

class Elementor_Darkify_Switcher extends \Elementor\Base_Data_Control
{

	public function get_type()
	{
		return 'darkify_switch';
	}

	/**
	 * Enqueue control scripts and styles.
	 *
	 * @since  1.0.0
	 * @access public
	 */
	public function enqueue()
	{
		wp_enqueue_style('darkify-admin-common', DARKIFY_DIR_URL . 'src/Admin/elementor/assets/elementor-switcher.css', [], DARKIFY_VERSION);
	}

	/**
	 * Render choose control output in the editor.
	 *
	 * Used to generate the control HTML in the editor using Underscore JS
	 * template. The variables for the class are available using `data` JS
	 * object.
	 *
	 * A three-column grid of preview cards. Each card is a real radio input
	 * (visually hidden, driven by its `<label>`) so Elementor's own
	 * `data-setting` binding and the arrow-key behaviour of a radio group both
	 * keep working untouched.
	 *
	 * Two things the previous template did not do:
	 *
	 *   - It showed the artwork with no name, so telling "Dark Side" from
	 *     "Dark Inner" meant guessing. Each card now carries its label, taken
	 *     from the `style_labels` map the widget passes in.
	 *   - Its lock test was `switchId > 3`, and `switchId` is a slug
	 *     ("classic", "expand", …). `'classic' > 3` is a NaN comparison, so it
	 *     was always false and the `disabled` attribute was never written —
	 *     leaving the stylesheet's `pointer-events: none` as the only guard.
	 *     That stops a mouse but not the keyboard, and these inputs are only
	 *     clip-hidden, so they stay focusable and arrow keys walked straight
	 *     into the Pro styles. The lock is now a position test against the
	 *     `unlocked` count the widget supplies (0 meaning no limit), and it
	 *     writes a real `disabled`.
	 *
	 * @return mixed
	 * @since  1.0.0
	 * @access public
	 */
	public function content_template()
	{
?>
		<div class="elementor-control-field darkify-style-field">
			<label class="elementor-control-title">{{{ data.label }}}</label>
			<div class="darkify-elementor">
				<div class="darkify-elementor-switches" role="radiogroup">
					<# _.each( data.options, function( switchId, index ) {
						var isLocked = data.unlocked > 0 && index >= data.unlocked;
						var label = ( data.style_labels && data.style_labels[ switchId ] ) ? data.style_labels[ switchId ] : switchId;
					#>
						<div class="darkify-elementor-switches-item elementor-control-input-wrapper<# if ( isLocked ) { #> darkify-locked<# } #>">
							<input id="{{ data.name }}-{{ switchId }}" type="radio" name="{{ data.name }}" data-setting="{{ data.name }}" value="{{ switchId }}"
								<# if ( isLocked ) { #> disabled<# } #>
								<# if ( switchId == data.controlValue ) { #> checked="checked"<# } #> />
							<label for="{{ data.name }}-{{ switchId }}" title="{{ label }}">
								<span class="darkify-switch-preview">
									<img src="{{ data.assets_url }}{{ switchId }}.svg" alt="" loading="lazy" />
								</span>
								<span class="darkify-switch-name">{{ label }}</span>
								<# if ( isLocked ) { #><span class="darkify-switch-pro">Pro</span><# } #>
							</label>
						</div>
					<# }); #>
				</div>
			</div>
		</div>

		<# if ( data.description ) { #>
			<div class="elementor-control-field-description">{{{ data.description }}}</div>
			<# } #>
		<?php
	}

	/**
	 * `unlocked` is how many of the listed styles may be chosen, counted from
	 * the start of `options`; 0 means no limit. `style_labels` maps each style
	 * slug to its display name. Both are defaulted here so the template never
	 * has to guard against a widget that forgot to pass them — and an absent
	 * `unlocked` reads as "no limit" rather than locking everything.
	 */
	protected function get_default_settings()
	{
		return [
			'options'      => [],
			'toggle'       => true,
			'unlocked'     => 0,
			'style_labels' => [],
		];
	}
}
