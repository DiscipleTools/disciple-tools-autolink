<div class="wrap">
    <div id="poststuff">
        <div id="post-body" class="metabox-holder columns-2">
            <div id="post-body-content">

				<?php if ( $error ): ?>
                    <div class="notice notice-error is-dismissible">
                        <p>
							<?php echo esc_attr( $error ) ?>
                        </p>
                    </div>
				<?php endif; ?>

                <!-- Main Column -->

                <form method="post">
					<?php wp_nonce_field( 'dt_admin_form', 'dt_admin_form_nonce' ) ?>
                    <table class="widefat striped">
                        <thead>
                        <tr>
                            <th><?php echo esc_html( $training_videos_translations['label'] ) ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <tr>
                            <td>
                                <div style="max-width: 600px">
                                    <admin-training-videos-field
                                            name="disciple_tools_autolink_training_videos"
                                            value='<?php echo esc_attr( $training_videos ) ?>'
                                            default='<?php echo esc_attr( $default_training_videos ) ?>'
                                            translations='<?php echo esc_attr( json_encode( $training_videos_translations ) ) ?>'
                                    ></admin-training-videos-field>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <button class="button button-primary">
									<?php esc_html_e( 'Save', 'disciple-tools-autolink' ) ?>
                                </button>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </form>
                <br>

                <!-- End Main Column -->
            </div><!-- end post-body-content -->
            <div id="postbox-container-1" class="postbox-container">
                <!-- Right Column -->

                <!-- Box -->
                <table class="widefat striped">
                    <thead>
                    <tr>
                        <th>
							<?php esc_html_e( 'Help', 'disciple-tools-autolink' ) ?>
                        </th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr>
                        <td>
                            <p>
								<?php esc_html_e( 'Training videos are available from within the AutoLink main menu.', 'disciple-tools-autolink' ) ?>
                            </p>
                            <p>
								<?php esc_html_e( 'Unchecking "Show training menu item?" hides Training from the AutoLink menu. Removing every video hides it as well.', 'disciple-tools-autolink' ) ?>
                            </p>
                            <p>
                                <a href="<?php echo esc_url( $general_tab_url ) ?>">
									<?php esc_html_e( 'Menu item settings', 'disciple-tools-autolink' ) ?>
                                </a>
                            </p>
                            <p>
                                <a class="button" href="<?php echo esc_attr( $training_videos_url ) ?>">
									<?php esc_html_e( 'View Training Videos', 'disciple-tools-autolink' ) ?>
                                </a>
                            </p>
                        </td>
                    </tr>
                    </tbody>
                </table>
                <br>

                <!-- End Right Column -->
            </div><!-- postbox-container 1 -->
            <div id="postbox-container-2" class="postbox-container">
            </div><!-- postbox-container 2 -->
        </div><!-- post-body meta box container -->
    </div><!--poststuff end -->
</div><!-- wrap end -->
