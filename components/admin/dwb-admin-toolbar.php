<div class="dwb-admin-toolbar">
    <h2>
        <img src="<?php echo $logo_url; // phpcs:ignore ?>" width="20" height="20">
        <?php echo esc_html__( 'Direwolf Blocks', 'dwb' ); ?>
    </h2>
    <?php
    foreach ( $tabs as $tab ) {
        printf(
            '<a class="dwb-admin-toolbar-tab%s" href="%s">%s</a>',
            ! empty( $tab['is_active'] ) ? ' is-active' : '',
            esc_url( $tab['url'] ),
            // phpcs:ignore
            $tab['text']
        );
    }
    ?>
</div>
