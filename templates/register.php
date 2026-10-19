<?php
/**
 * @var $logo_url string
 * @var $form_action string
 * @var $error string
 */
?>
<?php include 'parts/header.php'; ?>

<div class="container register">
    <dt-tile>
        <div class="section__inner">
            <div class="logo">
                <img src="<?php echo esc_url( $logo_url ) ?>"
                     alt="Disciple.Tools"
                     class="logo__image">
            </div>

            <form class="app-form"
                  action="<?php echo esc_attr( $form_action ) ?>"
                  method="POST">
                <?php wp_nonce_field( 'dt_autolink_register' ); ?>

                <?php if ( ! empty( $error ) ) : ?>
                    <dt-alert context="alert"
                              dismissable>
                        <?php echo esc_html( $error ) ?>
                    </dt-alert>
                <?php endif; ?>

                <dt-text name="username"
                         placeholder="<?php esc_attr_e( 'Username', 'disciple-tools-autolink' ); ?>"
                         value="<?php echo esc_attr( $username ); ?>"
                         required></dt-text>
                <dt-text name="email"
                         placeholder="<?php esc_attr_e( 'Email', 'disciple-tools-autolink' ); ?>"
                         value="<?php echo esc_attr( $email ); ?>"
                         required></dt-text>
                <dt-text name="password"
                         placeholder="<?php esc_attr_e( 'Password', 'disciple-tools-autolink' ); ?>"
                         value="<?php echo esc_attr( $password ); ?>"
                         type="password"
                         required></dt-text>
                <dt-text name="confirm_password"
                         placeholder="<?php esc_attr_e( 'Confirm Password', 'disciple-tools-autolink' ); ?>"
                         value=""
                         type="password"
                         required></dt-text>

                <submit-button context="success"
                           type="submit"
                           class="button--large button--block">
                    <?php esc_html_e( 'Register', 'disciple-tools-autolink' ) ?>
                </submit-button>
            </form>
        </div>
    </dt-tile>
</div>

<?php include 'parts/footer.php'; ?>
