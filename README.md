# Vape Society Shipping Checker

A WordPress/WooCommerce plugin that allows customers to check if shipping is available to their location by entering a zip code.

## Features

- Simple ZIP code validation interface
- Integration with WooCommerce shipping zones
- Special shipping restriction notifications for California customers
- Supports both page template and shortcode implementation
- Mobile-responsive design

## Usage

### Shortcode

You can use the shipping checker anywhere on your site with the following shortcode:

```
[shipping_checker]
```

Optional parameters:

- `title`: Change the main heading (default: "DO WE SHIP TO YOU?")
- `button_text`: Change the search button text (default: "Search")

Example:

```
[shipping_checker title="Check Shipping Availability" button_text="Check Now"]
```

### Page Template

The plugin also includes a dedicated page template "Shipping Availability Checker" that can be selected when creating a new page.

## Requirements

- WordPress 5.0 or higher
- WooCommerce 4.0 or higher
- PHP 7.2 or higher

## Installation

1. Upload the plugin files to the `/wp-content/plugins/vape-society-shipping-checker` directory
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Configure your WooCommerce shipping zones as needed
4. Use the shortcode or create a page with the shipping checker template
