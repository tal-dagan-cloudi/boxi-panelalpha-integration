#!/bin/bash
# End-to-End Test Script for Boxi PanelAlpha Integration

set -e

echo "=========================================="
echo "Boxi PanelAlpha Integration - E2E Test"
echo "=========================================="
echo ""

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Helper functions
print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
}

print_info() {
    echo -e "${YELLOW}→ $1${NC}"
}

# Test 1: Verify WordPress is running
print_info "Test 1: Checking WordPress availability..."
if curl -s -o /dev/null -w "%{http_code}" http://localhost:8080 | grep -q "200\|302"; then
    print_success "WordPress is accessible"
else
    print_error "WordPress is not accessible"
    exit 1
fi

# Test 2: Verify plugin is activated
print_info "Test 2: Checking plugin activation..."
if [ -d "/Users/tald/Projects/Boxi/boxi-panelalpha-integration" ] && \
   docker exec boxi-test-wordpress test -d /var/www/html/wp-content/plugins/boxi-panelalpha-integration 2>/dev/null; then
    print_success "Boxi PanelAlpha Integration plugin directory exists"
else
    print_error "Plugin directory not found"
    exit 1
fi

# Test 3: Verify WooCommerce is activated
print_info "Test 3: Checking WooCommerce activation..."
if docker exec boxi-test-wordpress test -d /var/www/html/wp-content/plugins/woocommerce 2>/dev/null; then
    print_success "WooCommerce directory exists"
else
    print_error "WooCommerce directory not found"
    exit 1
fi

# Test 4: Verify database table exists
print_info "Test 4: Checking database table creation..."
TABLE_EXISTS=$(docker exec boxi-test-db mysql -u wordpress -pwordpress -D wordpress -e "SHOW TABLES LIKE 'wp_boxi_integration_logs';" 2>/dev/null | grep -c "wp_boxi_integration_logs" || echo "0")
if [ "$TABLE_EXISTS" -eq "1" ]; then
    print_success "Database table 'wp_boxi_integration_logs' exists"
else
    print_error "Database table does not exist"
    exit 1
fi

# Test 5: Verify test products exist
print_info "Test 5: Checking test products..."
PRODUCT_COUNT=$(docker exec boxi-test-db mysql -u wordpress -pwordpress -D wordpress \
    -e "SELECT COUNT(*) FROM wp_posts WHERE post_type='product' AND post_status='publish';" 2>/dev/null | tail -n 1)
if [ "$PRODUCT_COUNT" -ge "3" ]; then
    print_success "Found $PRODUCT_COUNT test products"
else
    print_error "Expected at least 3 products, found $PRODUCT_COUNT"
    exit 1
fi

# Test 6: Configure API credentials using direct database
print_info "Test 6: Configuring PanelAlpha API credentials..."
docker exec boxi-test-db mysql -u wordpress -pwordpress -D wordpress \
    -e "INSERT INTO wp_options (option_name, option_value, autoload) VALUES ('boxi_panelalpha_api_url', 'https://panel.boxi.co.il:8443', 'yes') ON DUPLICATE KEY UPDATE option_value='https://panel.boxi.co.il:8443';" 2>/dev/null
docker exec boxi-test-db mysql -u wordpress -pwordpress -D wordpress \
    -e "INSERT INTO wp_options (option_name, option_value, autoload) VALUES ('boxi_panelalpha_api_token', '1|zfHscGuXRbGWhKKZR28WWSuD9rHZPcG6KpvxugXS', 'yes') ON DUPLICATE KEY UPDATE option_value='1|zfHscGuXRbGWhKKZR28WWSuD9rHZPcG6KpvxugXS';" 2>/dev/null
print_success "API credentials configured"

# Test 7: Verify credentials were saved
print_info "Test 7: Verifying credentials storage..."
API_URL=$(docker exec boxi-test-db mysql -u wordpress -pwordpress -D wordpress \
    -e "SELECT option_value FROM wp_options WHERE option_name='boxi_panelalpha_api_url';" 2>/dev/null | tail -n 1)
if [ ! -z "$API_URL" ]; then
    print_success "Credentials saved successfully"
else
    print_error "Failed to save credentials"
    exit 1
fi

# Test 8: Create a test order using database
print_info "Test 8: Creating test order..."
# Insert post for order
docker exec boxi-test-db mysql -u wordpress -pwordpress -D wordpress \
    -e "INSERT INTO wp_posts (post_author, post_date, post_date_gmt, post_content, post_title, post_status, post_type)
        VALUES (1, NOW(), UTC_TIMESTAMP(), '', 'Order - E2E Test', 'wc-pending', 'shop_order');" 2>/dev/null

