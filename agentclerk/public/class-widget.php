<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AgentClerk_Widget {

    public function __construct() {
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'woocommerce_after_add_to_cart_button', [ $this, 'product_page_embed' ] );
        add_filter( 'the_content', [ $this, 'clerk_page_content' ] );
    }

    public function enqueue_assets() {
        if ( get_option( 'agentclerk_plugin_status' ) !== 'active' ) {
            return;
        }

        $placement = json_decode( get_option( 'agentclerk_placement', '{}' ), true );

        $should_enqueue = false;
        if ( ! empty( $placement['widget'] ) ) {
            $should_enqueue = true;
        }
        if ( ! empty( $placement['product_page'] ) && function_exists( 'is_product' ) && is_product() ) {
            $should_enqueue = true;
        }

        $clerk_page_id = get_option( 'agentclerk_clerk_page_id' );
        if ( ! empty( $placement['clerk_page'] ) && $clerk_page_id && is_page( (int) $clerk_page_id ) ) {
            $should_enqueue = true;
        }

        if ( ! $should_enqueue ) {
            return;
        }

        wp_enqueue_style(
            'agentclerk-widget',
            AGENTCLERK_PLUGIN_URL . 'public/css/agentclerk-widget.css',
            [],
            AGENTCLERK_VERSION
        );

        wp_enqueue_script(
            'agentclerk-widget',
            AGENTCLERK_PLUGIN_URL . 'public/js/agentclerk-widget.js',
            [],
            AGENTCLERK_VERSION,
            true
        );

        $config = json_decode( get_option( 'agentclerk_agent_config', '{}' ), true );

        wp_localize_script( 'agentclerk-widget', 'agentclerkWidget', [
            'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
            'nonce'        => wp_create_nonce( 'agentclerk_nonce' ),
            'placement'    => $placement,
            'agentName'    => $config['agent_name'] ?? 'AgentClerk',
            'buttonLabel'  => $placement['button_label'] ?? 'Get Help',
            'position'     => $placement['position'] ?? 'bottom-right',
            'showWidget'   => ! empty( $placement['widget'] ),
            'clerkPageId'  => $clerk_page_id ?: 0,
            'isClerkPage'  => $clerk_page_id && is_page( (int) $clerk_page_id ),
            'escalationMessage' => $config['escalation_message'] ?? '',
        ] );
    }

    public function product_page_embed() {
        if ( get_option( 'agentclerk_plugin_status' ) !== 'active' ) {
            return;
        }

        $placement = json_decode( get_option( 'agentclerk_placement', '{}' ), true );
        if ( empty( $placement['product_page'] ) ) {
            return;
        }

        echo '<div id="agentclerk-product-embed" class="agentclerk-embed">';
        echo '<div class="agentclerk-embed-header">';
        echo '<span>' . esc_html( $placement['button_label'] ?? 'Get Help' ) . '</span>';
        echo '</div>';
        echo '<div class="agentclerk-embed-messages" id="agentclerk-product-messages"></div>';
        echo '<div class="agentclerk-embed-input">';
        echo '<input type="text" id="agentclerk-product-input" placeholder="Ask about this product..." />';
        echo '<button id="agentclerk-product-send">Send</button>';
        echo '</div>';
        echo '</div>';
    }

    public function clerk_page_content( $content ) {
        $clerk_page_id = get_option( 'agentclerk_clerk_page_id' );
        if ( ! $clerk_page_id || ! is_page( (int) $clerk_page_id ) ) {
            return $content;
        }

        if ( get_option( 'agentclerk_plugin_status' ) !== 'active' ) {
            return $content;
        }

        $config = json_decode( get_option( 'agentclerk_agent_config', '{}' ), true );

        $html  = '<div id="agentclerk-fullpage" class="agentclerk-fullpage">';
        $html .= '<div class="agentclerk-fullpage-header">';
        $html .= '<h2>' . esc_html( $config['agent_name'] ?? 'AgentClerk' ) . '</h2>';
        $html .= '</div>';
        $html .= '<div class="agentclerk-fullpage-messages" id="agentclerk-fullpage-messages"></div>';
        $html .= '<div class="agentclerk-fullpage-input">';
        $html .= '<input type="text" id="agentclerk-fullpage-input" placeholder="Type your message..." />';
        $html .= '<button id="agentclerk-fullpage-send">Send</button>';
        $html .= '</div>';
        $html .= '<div id="agentclerk-escalation" style="display:none;">';
        $html .= '<p>Would you like to speak with a human?</p>';
        $html .= '<input type="email" id="agentclerk-escalation-email" placeholder="your@email.com" />';
        $html .= '<button id="agentclerk-escalation-confirm">Confirm</button>';
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }
}
