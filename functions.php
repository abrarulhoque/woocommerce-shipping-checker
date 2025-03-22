<?php
/**
 * Functions for WooCommerce Shipping Availability Checker
 */

// Don't allow direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enqueue scripts and styles
 */
function shipping_checker_enqueue_scripts() {
    // Register and enqueue the JavaScript
    wp_register_script('wc-shipping-checker', '', array('jquery'), '1.0.0', true);
    wp_localize_script('wc-shipping-checker', 'wc_shipping_checker', array(
        'ajax_url' => admin_url('admin-ajax.php')
    ));
    wp_enqueue_script('wc-shipping-checker');
    
    // Register and enqueue the CSS
    wp_register_style('wc-shipping-checker-style', WC_SHIPPING_CHECKER_URL . 'shipping-checker.css', array(), '1.0.0');
    wp_enqueue_style('wc-shipping-checker-style');
}
add_action('wp_enqueue_scripts', 'shipping_checker_enqueue_scripts');

/**
 * Handle AJAX request for ZIP code validation
 */
function validate_shipping_zip_code() {
    // Security check
    check_ajax_referer('validate_zip_code_nonce', 'security');

    // Get and sanitize inputs
    $zip_code = isset($_POST['zip_code']) ? sanitize_text_field($_POST['zip_code']) : '';
    $country = isset($_POST['country']) ? sanitize_text_field($_POST['country']) : 'US';
    $state = isset($_POST['state']) ? sanitize_text_field($_POST['state']) : '';
    
    if (empty($zip_code)) {
        wp_send_json_error('Please enter a ZIP code.');
    }
    
    // Check if WooCommerce is active
    if (!class_exists('WC_Shipping_Zones') || !class_exists('WC_Shipping')) {
        wp_send_json_error('WooCommerce is not active or properly configured.');
    }
    
    // Create a mock shipping package with minimal required info
    $package = array(
        'destination' => array(
            'postcode' => $zip_code,
            'country'  => $country,
            'state'    => $state,
        ),
        'contents' => array(), // Empty contents for basic zone matching
    );
    
    // Find matching shipping zone
    $matching_zone = WC_Shipping_Zones::get_zone_matching_package($package);
    
    // Check if we found a valid zone (ID will be 0 if no specific zone matches)
    if (!$matching_zone || $matching_zone->get_id() === 0) {
        wp_send_json_error('We do not ship to your location.');
        wp_die();
    }
    
    // Check if zone has active shipping methods
    $shipping_methods = $matching_zone->get_shipping_methods(true); // Only enabled methods
    
    if (empty($shipping_methods)) {
        wp_send_json_error('No shipping methods are configured for your location.');
        wp_die();
    }
    
    // For better accuracy, try the alternative method to check actual shipping availability
    // This is similar to what happens at checkout
    $wc_shipping = WC_Shipping::instance();
    $package_with_contents = array(
        'destination' => array(
            'postcode' => $zip_code,
            'country'  => $country,
            'state'    => $state,
        ),
        'contents' => array(
            // Add a dummy product to ensure shipping calculation runs
            array(
                'data' => new WC_Product_Simple(),
                'quantity' => 1
            )
        ),
    );
    
    $shipping_for_package = $wc_shipping->calculate_shipping_for_package($package_with_contents);
    
    if (empty($shipping_for_package) || empty($shipping_for_package['rates'])) {
        wp_send_json_error('No shipping methods are available for your location.');
        wp_die();
    }
    
    // Get available methods to display from the calculated rates
    $available_methods = array();
    foreach ($shipping_for_package['rates'] as $method_id => $method) {
        $available_methods[] = array(
            'id'    => $method_id,
            'label' => $method->label,
            'cost'  => $method->cost ? wc_price($method->cost) : ''
        );
    }
    
    // Return results
    if (!empty($available_methods)) {
        wp_send_json_success(array('methods' => $available_methods));
    } else {
        wp_send_json_error('No shipping methods are available for your location.');
    }
    
    wp_die();
}