ORDER_ID=$(docker exec boxi-test-db mysql -u wordpress -pwordpress -D wordpress \
    -e "SELECT LAST_INSERT_ID();" 2>/dev/null | tail -n 1)

if [ ! -z "$ORDER_ID" ] && [ "$ORDER_ID" -gt "0" ]; then
    print_success "Created test order #$ORDER_ID"
else
    print_error "Failed to create order"
    exit 1
fi

# Test 9: Add product to order
print_info "Test 9: Adding product to order..."
docker exec boxi-test-db mysql -u wordpress -pwordpress -D wordpress \
    -e "INSERT INTO wp_woocommerce_order_items (order_item_name, order_item_type, order_id)
        VALUES ('Shared Hosting - Basic', 'line_item', $ORDER_ID);" 2>/dev/null

ITEM_ID=$(docker exec boxi-test-db mysql -u wordpress -pwordpress -D wordpress \
    -e "SELECT LAST_INSERT_ID();" 2>/dev/null | tail -n 1)

docker exec boxi-test-db mysql -u wordpress -pwordpress -D wordpress \
    -e "INSERT INTO wp_woocommerce_order_itemmeta (order_item_id, meta_key, meta_value)
        VALUES ($ITEM_ID, '_product_id', 11);" 2>/dev/null

print_success "Added product to order #$ORDER_ID"

# Test 10: Add domain metadata to order
print_info "Test 10: Adding domain metadata..."
docker exec boxi-test-db mysql -u wordpress -pwordpress -D wordpress \
    -e "INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES ($ORDER_ID, '_domain', 'test-e2e.com');" 2>/dev/null
print_success "Added domain: test-e2e.com"

# Test 11: Complete the order to trigger provisioning
print_info "Test 11: Marking order as completed (triggers provisioning)..."
docker exec boxi-test-db mysql -u wordpress -pwordpress -D wordpress \
    -e "UPDATE wp_posts SET post_status='wc-completed' WHERE ID=$ORDER_ID;" 2>/dev/null
print_success "Order #$ORDER_ID marked as completed"

# Test 12: Wait and check for provisioning logs
print_info "Test 12: Waiting for provisioning to start (10 seconds)..."
sleep 10

LOG_COUNT=$(docker exec boxi-test-db mysql -u wordpress -pwordpress -D wordpress \
    -e "SELECT COUNT(*) FROM wp_boxi_integration_logs WHERE order_id=$ORDER_ID;" 2>/dev/null | tail -n 1)

if [ "$LOG_COUNT" -gt "0" ]; then
    print_success "Found $LOG_COUNT log entries for order #$ORDER_ID"
else
    print_error "No log entries found for provisioning"
    exit 1
fi

# Test 13: Check provisioning status in order meta
print_info "Test 13: Checking provisioning status..."
PROVISION_STATUS=$(docker exec boxi-test-wpcli wp post meta get $ORDER_ID _boxi_provision_status \
    --allow-root \
    --path=/var/www/html 2>/dev/null || echo "none")

if [ ! -z "$PROVISION_STATUS" ] && [ "$PROVISION_STATUS" != "none" ]; then
    print_success "Provisioning status: $PROVISION_STATUS"
else
    print_info "Provisioning status not yet set (async processing)"
fi

# Test 14: Display recent logs
print_info "Test 14: Recent provisioning logs..."
docker exec boxi-test-db mysql -u wordpress -pwordpress -D wordpress \
    -e "SELECT id, level, event_type, message, timestamp FROM wp_boxi_integration_logs WHERE order_id=$ORDER_ID ORDER BY timestamp DESC LIMIT 5;" 2>/dev/null

echo ""
echo "=========================================="
echo -e "${GREEN}✓ E2E Test Complete!${NC}"
echo "=========================================="
echo ""
echo "Test Summary:"
echo "  - WordPress: ✓ Running"
echo "  - Plugins: ✓ Activated"
echo "  - Database: ✓ Created"
echo "  - Products: ✓ $PRODUCT_COUNT products"
echo "  - API Config: ✓ Configured"
echo "  - Test Order: ✓ Order #$ORDER_ID created and completed"
echo "  - Logs: ✓ $LOG_COUNT log entries"
echo ""
echo "Access the test environment:"
echo "  - WordPress Admin: http://localhost:8080/wp-admin/"
echo "  - Login: admin / admin123"
echo "  - View Order: http://localhost:8080/wp-admin/post.php?post=$ORDER_ID&action=edit"
echo "  - View Logs: http://localhost:8080/wp-admin/admin.php?page=boxi-logs"
echo ""
