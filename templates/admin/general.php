<div class="wrap">
	<?php if ( $error ): ?>
        <div class="notice notice-error is-dismissible">
            <p>
				<?php echo esc_attr( $error ) ?>
            </p>
        </div>
	<?php endif; ?>

    <form method="post">
		<?php wp_nonce_field( 'dt_admin_form', 'dt_admin_form_nonce' ) ?>

        <table class="widefat striped">
            <thead>
            <tr>
                <th colspan="2"><?php esc_html_e( 'Disciple.Tools Menu', 'disciple-tools-autolink' ) ?></th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td style="white-space: nowrap; vertical-align: top; width: 1%;">
					<?php esc_html_e( 'Add main DT menu link?', 'disciple-tools-autolink' ) ?>
                </td>
                <td>
                    <label>
                        <input type="checkbox"
                               name="disciple_tools_autolink_show_in_menu"
                               value="1"
						       <?php checked( $old['disciple_tools_autolink_show_in_menu'], '1' ) ?> />
						<?php esc_html_e( 'Show', 'disciple-tools-autolink' ) ?>
                    </label>
                    <p class="description">
						<?php esc_html_e( 'If enabled, an "Autolink" menu item will be present in the primary Disciple.Tools menu allowing easy access to Autolink.', 'disciple-tools-autolink' ) ?>
                    </p>
                </td>
            </tr>
            </tbody>
        </table>
        <br>

        <table class="widefat striped">
            <thead>
            <tr>
                <th colspan="2"><?php esc_html_e( 'Autolink App Menu', 'disciple-tools-autolink' ) ?></th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td style="white-space: nowrap; vertical-align: top; width: 1%;">
					<?php esc_html_e( 'Show training menu item?', 'disciple-tools-autolink' ) ?>
                </td>
                <td>
                    <label>
                        <input type="checkbox"
                               name="disciple_tools_autolink_show_training"
                               value="1"
						       <?php checked( $old['disciple_tools_autolink_show_training'], '1' ) ?> />
						<?php esc_html_e( 'Show', 'disciple-tools-autolink' ) ?>
                    </label>
                    <p class="description">
						<?php esc_html_e( 'Unchecking "Show training menu item?" hides Training from the AutoLink menu. Removing every video hides it as well.', 'disciple-tools-autolink' ) ?>
                        <a href="<?php echo esc_url( $training_tab_url ) ?>">
							<?php esc_html_e( 'Manage training videos', 'disciple-tools-autolink' ) ?>
                        </a>
                    </p>
                </td>
            </tr>
            <tr>
                <td style="white-space: nowrap; vertical-align: top; width: 1%;">
					<?php esc_html_e( 'Show survey?', 'disciple-tools-autolink' ) ?>
                </td>
                <td>
                    <label>
                        <input type="checkbox"
                               name="disciple_tools_autolink_show_survey"
                               value="1"
						       <?php checked( $old['disciple_tools_autolink_show_survey'], '1' ) ?> />
						<?php esc_html_e( 'Show', 'disciple-tools-autolink' ) ?>
                    </label>
                </td>
            </tr>
            </tbody>
        </table>
        <br>

        <table class="widefat striped">
            <thead>
            <tr>
                <th colspan="2"><?php echo esc_html( $group_labels->name ) ?></th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td style="white-space: nowrap; vertical-align: top; width: 1%;">
					<?php esc_html_e( 'Allow parent group selection?', 'disciple-tools-autolink' ) ?>
                </td>
                <td>
                    <label>
                        <input type="checkbox"
                               name="disciple_tools_autolink_allow_parent_group_selection"
                               value="1"
						       <?php checked( $old['disciple_tools_autolink_allow_parent_group_selection'], '1' ) ?> />
						<?php esc_html_e( 'Allow', 'disciple-tools-autolink' ) ?>
                    </label>
                    <p class="description">
						<?php esc_html_e( "If enabled, the user will be able to select a parent group when creating a new group.", 'disciple-tools-autolink' ) ?>
						<?php esc_html_e( "If disabled, the group will be assigned to the first leader's first group.", 'disciple-tools-autolink' ) ?>
                    </p>
                </td>
            </tr>
            </tbody>
        </table>
        <br>

        <table class="widefat striped">
            <thead>
            <tr>
                <th colspan="2"><?php echo esc_html( $contact_labels->name ) ?></th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td style="white-space: nowrap; vertical-align: top; width: 1%;">
					<?php
					/* translators: %s: plural name of the contacts post type, e.g. "Contacts". */
					echo esc_html( sprintf( __( 'Enable %s?', 'disciple-tools-autolink' ), $contact_labels->name ) );
					?>
                </td>
                <td>
                    <label>
                        <input type="checkbox"
                               name="disciple_tools_autolink_enable_contacts"
                               value="1"
						       <?php checked( $old['disciple_tools_autolink_enable_contacts'], '1' ) ?> />
						<?php esc_html_e( 'Enable', 'disciple-tools-autolink' ) ?>
                    </label>
                    <p class="description">
						<?php
						/* translators: %s: plural name of the contacts post type, e.g. "Contacts". */
						echo esc_html( sprintf( __( 'Adds a %s tab to the Autolink app, and contact links that open Autolink as a contact with no login.', 'disciple-tools-autolink' ), $contact_labels->name ) );
						?>
                    </p>
                    <p class="description">
						<?php esc_html_e( 'While disabled, contact links that were already shared stop working.', 'disciple-tools-autolink' ) ?>
                    </p>
                </td>
            </tr>
            <tr>
                <td style="white-space: nowrap; vertical-align: top; width: 1%;">
					<?php esc_html_e( 'Contact links last', 'disciple-tools-autolink' ) ?>
                </td>
                <td>
                    <input type="number"
                           name="disciple_tools_autolink_contact_link_validity_amount"
                           min="1"
                           step="1"
                           style="width: 5em;"
                           value="<?php echo esc_attr( $old['disciple_tools_autolink_contact_link_validity_amount'] ) ?>"/>
                    <select name="disciple_tools_autolink_contact_link_validity_unit">
						<?php foreach ( $validity_units as $unit => $unit_label ): ?>
                            <option value="<?php echo esc_attr( $unit ) ?>" <?php selected( $old['disciple_tools_autolink_contact_link_validity_unit'], $unit ) ?>>
								<?php echo esc_html( $unit_label ) ?>
                            </option>
						<?php endforeach; ?>
                    </select>
                    <p class="description">
						<?php esc_html_e( 'A contact link opens Autolink as that contact, with no login. A link is created when someone presses Copy link, and stops working after this long.', 'disciple-tools-autolink' ) ?>
                    </p>
                    <p class="description">
						<?php esc_html_e( 'Copying a link that still works extends it rather than replacing it, so copies already shared keep working. Changing this setting affects links copied from now on.', 'disciple-tools-autolink' ) ?>
                    </p>
                </td>
            </tr>
            </tbody>
        </table>
        <br>

		<?php submit_button( __( 'Save', 'disciple-tools-autolink' ) ) ?>
    </form>
</div><!-- wrap end -->