// Register AJAX handlers for both logged-in and non-logged-in users
add_action('wp_ajax_validate_shipping_zip_code', 'validate_shipping_zip_code');
add_action('wp_ajax_nopriv_validate_shipping_zip_code', 'validate_shipping_zip_code');

/**
 * Alternative approach using WC_Shipping for more accurate method availability
 */
function validate_shipping_zip_code_with_wc_shipping() {
    // Security check
    check_ajax_referer('validate_zip_code_nonce', 'security');

    // Get and sanitize inputs
    $zip_code = isset($_POST['zip_code']) ? sanitize_text_field($_POST['zip_code']) : '';
    $country = isset($_POST['country']) ? sanitize_text_field($_POST['country']) : 'US';
    
    if (empty($zip_code)) {
        wp_send_json_error('Please enter a ZIP code.');
    }
    
    // Check if WooCommerce is active
    if (!class_exists('WC_Shipping')) {
        wp_send_json_error('WooCommerce is not active or properly configured.');
    }
    
    // Create a mock shipping package
    $package = array(
        'destination' => array(
            'postcode' => $zip_code,
            'country'  => $country,
            'state'    => '', // Leave empty to let WooCommerce determine
        ),
        'contents' => array(
            // Add a dummy product to ensure shipping calculation runs
            array(
                'data' => new WC_Product_Simple(),
                'quantity' => 1
            )
        ),
    );
    
    // Initialize WC_Shipping
    $shipping = WC_Shipping::instance();
    
    // Calculate shipping for the package
    $shipping_methods = $shipping->calculate_shipping_for_package($package);
    
    if (!empty($shipping_methods) && isset($shipping_methods['rates']) && !empty($shipping_methods['rates'])) {
        $available_methods = array();
        
        foreach ($shipping_methods['rates'] as $method_id => $method) {
            $available_methods[] = array(
                'id'    => $method_id,
                'label' => $method->label,
                'cost'  => $method->cost ? wc_price($method->cost) : ''
            );
        }
        
        wp_send_json_success(array('methods' => $available_methods));
    } else {
        wp_send_json_error('We do not ship to your location.');
    }
    
    wp_die();
}

// Uncomment to use the alternative approach
// add_action('wp_ajax_validate_shipping_zip_code', 'validate_shipping_zip_code_with_wc_shipping');
// add_action('wp_ajax_nopriv_validate_shipping_zip_code', 'validate_shipping_zip_code_with_wc_shipping');

/**
 * Handle AJAX request for getting states for a country
 */
function get_states_for_country() {
    // Security check
    check_ajax_referer('validate_zip_code_nonce', 'security');

    // Get and sanitize input
    $country = isset($_POST['country']) ? sanitize_text_field($_POST['country']) : '';
    
    if (empty($country)) {
        wp_send_json_error('Please provide a country code.');
    }
    
    // Check if WooCommerce is active
    if (!function_exists('WC')) {
        wp_send_json_error('WooCommerce is not active or properly configured.');
    }
    
    // Get states for the country
    $states = WC()->countries->get_states($country);
    
    // Return results
    wp_send_json_success(array('states' => $states));
    
    wp_die();
}

// Register AJAX handlers for both logged-in and non-logged-in users
add_action('wp_ajax_get_states_for_country', 'get_states_for_country');
add_action('wp_ajax_nopriv_get_states_for_country', 'get_states_for_country');

/**
 * Handle AJAX request for retrieving state from ZIP code using API
 */
function get_state_from_zip() {
    // Security check
    check_ajax_referer('validate_zip_code_nonce', 'security');

    // Get and sanitize input
    $zip_code = isset($_POST['zip_code']) ? sanitize_text_field($_POST['zip_code']) : '';
    
    if (empty($zip_code)) {
        wp_send_json_error('Please provide a ZIP code.');
    }
    
    // Fetch state from API
    $api_url = 'https://api.sipcode.dev/zip/' . $zip_code;
    $response = wp_remote_get($api_url);
    
    if (is_wp_error($response)) {
        wp_send_json_error('Error connecting to ZIP code service.');
    }
    
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body);
    
    // Check if data is valid and contains state
    if (!$data || empty($data) || !isset($data[0]->state_id)) {
        wp_send_json_error('Invalid ZIP code or state information not found.');
    }
    
    // Return the state info
    wp_send_json_success(array(
        'state_id' => $data[0]->state_id,
        'state_name' => $data[0]->state_name,
        'city' => $data[0]->city
    ));
    
    wp_die();
}

