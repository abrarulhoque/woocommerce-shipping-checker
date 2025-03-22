<?php
/**
 * Template Name: Shipping Availability Checker
 *
 * A custom page template for checking shipping availability by ZIP code
 */
get_header();
?>

<div class="shipping-checker-container">
    <h1>Do We Ship To You?</h1>
    <p>Enter your ZIP code below to check if we can ship to your location.</p>
    
    <div class="zip-code-checker-form">
        <div class="form-row">
            <div class="form-group zip-input-group">
                <input type="text" id="zip_code_input" placeholder="Enter ZIP Code">
                <input type="hidden" id="country_input" value="US">
                <input type="hidden" id="state_input" value="">
            </div>
            <div class="check-button-container">
                <button id="check_zip_code_button">CHECK AVAILABILITY</button>
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

<?php get_footer(); ?> 