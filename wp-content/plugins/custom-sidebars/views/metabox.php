<?php
 * Uses:
 *   $selected
 */
global $wp_registered_sidebars;
$available = $wp_registered_sidebars;
<p>
<?php if ( ! empty( $sidebars ) ) { ?>
			<b><?php echo esc_html( $sb_name ); ?></b>:
			<select name="cs_replacement_<?php echo esc_attr( $s ); ?>"
				id="cs_replacement_<?php echo esc_attr( $s ); ?>"
				class="cs-replacement-field <?php echo esc_attr( $s ); ?>">
				<option value=""></option>