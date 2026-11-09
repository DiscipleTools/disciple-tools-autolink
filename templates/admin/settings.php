<div class="wrap">
    <h2><?php echo esc_html( $page_title ) ?></h2>
    <h2 class="nav-tab-wrapper">
		<?php foreach ( $tabs as $tab_key => $tab_label ): ?>
            <a href="<?php echo esc_attr( $link . $tab_key ) ?>"
               class="nav-tab <?php echo esc_attr( $current_tab === $tab_key ? 'nav-tab-active' : '' ); ?>">
				<?php echo esc_html( $tab_label ) ?>
            </a>
		<?php endforeach; ?>
    </h2>

	<?php $tab->content(); ?>
</div><!-- End wrap -->
