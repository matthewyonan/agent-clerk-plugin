<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AgentClerk_Scanner {

    public static function start_scan() {
        $site_url = get_site_url();
        $urls     = self::fetch_sitemap_urls( $site_url );
        $total    = count( $urls );

        set_transient( 'agentclerk_scan_progress', [
            'total'     => $total,
            'completed' => 0,
            'status'    => 'scanning',
        ], 3600 );

        $pages    = [];
        $policies = [ 'refund' => '', 'license' => '', 'delivery' => '' ];
        $gaps     = [];

        foreach ( $urls as $i => $url ) {
            $page_data = self::scan_page( $url );
            if ( $page_data ) {
                $pages[] = $page_data;
                $policies = self::extract_policies( $page_data, $policies );
            }

            set_transient( 'agentclerk_scan_progress', [
                'total'     => $total,
                'completed' => $i + 1,
                'status'    => 'scanning',
            ], 3600 );
        }

        $products = self::get_wc_products();

        if ( empty( $policies['refund'] ) ) {
            $gaps[] = 'No refund policy found';
        }
        if ( empty( $policies['license'] ) ) {
            $gaps[] = 'No license policy found';
        }
        if ( empty( $policies['delivery'] ) ) {
            $gaps[] = 'No delivery information found';
        }

        $has_faq = false;
        $has_support_email = false;
        foreach ( $pages as $page ) {
            if ( $page['type'] === 'faq' ) {
                $has_faq = true;
            }
            if ( $page['type'] === 'contact' || ! empty( $page['emails'] ) ) {
                $has_support_email = true;
            }
        }

        if ( ! $has_faq ) {
            $gaps[] = 'No FAQ page found';
        }
        if ( ! $has_support_email ) {
            $gaps[] = 'No support email found';
        }

        $scan_results = [
            'pages'      => $pages,
            'products'   => $products,
            'policies'   => $policies,
            'gaps'       => $gaps,
            'page_count' => $total,
            'timestamp'  => current_time( 'mysql' ),
        ];

        update_option( 'agentclerk_scan_cache', wp_json_encode( $scan_results ) );

        $config             = json_decode( get_option( 'agentclerk_agent_config', '{}' ), true );
        $config['policies'] = $policies;
        update_option( 'agentclerk_agent_config', wp_json_encode( $config ) );

        set_transient( 'agentclerk_scan_progress', [
            'total'     => $total,
            'completed' => $total,
            'status'    => 'complete',
        ], 3600 );

        return $scan_results;
    }

    private static function fetch_sitemap_urls( $site_url ) {
        $urls     = [];
        $sitemap  = wp_remote_get( $site_url . '/sitemap.xml', [ 'timeout' => 15 ] );

        if ( is_wp_error( $sitemap ) || wp_remote_retrieve_response_code( $sitemap ) !== 200 ) {
            $sitemap = wp_remote_get( $site_url . '/sitemap_index.xml', [ 'timeout' => 15 ] );
        }

        if ( is_wp_error( $sitemap ) || wp_remote_retrieve_response_code( $sitemap ) !== 200 ) {
            return [ $site_url ];
        }

        $body = wp_remote_retrieve_body( $sitemap );
        $xml  = @simplexml_load_string( $body );

        if ( ! $xml ) {
            return [ $site_url ];
        }

        if ( isset( $xml->sitemap ) ) {
            foreach ( $xml->sitemap as $sub ) {
                $sub_urls = self::parse_sitemap( (string) $sub->loc );
                $urls     = array_merge( $urls, $sub_urls );
            }
        } elseif ( isset( $xml->url ) ) {
            foreach ( $xml->url as $entry ) {
                $urls[] = (string) $entry->loc;
            }
        }

        $filtered = [];
        $exclude  = [ '.jpg', '.jpeg', '.png', '.gif', '.svg', '.webp', '/wp-admin/', '/wp-json/', '/feed/' ];
        foreach ( $urls as $url ) {
            $dominated = false;
            foreach ( $exclude as $ex ) {
                if ( stripos( $url, $ex ) !== false ) {
                    $dominated = true;
                    break;
                }
            }
            if ( ! $dominated ) {
                $filtered[] = $url;
            }
        }

        return $filtered;
    }

    private static function parse_sitemap( $url ) {
        $urls    = [];
        $request = wp_remote_get( $url, [ 'timeout' => 15 ] );

        if ( is_wp_error( $request ) || wp_remote_retrieve_response_code( $request ) !== 200 ) {
            return $urls;
        }

        $xml = @simplexml_load_string( wp_remote_retrieve_body( $request ) );
        if ( $xml && isset( $xml->url ) ) {
            foreach ( $xml->url as $entry ) {
                $urls[] = (string) $entry->loc;
            }
        }

        return $urls;
    }

    private static function scan_page( $url ) {
        $response = wp_remote_get( $url, [ 'timeout' => 15 ] );

        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            return null;
        }

        $html = wp_remote_retrieve_body( $response );
        $text = self::strip_to_text( $html );
        $type = self::classify_page( $url, $text );

        $emails = [];
        if ( preg_match_all( '/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/', $text, $matches ) ) {
            $emails = array_unique( $matches[0] );
        }

        return [
            'url'    => $url,
            'type'   => $type,
            'text'   => mb_substr( $text, 0, 5000 ),
            'emails' => array_values( $emails ),
        ];
    }

    private static function strip_to_text( $html ) {
        $html = preg_replace( '/<script[^>]*>.*?<\/script>/si', '', $html );
        $html = preg_replace( '/<style[^>]*>.*?<\/style>/si', '', $html );
        $html = preg_replace( '/<nav[^>]*>.*?<\/nav>/si', '', $html );
        $html = preg_replace( '/<footer[^>]*>.*?<\/footer>/si', '', $html );
        $text = wp_strip_all_tags( $html );
        $text = preg_replace( '/\s+/', ' ', $text );
        return trim( $text );
    }

    private static function classify_page( $url, $text ) {
        $lower_url  = strtolower( $url );
        $lower_text = strtolower( $text );

        if ( preg_match( '/faq|frequently.asked/i', $lower_url . ' ' . $lower_text ) ) {
            return 'faq';
        }
        if ( preg_match( '/refund|return.policy/i', $lower_url . ' ' . $lower_text ) ) {
            return 'refund_policy';
        }
        if ( preg_match( '/privacy.policy/i', $lower_url . ' ' . $lower_text ) ) {
            return 'privacy_policy';
        }
        if ( preg_match( '/terms|tos|terms.of.service/i', $lower_url . ' ' . $lower_text ) ) {
            return 'terms';
        }
        if ( preg_match( '/contact|support|help/i', $lower_url . ' ' . $lower_text ) ) {
            return 'contact';
        }
        if ( preg_match( '/about/i', $lower_url ) ) {
            return 'about';
        }
        if ( preg_match( '/shop|store|product/i', $lower_url ) ) {
            return 'shop';
        }

        return 'general';
    }

    private static function extract_policies( $page, $policies ) {
        if ( $page['type'] === 'refund_policy' && empty( $policies['refund'] ) ) {
            $policies['refund'] = mb_substr( $page['text'], 0, 2000 );
        }
        if ( $page['type'] === 'terms' && empty( $policies['license'] ) ) {
            $policies['license'] = mb_substr( $page['text'], 0, 2000 );
        }
        if ( stripos( $page['text'], 'delivery' ) !== false || stripos( $page['text'], 'shipping' ) !== false ) {
            if ( empty( $policies['delivery'] ) ) {
                $policies['delivery'] = mb_substr( $page['text'], 0, 2000 );
            }
        }

        return $policies;
    }

    private static function get_wc_products() {
        if ( ! function_exists( 'wc_get_products' ) ) {
            return [];
        }

        $products = wc_get_products( [ 'status' => 'publish', 'limit' => -1 ] );
        $result   = [];

        foreach ( $products as $product ) {
            $result[] = [
                'id'          => $product->get_id(),
                'name'        => $product->get_name(),
                'price'       => $product->get_price(),
                'type'        => $product->get_type(),
                'description' => $product->get_short_description(),
                'status'      => $product->get_status(),
            ];
        }

        return $result;
    }

    public static function build_support_file( $scan_results ) {
        $text = "# Support Knowledge Base\n\n";

        if ( ! empty( $scan_results['products'] ) ) {
            $text .= "## Products\n";
            foreach ( $scan_results['products'] as $p ) {
                $text .= "- {$p['name']}: \${$p['price']} ({$p['type']})\n";
                if ( ! empty( $p['description'] ) ) {
                    $text .= "  {$p['description']}\n";
                }
            }
            $text .= "\n";
        }

        foreach ( $scan_results['pages'] as $page ) {
            if ( in_array( $page['type'], [ 'faq', 'contact' ], true ) ) {
                $text .= "## " . ucfirst( $page['type'] ) . "\n";
                $text .= mb_substr( $page['text'], 0, 1000 ) . "\n\n";
            }
        }

        if ( ! empty( $scan_results['policies']['refund'] ) ) {
            $text .= "## Refund Policy\n" . mb_substr( $scan_results['policies']['refund'], 0, 1000 ) . "\n\n";
        }

        return $text;
    }
}
