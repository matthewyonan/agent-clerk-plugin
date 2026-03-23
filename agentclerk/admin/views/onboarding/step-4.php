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
<div class="wrap agentclerk-onboarding">
    <h1>AgentClerk Setup — Step 4: Catalog Confirmation</h1>
    <p>Choose which products your AI agent can sell. Toggle off any products you don't want the agent to handle.</p>

    <table class="wp-list-table widefat fixed striped" id="catalog-table">
        <thead>
            <tr>
                <th>Product Name</th>
                <th>Type</th>
                <th>Price</th>
                <th>WC Status</th>
                <th>Agent Can Sell</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $products as $p ) :
                $checked = ! isset( $visibility[ $p['id'] ] ) || $visibility[ $p['id'] ];
            ?>
                <tr>
                    <td><?php echo esc_html( $p['name'] ); ?></td>
                    <td><?php echo esc_html( $p['type'] ); ?></td>
                    <td>$<?php echo esc_html( $p['price'] ); ?></td>
                    <td><?php echo esc_html( $p['status'] ); ?></td>
                    <td><input type="checkbox" class="product-toggle" data-id="<?php echo esc_attr( $p['id'] ); ?>" <?php checked( $checked ); ?> /></td>
                </tr>
            <?php endforeach; ?>
            <?php if ( empty( $products ) ) : ?>
                <tr><td colspan="5">No WooCommerce products found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="agentclerk-card" style="margin-top:20px;">
        <h3>Add a Product Manually</h3>
        <div class="agentclerk-field">
            <label>Name</label>
            <input type="text" id="new-product-name" class="regular-text" />
        </div>
        <div class="agentclerk-field">
            <label>Type</label>
            <select id="new-product-type">
                <option value="simple">Simple</option>
                <option value="variable">Variable</option>
            </select>
        </div>
        <div class="agentclerk-field">
            <label>Price</label>
            <input type="number" id="new-product-price" step="0.01" class="small-text" />
        </div>
        <div class="agentclerk-field">
            <label>Description</label>
            <textarea id="new-product-desc" rows="3" class="large-text"></textarea>
        </div>
        <button class="button" id="add-product">Add Product</button>
    </div>

    <p style="margin-top:20px;">
        <button class="button button-primary button-hero" id="step4-continue">Continue to Placement</button>
    </p>
</div>

<script>
jQuery(function($) {
    $('#add-product').on('click', function() {
        var name = $('#new-product-name').val().trim();
        if (!name) { alert('Product name is required.'); return; }

        $.post(agentclerk.ajaxUrl, {
            action: 'agentclerk_add_product',
            nonce: agentclerk.nonce,
            name: name,
            type: $('#new-product-type').val(),
            price: $('#new-product-price').val(),
            description: $('#new-product-desc').val()
        }, function(resp) {
            if (resp.success) {
                location.reload();
            } else {
                alert(resp.data.message || 'Failed to add product.');
            }
        });
    });

    $('#step4-continue').on('click', function() {
        var visibility = {};
        $('.product-toggle').each(function() {
            visibility[$(this).data('id')] = $(this).is(':checked');
        });

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
