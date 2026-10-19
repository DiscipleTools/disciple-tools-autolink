<?php include 'parts/header.php'; ?>
<?php include 'parts/navbar.php'; ?>
<?php
/*
 * The chevrons point the way the reader is travelling, not at a fixed side of
 * the screen, so they swap over in a right-to-left language.
 */
$back_icon    = is_rtl() ? 'mdi:chevron-right' : 'mdi:chevron-left';
$forward_icon = is_rtl() ? 'mdi:chevron-left' : 'mdi:chevron-right';
?>

<form class="survey container"
      action="<?php echo esc_url( $action ); ?>"
      method="POST">

    <?php wp_nonce_field( 'dt_autolink_survey' ); ?>

    <dt-tile title="<?php echo esc_attr( $question['label'] ); ?>"
             class="question">
        <div class="section__inner">
            <div class="question__progress-bar"
                 style="
                     --progress: <?php echo esc_attr( $progress ); ?>;"></div>

            <dt-text class="question__input"
                     type="number"
                     value="<?php echo esc_attr( $answer ); ?>"
                     name="<?php echo esc_attr( $question['name'] ) ?>"></dt-text>

            <div class="question__pagination">
                <?php if ( $previous_url ) : ?>
                    <dt-button type="submit"
                               class="pagination__previous"
                               href="<?php echo esc_url( $previous_url ) ?>"
                               round>
                        <dt-icon icon="<?php echo esc_attr( $back_icon ); ?>"></dt-icon>
                    </dt-button>
                <?php endif; ?>
                <submit-button class="pagination__next"
                                type="submit"
                                round>
                    <dt-icon icon="<?php echo esc_attr( $forward_icon ); ?>"></dt-icon>
                </submit-button>
            </div>
        </div>
    </dt-tile>
</form>


<?php include 'parts/footer.php'; ?>
