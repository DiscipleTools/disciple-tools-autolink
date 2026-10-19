<div class="container">
    <dt-button context="<?php echo empty( $action ) ? 'primary' : 'inactive'; ?>"
               href="<?php echo esc_url( $app_url ); ?>">
        <?php echo esc_html( $church_health_label ); ?>
    </dt-button>
    <dt-button context="<?php echo $action === 'tree' ? 'primary' : 'inactive'; ?>"
               href="<?php echo esc_url( $app_url . "?" . http_build_query( [ 'action' => 'tree' ] ) ); ?>">
        <?php echo esc_html( $tree_label ); ?>
    </dt-button>
    <dt-button context="<?php echo $action === 'contacts' ? 'primary' : 'inactive'; ?>"
               href="<?php echo esc_url( $app_url . "?" . http_build_query( [ 'action' => 'contacts' ] ) ); ?>">
        <?php echo esc_html( $contacts_label ); ?>
    </dt-button>
    <?php if ( function_exists( 'dt_genmapper_metrics' ) ): ?>
        <dt-button context="<?php echo $action === 'genmap' ? 'primary' : 'inactive'; ?>"
                   href="<?php echo esc_url( $app_url . "?" . http_build_query( [ 'action' => 'genmap' ] ) ); ?>">
            <?php echo esc_html( $genmap_label ); ?>
        </dt-button>
    <?php endif; ?>
</div>
