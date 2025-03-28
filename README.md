# EDH WooCommerce Matomo Tracking

A WordPress plugin that sends WooCommerce order details to Matomo for enhanced analytics tracking.

## Description

This plugin integrates WooCommerce with Matomo analytics by tracking:
- New orders
- Order status changes
- Order details (total, currency, items)
- Customer information
- Product categories

## Requirements

- WordPress 5.8 or higher
- PHP 7.4 or higher
- WooCommerce 5.0 or higher
- Matomo Analytics instance

## Installation

1. Download the plugin zip file
2. Go to WordPress admin > Plugins > Add New
3. Click "Upload Plugin" and select the downloaded zip file
4. Click "Install Now" and then "Activate"

## Configuration

1. Go to WooCommerce > Matomo Tracking in your WordPress admin
2. Enter your Matomo instance URL (e.g., https://analytics.example.com)
3. Enter your Matomo site ID
4. Enter your Matomo authentication token (required for secure server-side tracking)
5. Enable/disable tracking as needed
6. Click "Save Changes"

## Features

- Tracks new orders automatically
- Monitors order status changes
- Sends detailed order information including:
  - Order ID
  - Order total
  - Currency
  - Customer ID
  - Product details
  - Product categories
- Non-blocking API calls for better performance
- Secure data transmission
- Easy configuration through WordPress admin

## Data Privacy

This plugin respects user privacy by:
- Only sending necessary order data
- Not transmitting personal customer information
- Using secure HTTPS connections
- Following WordPress and WooCommerce data handling best practices

## Support

For support, please create an issue in the GitHub repository or contact EncodeDotHost support.

## License

This plugin is licensed under the GPL v3 or later.

## Credits

Developed by EncodeDotHost (https://encode.host)