// Register AJAX handlers for both logged-in and non-logged-in users
add_action('wp_ajax_get_state_from_zip', 'get_state_from_zip');
add_action('wp_ajax_nopriv_get_state_from_zip', 'get_state_from_zip');

/**
 * Register the shipping checker shortcode
 */
function wc_shipping_checker_shortcode($atts) {
    // Ensure styles and scripts are loaded
    shipping_checker_enqueue_scripts();
    
    // Process shortcode attributes
    $atts = shortcode_atts(array(
        'title' => 'Do We Ship To You?',
        'description' => 'Enter your ZIP code below to check if we can ship to your location.',
        'button_text' => 'CHECK AVAILABILITY'
    ), $atts, 'shipping_checker');
    
    // Start output buffering
    ob_start();
    ?>
    <div class="shipping-checker-container">
        <h2><?php echo esc_html($atts['title']); ?></h2>
        <p><?php echo esc_html($atts['description']); ?></p>
        
        <div class="zip-code-checker-form">
            <div class="form-row">
                <div class="form-group zip-input-group">
                    <input type="text" id="zip_code_input" placeholder="Enter ZIP Code">
                    <input type="hidden" id="country_input" value="US">
                    <input type="hidden" id="state_input" value="">
                </div>
                <div class="check-button-container">
                    <button id="check_zip_code_button"><?php echo esc_html($atts['button_text']); ?></button>
                </div>
            </div>
            <?php wp_nonce_field('validate_zip_code_nonce', 'zip_code_security'); ?>
        </div>
        
        <div id="shipping_results" class="shipping-results">
            <!-- Results will be displayed here -->
        </div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        $('#check_zip_code_button').on('click', checkShippingAvailability);
        $('#zip_code_input').on('keypress', function(e) {
            if (e.which === 13) { // Enter key
                e.preventDefault();
                checkShippingAvailability();
            }
        });
        
        function checkShippingAvailability() {
            var zip_code = $('#zip_code_input').val();
            var security = $('#zip_code_security').val();
            
            if (!zip_code) {
                $('#shipping_results').html('<p class="error">Please enter a ZIP code.</p>');
                return;
            }
            
            $('#shipping_results').html('<p>Checking availability...</p>');
            
            // First get the state from ZIP code
            $.ajax({
                url: wc_shipping_checker.ajax_url,
                type: 'POST',
                data: {
                    action: 'get_state_from_zip',
                    zip_code: zip_code,
                    security: security
                },
                success: function(response) {
                    if (response.success) {
                        // We got the state, now check shipping availability
                        var state_id = response.data.state_id;
                        $('#state_input').val(state_id);
                        
                        // Check shipping availability with state and ZIP
                        checkShippingWithState(zip_code, 'US', state_id);
                    } else {
                        $('#shipping_results').html('<p class="error">' + response.data + '</p>');
                    }
                },
                error: function() {
                    $('#shipping_results').html('<p class="error">Error retrieving location data. Please try again.</p>');
                }
            });
        }
        
        function checkShippingWithState(zip_code, country, state) {
            var security = $('#zip_code_security').val();
            
            // Display California shipping restrictions if state is CA
            if (state === 'CA') {
                var california_notice = '<div class="california-notice">';
                california_notice += '<p><strong>*ATTENTION CALIFORNIA CUSTOMERS:</strong> CALIFORNIA SHIPPING IS ONLY AVAILABLE FOR:</p>';
                california_notice += '<p><a href="https://vapesocietysupplies.com/product-tag/tobacco-flavor/" target="_blank">TOBACCO FLAVORS</a> | ';
                california_notice += '<a href="https://vapesocietysupplies.com/collections/devices/" target="_blank">VAPE HARDWARE</a></p>';
                california_notice += '</div>';
                
                $('#shipping_results').html(california_notice + '<p>Checking shipping availability...</p>');
            }
            
            $.ajax({
                url: wc_shipping_checker.ajax_url,
                type: 'POST',
                data: {
                    action: 'validate_shipping_zip_code',
                    zip_code: zip_code,
                    country: country,
                    state: state,
                    security: security
                },
                success: function(response) {
                    if (response.success) {
                        if (response.data.methods && response.data.methods.length > 0) {
                            var methods_html = '<div class="success-message"><h3>Good news! We can ship to your location.</h3>';
                            
                            // Add California notice inside success message if applicable
                            if (state === 'CA') {
                                methods_html = '<div class="success-message">';
                                methods_html += '<div class="california-notice">';
                                methods_html += '<p><strong>*ATTENTION CALIFORNIA CUSTOMERS:</strong> CALIFORNIA SHIPPING IS ONLY AVAILABLE FOR:</p>';
                                methods_html += '<p><a href="https://vapesocietysupplies.com/product-tag/tobacco-flavor/" target="_blank">TOBACCO FLAVORS</a> | ';
                                methods_html += '<a href="https://vapesocietysupplies.com/collections/devices/" target="_blank">VAPE HARDWARE</a></p>';
                                methods_html += '</div>';
                                methods_html += '<h3>Good news! We can ship to your location.</h3>';
                            }
                            
                            methods_html += '<p>Available shipping methods:</p><ul>';
                            $.each(response.data.methods, function(index, method) {
                                methods_html += '<li>' + method.label;
                                if (method.cost) {
                                    methods_html += ' - ' + method.cost;
                                }
                                methods_html += '</li>';
                            });
                            methods_html += '</ul></div>';
                            $('#shipping_results').html(methods_html);
                        } else {
                            var error_html = '<p class="error">No shipping methods are available for your location.</p>';
                            
                            // Add California notice with error message if applicable
                            if (state === 'CA') {
                                error_html = '<div class="california-notice">';
                                error_html += '<p><strong>*ATTENTION CALIFORNIA CUSTOMERS:</strong> CALIFORNIA SHIPPING IS ONLY AVAILABLE FOR:</p>';
                                error_html += '<p><a href="https://vapesocietysupplies.com/product-tag/tobacco-flavor/" target="_blank">TOBACCO FLAVORS</a> | ';
                                error_html += '<a href="https://vapesocietysupplies.com/collections/devices/" target="_blank">VAPE HARDWARE</a></p>';
                                error_html += '</div>';
                                error_html += '<p class="error">No shipping methods are available for your location.</p>';
                            }
                            
                            $('#shipping_results').html(error_html);
                        }
                    } else {
                        var error_message = '<p class="error">' + response.data + '</p>';
                        
                        // Add California notice with error message if applicable
                        if (state === 'CA') {
                            error_message = '<div class="california-notice">';
                            error_message += '<p><strong>*ATTENTION CALIFORNIA CUSTOMERS:</strong> CALIFORNIA SHIPPING IS ONLY AVAILABLE FOR:</p>';
                            error_message += '<p><a href="https://vapesocietysupplies.com/product-tag/tobacco-flavor/" target="_blank">TOBACCO FLAVORS</a> | ';
                            error_message += '<a href="https://vapesocietysupplies.com/collections/devices/" target="_blank">VAPE HARDWARE</a></p>';
                            error_message += '</div>';
                            error_message += '<p class="error">' + response.data + '</p>';
                        }
                        
                        $('#shipping_results').html(error_message);
                    }
                },
                error: function() {
                    $('#shipping_results').html('<p class="error">An error occurred. Please try again.</p>');
                }
            });
        }
    });
    </script>
    <?php
    
    // Return the buffered content
    return ob_get_clean();
}
add_shortcode('shipping_checker', 'wc_shipping_checker_shortcode'); 