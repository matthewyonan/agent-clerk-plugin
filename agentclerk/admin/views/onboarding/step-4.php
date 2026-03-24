<?php
if ( ! defined( 'ABSPATH' ) ) exit;
$products = [];
if ( function_exists( 'wc_get_products' ) ) {
    $wc_products = wc_get_products( [ 'status' => 'publish', 'limit' => -1 ] );
    foreach ( $wc_products as $p ) {
        $products[] = [
            'id'     => $p->get_id(),
            'name'   => $p->get_name(),
            'type'   => $p->get_type(),
            'price'  => $p->get_price(),
            'status' => $p->get_status(),
        ];
    }
}
$config     = json_decode( get_option( 'agentclerk_agent_config', '{}' ), true );
$visibility = $config['product_visibility'] ?? [];
?>
<div class="wrap ac-wrap">
    <div class="ac-steps">
        <div class="ac-step done"><div class="ac-step-num">&#10003;</div><span>Choose tier</span></div><div class="ac-step-line"></div>
        <div class="ac-step done"><div class="ac-step-num">&#10003;</div><span>Scan site</span></div><div class="ac-step-line"></div>
        <div class="ac-step done"><div class="ac-step-num">&#10003;</div><span>Review</span></div><div class="ac-step-line"></div>
        <div class="ac-step current"><div class="ac-step-num">4</div><span>Catalog</span></div><div class="ac-step-line"></div>
        <div class="ac-step"><div class="ac-step-num">5</div><span>Placement</span></div><div class="ac-step-line"></div>
        <div class="ac-step"><div class="ac-step-num">6</div><span>Go live</span></div>
    </div>

    <div class="ac-flex-between ac-mb">
        <div>
            <div class="ac-page-title">Your product catalog</div>
            <div class="ac-page-subtitle"><?php echo count( $products ); ?> products imported from WooCommerce. Control which ones your agent can sell.</div>
        </div>
        <button class="ac-btn ac-btn-ghost ac-btn-sm" id="show-add-product">+ Add product</button>
    </div>

    <div class="ac-callout ac-callout-blue ac-mb"><span class="ac-callout-icon">&#8505;</span><span>Product names, prices, and descriptions are managed in WooCommerce &mdash; changes sync to the agent automatically. Here you only control which products the agent can sell.</span></div>

    <div class="ac-card">
        <table class="ac-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Type</th>
                    <th>Price</th>
                    <th>WooCommerce</th>
                    <th>Agent can sell this</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $products as $p ) :
                    $checked = ! isset( $visibility[ $p['id'] ] ) || $visibility[ $p['id'] ];
                    $type_class = $p['type'] === 'simple' ? 'ac-badge-electric' : 'ac-badge-amber';
                ?>
                    <tr>
                        <td style="font-weight:500"><?php echo esc_html( $p['name'] ); ?></td>
                        <td><span class="ac-badge <?php echo esc_attr( $type_class ); ?>"><?php echo esc_html( ucfirst( $p['type'] ) ); ?></span></td>
                        <td class="ac-mono">$<?php echo esc_html( number_format( (float) $p['price'], 2 ) ); ?></td>
                        <td><span class="ac-badge ac-badge-green">Published</span></td>
                        <td><div class="ac-toggle <?php echo $checked ? 'on' : ''; ?>" data-id="<?php echo esc_attr( $p['id'] ); ?>"></div></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ( empty( $products ) ) : ?>
                    <tr><td colspan="5" style="color:var(--ac-text3)">No WooCommerce products found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="ac-card" id="add-product-form" style="display:none">
        <div class="ac-card-head"><h2>Add a Product Manually</h2></div>
        <div class="ac-card-body">
            <div class="ac-grid-2">
                <div class="ac-field-group"><label class="ac-label">Product Name</label><input type="text" id="new-product-name"></div>
                <div class="ac-field-group"><label class="ac-label">Price</label><input type="number" id="new-product-price" step="0.01"></div>
            </div>
            <div class="ac-field-group"><label class="ac-label">Description</label><textarea id="new-product-desc" rows="3"></textarea></div>
            <button class="ac-btn ac-btn-primary" id="add-product">Add Product</button>
        </div>
    </div>

    <div class="ac-mt">
        <button class="ac-btn ac-btn-electric ac-btn-lg" id="step4-continue">Continue to placement &rarr;</button>
    </div>
</div>

<script>
jQuery(function($) {
    // Toggle switches
    $('.ac-toggle').on('click', function() { $(this).toggleClass('on'); });

    $('#show-add-product').on('click', function() { $('#add-product-form').toggle(); });

    $('#add-product').on('click', function() {
        var name = $.trim($('#new-product-name').val());
        if (!name) { alert('Product name is required.'); return; }
        $.post(agentclerk.ajaxUrl, {
            action: 'agentclerk_add_product',
            nonce: agentclerk.nonce,
            name: name,
            type: 'simple',
            price: $('#new-product-price').val(),
            description: $('#new-product-desc').val()
        }, function(resp) {
            if (resp.success) location.reload();
            else alert(resp.data.message || 'Failed to add product.');
        });
    });

    $('#step4-continue').on('click', function() {
        var visibility = {};
        $('.ac-toggle').each(function() { visibility[$(this).data('id')] = $(this).hasClass('on'); });

        $.post(agentclerk.ajaxUrl, {
            action: 'agentclerk_save_catalog',
            nonce: agentclerk.nonce,
            visibility: JSON.stringify(visibility)
        }, function() {
            $.post(agentclerk.ajaxUrl, {
                action: 'agentclerk_save_onboarding_step',
                nonce: agentclerk.nonce,
                step: 5
            }, function() {
                window.location.href = agentclerk.ajaxUrl.replace('admin-ajax.php', 'admin.php?page=agentclerk-onboarding');
            });
        });
    });
});
</script>
