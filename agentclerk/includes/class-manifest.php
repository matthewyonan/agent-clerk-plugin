<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AgentClerk_Manifest {

    public function __construct() {
        add_action( 'template_redirect', [ $this, 'handle_manifest_request' ] );
        add_action( 'save_post_product', [ $this, 'bust_cache' ] );
    }

    public function handle_manifest_request() {
        if ( ! get_query_var( 'agentclerk_manifest' ) ) {
            return;
        }

        $cached = get_transient( 'agentclerk_manifest_cache' );
        if ( $cached ) {
            header( 'Content-Type: application/json' );
            echo $cached;
            exit;
        }

        $config   = json_decode( get_option( 'agentclerk_agent_config', '{}' ), true );
        $products = $this->get_visible_products( $config );

        $manifest = [
            'manifest_version' => '1.0',
            'business'         => [
                'name'        => $config['business_name'] ?? get_bloginfo( 'name' ),
                'description' => $config['business_desc'] ?? get_bloginfo( 'description' ),
            ],
            'agent_endpoint' => get_site_url() . '/agentclerk/chat',
            'products'       => $products,
            'policies'       => $config['policies'] ?? [],
            'fulfillment_types' => $this->detect_fulfillment_types( $products ),
            'currency'       => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD',
        ];

        $json = wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
        set_transient( 'agentclerk_manifest_cache', $json, 900 );

        header( 'Content-Type: application/json' );
        echo $json;
        exit;
    }

    private function get_visible_products( $config ) {
        if ( ! function_exists( 'wc_get_products' ) ) {
            return [];
        }

        $products   = wc_get_products( [ 'status' => 'publish', 'limit' => -1 ] );
        $visibility = $config['product_visibility'] ?? [];
        $result     = [];

        foreach ( $products as $product ) {
            $pid = $product->get_id();
            if ( isset( $visibility[ $pid ] ) && ! $visibility[ $pid ] ) {
                continue;
            }
            $result[] = [
                'id'          => $pid,
                'name'        => $product->get_name(),
                'price'       => (float) $product->get_price(),
                'type'        => $product->is_downloadable() ? 'digital' : 'physical',
                'description' => $product->get_short_description(),
                'available'   => $product->is_in_stock(),
            ];
        }

        return $result;
    }

    private function detect_fulfillment_types( $products ) {
        $types = [];
        foreach ( $products as $p ) {
            if ( $p['type'] === 'digital' && ! in_array( 'digital_download', $types, true ) ) {
                $types[] = 'digital_download';
            }
            if ( $p['type'] === 'physical' && ! in_array( 'physical_shipping', $types, true ) ) {
                $types[] = 'physical_shipping';
            }
        }
        return $types ?: [ 'digital_download' ];
    }

    public function bust_cache() {
        delete_transient( 'agentclerk_manifest_cache' );
    }
}
