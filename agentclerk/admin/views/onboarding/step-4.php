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
        <div class="ac-step done"><div class="ac-step-num">&#10003;</div><span><?php echo esc_html( 'Choose tier' ); ?></span></div><div class="ac-step-line"></div>
        <div class="ac-step done"><div class="ac-step-num">&#10003;</div><span><?php echo esc_html( 'Scan site' ); ?></span></div><div class="ac-step-line"></div>
        <div class="ac-step done"><div class="ac-step-num">&#10003;</div><span><?php echo esc_html( 'Review' ); ?></span></div><div class="ac-step-line"></div>
        <div class="ac-step current"><div class="ac-step-num">4</div><span><?php echo esc_html( 'Catalog' ); ?></span></div><div class="ac-step-line"></div>
        <div class="ac-step"><div class="ac-step-num">5</div><span><?php echo esc_html( 'Go live' ); ?></span></div>
    </div>

    <div class="ac-flex-between ac-mb">
        <div>
            <h1 class="ac-page-title"><?php echo esc_html( 'Your product catalog' ); ?></h1>
            <p class="ac-page-subtitle"><?php printf( esc_html( '%d products imported from WooCommerce. Control which ones your agent can sell.' ), count( $products ) ); ?></p>
        </div>
        <button class="ac-btn ac-btn-ghost ac-btn-sm" id="ac-show-add-product">+ <?php echo esc_html( 'Add product' ); ?></button>
    </div>

    <div class="ac-callout ac-callout-blue ac-mb"><span>&#8505;</span><span><?php echo esc_html( 'Product names, prices, and descriptions are managed in WooCommerce — changes sync to the agent automatically. Here you only control which products the agent can sell.' ); ?></span></div>

    <div class="ac-card">
        <table class="ac-table">
            <thead><tr><th><?php echo esc_html( 'Product' ); ?></th><th><?php echo esc_html( 'Type' ); ?></th><th><?php echo esc_html( 'Price' ); ?></th><th><?php echo esc_html( 'WooCommerce' ); ?></th><th><?php echo esc_html( 'Agent can sell this' ); ?></th></tr></thead>
            <tbody>
                <?php foreach ( $products as $p ) :
                    $checked    = ! isset( $visibility[ $p['id'] ] ) || $visibility[ $p['id'] ];
                    $type_class = ( $p['type'] === 'simple' ) ? 'ac-badge-electric' : 'ac-badge-amber';
                ?>
                    <tr>
                        <td style="font-weight:500"><?php echo esc_html( $p['name'] ); ?></td>
                        <td><span class="ac-badge <?php echo esc_attr( $type_class ); ?>"><?php echo esc_html( ucfirst( $p['type'] ) ); ?></span></td>
                        <td style="font-family:'DM Mono',monospace">$<?php echo esc_html( number_format( (float) $p['price'], 2 ) ); ?></td>
                        <td><span class="ac-badge ac-badge-green"><?php echo esc_html( 'Published' ); ?></span></td>
                        <td><div class="ac-toggle <?php echo $checked ? 'on' : ''; ?>" data-id="<?php echo esc_attr( $p['id'] ); ?>"></div></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ( empty( $products ) ) : ?>
                    <tr><td colspan="5" style="color:var(--ac-text3)"><?php echo esc_html( 'No WooCommerce products found.' ); ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="ac-modal-overlay" id="ac-add-product-modal">
        <div class="ac-modal">
            <div class="ac-modal-header"><h3><?php echo esc_html( 'Add a Product' ); ?></h3><button class="ac-modal-close" id="ac-close-add-product">&times;</button></div>
            <div class="ac-modal-body">
                <div class="ac-field-group"><label class="ac-label"><?php echo esc_html( 'Product name' ); ?></label><input type="text" id="ac-new-product-name"></div>
                <div class="ac-grid-2">
                    <div class="ac-field-group"><label class="ac-label"><?php echo esc_html( 'Type' ); ?></label><select id="ac-new-product-type"><option value="simple"><?php echo esc_html( 'Simple' ); ?></option><option value="variable"><?php echo esc_html( 'Variable' ); ?></option></select></div>
                    <div class="ac-field-group"><label class="ac-label"><?php echo esc_html( 'Price' ); ?></label><input type="number" id="ac-new-product-price" step="0.01"></div>
                </div>
                <div class="ac-field-group"><label class="ac-label"><?php echo esc_html( 'Description' ); ?></label><textarea id="ac-new-product-desc" rows="3"></textarea></div>
                <button class="ac-btn ac-btn-primary" id="ac-add-product" style="width:100%;justify-content:center"><?php echo esc_html( 'Add Product' ); ?></button>
            </div>
        </div>
    </div>

    <div class="ac-mt"><button class="ac-btn ac-btn-electric" id="ac-step4-continue"><?php echo esc_html( 'Continue to placement' ); ?> &rarr;</button></div>
</div>

<script>
jQuery(function($) {
    $('.ac-toggle').on('click', function() { $(this).toggleClass('on'); });
    $('#ac-show-add-product').on('click', function() { $('#ac-add-product-modal').addClass('active'); });
    $('#ac-close-add-product').on('click', function() { $('#ac-add-product-modal').removeClass('active'); });
    $('#ac-add-product-modal').on('click', function(e) { if (e.target===this) $(this).removeClass('active'); });
    $('#ac-add-product').on('click', function() {
        var name = $.trim($('#ac-new-product-name').val());
        if (!name) { alert('Product name is required.'); return; }
        $.post(agentclerk.ajaxUrl, {action:'agentclerk_add_product',nonce:agentclerk.nonce,name:name,type:$('#ac-new-product-type').val(),price:$('#ac-new-product-price').val(),description:$('#ac-new-product-desc').val()}, function(r) {
            if (r.success) location.reload(); else alert(r.data?r.data.message:'Failed to add product.');
        });
    });
    $('#ac-step4-continue').on('click', function() {
        var vis = {};
        $('.ac-toggle').each(function() { vis[$(this).data('id')]=$(this).hasClass('on'); });
        $.post(agentclerk.ajaxUrl, {action:'agentclerk_save_catalog',nonce:agentclerk.nonce,visibility:JSON.stringify(vis)}, function() {
            $.post(agentclerk.ajaxUrl, {action:'agentclerk_save_onboarding_step',nonce:agentclerk.nonce,step:5}, function() {
                window.location.href=agentclerk.ajaxUrl.replace('admin-ajax.php','admin.php?page=agentclerk-onboarding');
            });
        });
    });
});
</script>
