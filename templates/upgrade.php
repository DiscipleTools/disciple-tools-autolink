<?php include 'parts/app-header.php'; ?>
<?php include 'parts/app-greeting.php'; ?>

<div class="container">
    <dt-tile class="upgrade">
        <div class="section__inner">
            <div class="upgrade__heading">
                <h3><?php echo esc_html( $translations['heading'] ); ?></h3>
                <span class="help-text cloak"><?php echo esc_html( $translations['help_text'] ); ?></span>
            </div>

            <p class="upgrade__keeps"><?php echo esc_html( $translations['keeps_label'] ); ?></p>

			<?php if ( $error ) : ?>
                <dt-alert context="alert" dismissable><?php echo esc_html( $error ); ?></dt-alert>
			<?php endif; ?>

            <form class="app-form" method="post" action="<?php echo esc_url( $form_action ); ?>">
                <input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( $nonce ) ); ?>"/>

                <dt-text name="username"
                         label="<?php echo esc_attr( $translations['username_label'] ); ?>"
                         value="<?php echo esc_attr( $username ); ?>"
                         required></dt-text>

                <dt-text name="email"
                         type="email"
                         label="<?php echo esc_attr( $translations['email_label'] ); ?>"
                         value="<?php echo esc_attr( $email ); ?>"
                         required></dt-text>

                <label class="upgrade__label" for="upgrade-password"><?php echo esc_html( $translations['password_label'] ); ?></label>
                <input class="upgrade__input" id="upgrade-password" type="password" name="password" required/>

                <label class="upgrade__label" for="upgrade-confirm"><?php echo esc_html( $translations['confirm_label'] ); ?></label>
                <input class="upgrade__input" id="upgrade-confirm" type="password" name="confirm_password" required/>

                <p class="upgrade__notice"><?php echo esc_html( $translations['revoke_notice'] ); ?></p>

                <div class="upgrade__actions">
                    <submit-button type="submit" context="success"><?php echo esc_html( $translations['submit_label'] ); ?></submit-button>
                    <dt-button href="<?php echo esc_url( $cancel_url ); ?>" context="inactive"><?php echo esc_html( $translations['cancel_label'] ); ?></dt-button>
                </div>
            </form>
        </div>
    </dt-tile>
</div>

<?php include 'parts/app-footer.php'; ?>
