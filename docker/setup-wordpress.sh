#!/bin/sh
set -e

echo "Waiting for WordPress to be ready..."
sleep 30

# Check if WordPress is already installed
if ! wp core is-installed --allow-root --path=/var/www/html 2>/dev/null; then
    echo "Installing WordPress..."

    # Install WordPress
    wp core install \
        --url="http://localhost:8080" \
        --title="Boxi Test Site" \
        --admin_user="admin" \
        --admin_password="admin123" \
        --admin_email="admin@example.com" \
        --allow-root \
        --path=/var/www/html

    echo "WordPress installed successfully!"
else
    echo "WordPress is already installed."
fi

# Install and activate WooCommerce
echo "Installing WooCommerce..."
if ! wp plugin is-installed woocommerce --allow-root --path=/var/www/html; then
    wp plugin install woocommerce --activate --allow-root --path=/var/www/html
else
    echo "WooCommerce is already installed."
    wp plugin activate woocommerce --allow-root --path=/var/www/html 2>/dev/null || true
fi

# Install Action Scheduler (WooCommerce dependency)
echo "Ensuring Action Scheduler is available..."

# Configure WooCommerce basic settings
echo "Configuring WooCommerce..."
wp option update woocommerce_store_address "123 Test Street" --allow-root --path=/var/www/html
wp option update woocommerce_store_city "Tel Aviv" --allow-root --path=/var/www/html
wp option update woocommerce_default_country "IL" --allow-root --path=/var/www/html
wp option update woocommerce_currency "ILS" --allow-root --path=/var/www/html

# Activate Boxi PanelAlpha Integration plugin
echo "Activating Boxi PanelAlpha Integration plugin..."
if wp plugin is-installed boxi-panelalpha-integration --allow-root --path=/var/www/html; then
    wp plugin activate boxi-panelalpha-integration --allow-root --path=/var/www/html
    echo "Boxi PanelAlpha Integration plugin activated!"
else
    echo "Warning: Boxi PanelAlpha Integration plugin not found. Please ensure it's mounted correctly."
fi

# Create test products
echo "Creating test products..."

# Check if products already exist
if ! wp post list --post_type=product --allow-root --path=/var/www/html | grep -q "Shared Hosting"; then
    # Create Shared Hosting product
    SHARED_HOSTING_ID=$(wp post create \
        --post_type=product \
        --post_title="Shared Hosting - Basic" \
        --post_content="Basic shared hosting plan with 10GB storage and unlimited bandwidth." \
        --post_status=publish \
        --allow-root \
        --path=/var/www/html \
        --porcelain)

    wp post meta update $SHARED_HOSTING_ID _regular_price 99.00 --allow-root --path=/var/www/html
    wp post meta update $SHARED_HOSTING_ID _price 99.00 --allow-root --path=/var/www/html
    wp post meta update $SHARED_HOSTING_ID _virtual yes --allow-root --path=/var/www/html

    echo "Created product: Shared Hosting - Basic (ID: $SHARED_HOSTING_ID)"
fi

if ! wp post list --post_type=product --allow-root --path=/var/www/html | grep -q "VPS Hosting"; then
    # Create VPS Hosting product
    VPS_HOSTING_ID=$(wp post create \
        --post_type=product \
        --post_title="VPS Hosting - Standard" \
        --post_content="Virtual Private Server with dedicated resources and root access." \
        --post_status=publish \
        --allow-root \
        --path=/var/www/html \
        --porcelain)

    wp post meta update $VPS_HOSTING_ID _regular_price 299.00 --allow-root --path=/var/www/html
    wp post meta update $VPS_HOSTING_ID _price 299.00 --allow-root --path=/var/www/html
    wp post meta update $VPS_HOSTING_ID _virtual yes --allow-root --path=/var/www/html

    echo "Created product: VPS Hosting - Standard (ID: $VPS_HOSTING_ID)"
fi

if ! wp post list --post_type=product --allow-root --path=/var/www/html | grep -q "Dedicated Server"; then
    # Create Dedicated Server product
    DEDICATED_ID=$(wp post create \
        --post_type=product \
        --post_title="Dedicated Server - Premium" \
        --post_content="High-performance dedicated server with full control and management." \
        --post_status=publish \
        --allow-root \
        --path=/var/www/html \
        --porcelain)

    wp post meta update $DEDICATED_ID _regular_price 999.00 --allow-root --path=/var/www/html
    wp post meta update $DEDICATED_ID _price 999.00 --allow-root --path=/var/www/html
    wp post meta update $DEDICATED_ID _virtual yes --allow-root --path=/var/www/html

    echo "Created product: Dedicated Server - Premium (ID: $DEDICATED_ID)"
fi

# Create a test customer
echo "Creating test customer..."
if ! wp user list --role=customer --allow-root --path=/var/www/html | grep -q "testcustomer"; then
    wp user create testcustomer test@example.com \
        --role=customer \
        --user_pass=customer123 \
        --first_name=Test \
        --last_name=Customer \
        --display_name="Test Customer" \
        --allow-root \
        --path=/var/www/html

    echo "Created test customer: testcustomer / customer123"
fi

echo ""
echo "=========================================="
echo "WordPress Test Environment Setup Complete!"
echo "=========================================="
echo ""
echo "Access URLs:"
echo "  WordPress Admin: http://localhost:8080/wp-admin/"
echo "  Username: admin"
echo "  Password: admin123"
echo ""
echo "  phpMyAdmin: http://localhost:8081/"
echo "  Database: wordpress"
echo "  Username: wordpress"
echo "  Password: wordpress"
echo ""
echo "Test Customer:"
echo "  Username: testcustomer"
echo "  Password: customer123"
echo "  Email: test@example.com"
echo ""
echo "Next Steps:"
echo "1. Configure Boxi PanelAlpha Integration in WordPress admin"
echo "2. Map products to PanelAlpha plans"
echo "3. Create test orders to trigger provisioning"
echo ""
echo "=========================================="
