<?php include 'parts/app-header.php'; ?>
<?php include 'parts/app-greeting.php'; ?>
<?php include 'parts/church-view-tabs.php'; ?>

<div class="container">
    <dt-tile class="contacts">
        <div class="section__inner">
            <div class="contacts__heading">
                <h3><?php echo esc_html( $translations['heading'] ); ?></h3>
                <dt-button class="contacts__add"
                           context="success"
                           href="<?php echo esc_url( $add_contact_link ); ?>"
                           title="<?php echo esc_attr( $translations['add'] ); ?>"
                           aria-label="<?php echo esc_attr( $translations['add'] ); ?>"
                           round>
                    <dt-icon icon="ic:baseline-plus"></dt-icon>
                </dt-button>
            </div>
            <span class="help-text cloak"><?php echo esc_html( $translations['help_text'] ); ?></span>

            <app-contacts
                    contacts='<?php echo esc_attr( wp_json_encode( $contacts ) ); ?>'
                    translations='<?php echo esc_attr( wp_json_encode( $translations ) ); ?>'
                    relationshipIcons='<?php echo esc_attr( wp_json_encode( $relationship_icons ) ); ?>'
                    milestoneOptions='<?php echo esc_attr( wp_json_encode( $milestones['options'] ) ); ?>'
            ></app-contacts>
        </div>
    </dt-tile>
</div>

<?php include 'parts/app-footer.php'; ?>
