<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$products = array();
if ( function_exists( 'wc_get_products' ) ) {
    $wc_products = wc_get_products( array( 'status' => 'publish', 'limit' => -1 ) );
    foreach ( $wc_products as $p ) {
        $products[] = array(
            'id'     => $p->get_id(),
            'name'   => $p->get_name(),
            'type'   => $p->get_type(),
            'price'  => $p->get_price(),
            'status' => $p->get_status(),
        );
    }
}
$config     = json_decode( get_option( 'agentclerk_agent_config', '{}' ), true );
$visibility = $config['product_visibility'] ?? array();
?>
<div class="wrap ac-wrap">
    <div class="ac-steps">
        <div class="ac-step done"><div class="ac-step-n">&#10003;</div><span><?php echo esc_html( 'Choose tier' ); ?></span></div><div class="ac-step-line"></div>
        <div class="ac-step done"><div class="ac-step-n">&#10003;</div><span><?php echo esc_html( 'Scan site' ); ?></span></div><div class="ac-step-line"></div>
        <div class="ac-step done"><div class="ac-step-n">&#10003;</div><span><?php echo esc_html( 'Review' ); ?></span></div><div class="ac-step-line"></div>
        <div class="ac-step cur"><div class="ac-step-n">4</div><span><?php echo esc_html( 'Catalog' ); ?></span></div><div class="ac-step-line"></div>
        <div class="ac-step"><div class="ac-step-n">5</div><span><?php echo esc_html( 'Go live' ); ?></span></div>
    </div>

    <div class="ac-fb ac-mb">
        <div>
            <div class="ac-pt"><?php echo esc_html( 'Your product catalog' ); ?></div>
            <div class="ac-ps"><?php printf( esc_html( '%d products imported from WooCommerce. Control which ones your agent can sell.' ), count( $products ) ); ?></div>
        </div>
        <button class="ac-btn ac-btn-g ac-btn-sm" id="ac-show-add-product">+ <?php echo esc_html( 'Add product' ); ?></button>
    </div>

    <div class="ac-co bl ac-mb"><span class="ac-co-i">&#8505;</span><span><?php echo esc_html( 'Product names, prices, and descriptions are managed in WooCommerce — changes sync to the agent automatically. Here you only control which products the agent can sell.' ); ?></span></div>

    <div class="ac-card">
        <table class="ac-dt">
            <thead><tr><th><?php echo esc_html( 'Product' ); ?></th><th><?php echo esc_html( 'Type' ); ?></th><th><?php echo esc_html( 'Price' ); ?></th><th><?php echo esc_html( 'WooCommerce' ); ?></th><th><?php echo esc_html( 'Agent can sell this' ); ?></th></tr></thead>
            <tbody>
                <?php foreach ( $products as $p ) :
                    $checked    = ! isset( $visibility[ $p['id'] ] ) || $visibility[ $p['id'] ];
                    $type_badge = ( $p['type'] === 'simple' ) ? 'ac-b-e' : 'ac-b-a';
                ?>
                    <tr>
                        <td style="font-weight:500"><?php echo esc_html( $p['name'] ); ?></td>
                        <td><span class="ac-b <?php echo esc_attr( $type_badge ); ?>"><?php echo esc_html( ucfirst( $p['type'] ) ); ?></span></td>
                        <td style="font-family:'DM Mono',monospace;font-size:12px">$<?php echo esc_html( number_format( (float) $p['price'], 2 ) ); ?></td>
                        <td><span class="ac-b ac-b-g"><?php echo esc_html( 'Published' ); ?></span></td>
                        <td><div class="ac-tog <?php echo $checked ? 'on' : ''; ?>" data-id="<?php echo esc_attr( $p['id'] ); ?>"></div></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ( empty( $products ) ) : ?>
                    <tr><td colspan="5" style="color:var(--text3)"><?php echo esc_html( 'No WooCommerce products found.' ); ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Add product modal -->
    <div class="ac-modal-ov" id="ac-add-product-modal">
        <div class="ac-modal-box">
            <div class="ac-modal-hd"><h3><?php echo esc_html( 'Add a Product' ); ?></h3><span class="ac-modal-x" id="ac-close-add-product">&times;</span></div>
            <div class="ac-modal-body">
                <div class="ac-fg"><label class="ac-fl"><?php echo esc_html( 'Product name' ); ?></label><input type="text" id="ac-new-product-name"></div>
                <div class="ac-g2">
                    <div class="ac-fg"><label class="ac-fl"><?php echo esc_html( 'Type' ); ?></label><select id="ac-new-product-type"><option value="simple"><?php echo esc_html( 'Simple' ); ?></option><option value="variable"><?php echo esc_html( 'Variable' ); ?></option></select></div>
                    <div class="ac-fg"><label class="ac-fl"><?php echo esc_html( 'Price' ); ?></label><input type="text" id="ac-new-product-price"></div>
                </div>
                <div class="ac-fg"><label class="ac-fl"><?php echo esc_html( 'Description' ); ?></label><textarea id="ac-new-product-desc" rows="3"></textarea></div>
                <button class="ac-btn ac-btn-p" id="ac-add-product" style="width:100%;justify-content:center"><?php echo esc_html( 'Add Product' ); ?></button>
            </div>
        </div>
    </div>

    <button class="ac-btn ac-btn-e ac-btn-lg" id="ac-step4-continue"><?php echo esc_html( 'Continue to placement' ); ?> &rarr;</button>
    <div style="text-align:right;padding:20px 0 4px;font-size:11px;color:var(--ac-text3)">&copy; 2026 &mdash; A Brilliant Way</div>
</div>
