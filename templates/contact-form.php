<?php include 'parts/app-header.php'; ?>

<div class="container">
    <dt-tile class="contact-form" title="<?php echo esc_attr( $translations['heading'] ); ?>">
        <div class="section__inner">
            <span class="help-text cloak"><?php echo esc_html( $translations['help_text'] ); ?></span>

			<?php if ( $error ) : ?>
                <dt-alert context="alert" dismissable><?php echo esc_html( $error ); ?></dt-alert>
			<?php endif; ?>

            <form class="app-form" method="post" action="<?php echo esc_url( $form_action ); ?>">
                <input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( $nonce ) ); ?>"/>

                <dt-text name="name"
                         label="<?php echo esc_attr( $translations['name_label'] ); ?>"
                         value="<?php echo esc_attr( $name ); ?>"
                         required></dt-text>

                <div class="contact-form__actions">
                    <submit-button type="submit" context="success"><?php echo esc_html( $translations['submit_label'] ); ?></submit-button>
                    <dt-button href="<?php echo esc_url( $cancel_url ); ?>" context="inactive"><?php echo esc_html( $translations['cancel_label'] ); ?></dt-button>
                </div>
            </form>
        </div>
    </dt-tile>
</div>

<?php include 'parts/app-footer.php'; ?>
