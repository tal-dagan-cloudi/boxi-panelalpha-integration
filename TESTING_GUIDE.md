# Boxi PanelAlpha Integration - Testing Guide

This guide will help you set up and test the plugin in a complete local WordPress environment.

## 🚀 Quick Start (5 minutes)

### 1. Start the Test Environment

```bash
cd /Users/tald/Projects/Boxi/boxi-panelalpha-integration

# Start all services (WordPress, MySQL, phpMyAdmin)
./test-environment.sh start
```

**What this does:**
- Starts WordPress 6.x with WooCommerce
- Creates MySQL database
- Automatically installs and activates the plugin
- Creates 3 test products (Shared Hosting, VPS, Dedicated Server)
- Creates admin user and test customer
- Sets up phpMyAdmin for database inspection

**Wait time:** 30-60 seconds for WordPress setup to complete.

### 2. Access WordPress Admin

Open your browser and navigate to:
```
http://localhost:8080/wp-admin/
```

**Login credentials:**
- **Username:** `admin`
- **Password:** `admin123`

### 3. Configure PanelAlpha API

1. Navigate to **Boxi Integration → Settings**
2. Click the **API Credentials** tab
3. Enter the following:
   - **API URL:** `https://panel.boxi.co.il:8443/`
   - **API Token:** `1|zfHscGuXRbGWhKKZR28WWSuD9rHZPcG6KpvxugXS`
4. Click **Test Connection** (should show green success message)
5. Click **Save Credentials**

### 4. Create Product Mappings

1. Click the **Product Mappings** tab
2. For each product, create a mapping:

   **Shared Hosting - Basic**
   - Select product from dropdown
   - Enter PanelAlpha Plan ID (e.g., `1`)
   - Check "Auto-Provision"
   - Click "Add Mapping"

   **VPS Hosting - Standard**
   - Plan ID: `2`
   - Auto-Provision: Yes

   **Dedicated Server - Premium**
   - Plan ID: `3`
   - Auto-Provision: Yes

---

## 🧪 Testing Scenarios

### Test 1: Complete Order Provisioning Flow

**Objective:** Verify that completing an order triggers automatic provisioning.

1. **Create an order:**
   - Go to **WooCommerce → Orders → Add New**
   - Click "Add item(s)" → "Add products"
   - Select "Shared Hosting - Basic"
   - Add customer details:
     - Email: `test@example.com`
     - First Name: `Test`
     - Last Name: `Customer`
   - Add custom field for domain:
     - Field name: `_domain`
     - Value: `test-domain.com`
   - Click "Create"

2. **Mark order as completed:**
   - Change order status to "Completed"
   - Click "Update"

3. **Verify provisioning started:**
   - Scroll down to "Boxi PanelAlpha Integration" metabox
   - Should show status: "Provisioning" or "Pending Provisioning"

4. **Check event logs:**
   - Go to **Boxi Integration → Event Logs**
   - Should see entries:
     - "Order completed - provisioning enqueued"
     - "Provisioning job started"
     - API calls to PanelAlpha

5. **Wait for completion:**
   - Provisioning takes 1-5 minutes depending on PanelAlpha API
   - Refresh order page
   - Status should change to "Active"

6. **Verify credentials saved:**
   - In order metabox, click **Reveal Credentials**
   - Should display username, password, and email
   - Credentials should be valid PanelAlpha credentials

7. **Check customer email:**
   - Customer should receive welcome email with credentials
   - Check WordPress mail logs or use plugin like WP Mail Logging

**Expected Results:**
- ✅ Order status changes to "Active"
- ✅ Service ID saved to order meta
- ✅ Credentials encrypted and saved
- ✅ Customer receives email
- ✅ All events logged in event log

---

### Test 2: Subscription Payment Failure (Suspend Service)

**Objective:** Verify that failed subscription payments suspend the hosting service.

**Prerequisites:** Install WooCommerce Subscriptions plugin (optional - can simulate with manual hooks)

1. **Simulate payment failure:**
   ```bash
   # Using WP-CLI
   ./test-environment.sh wp post meta update <ORDER_ID> _subscription_status failed
   ```

2. **Trigger suspension hook manually:**
   ```php
   // In WordPress admin → Tools → Site Health → Info
   do_action('woocommerce_subscription_payment_failed', <ORDER_ID>);
   ```

3. **Verify service suspended:**
   - Check order metabox - status should be "Suspended"
   - Check event logs for "Service suspended" event
   - Verify PanelAlpha API was called to suspend service

**Expected Results:**
- ✅ Service status changed to "Suspended"
- ✅ PanelAlpha service suspended via API
- ✅ Event logged

---

### Test 3: Subscription Cancellation (Cancel Service)

**Objective:** Verify that cancelled subscriptions cancel the hosting service.

1. **Cancel subscription:**
   - Change order status to "Cancelled"
   - Or trigger hook manually:
   ```php
   do_action('woocommerce_subscription_status_cancelled', <SUBSCRIPTION_ID>);
   ```

2. **Verify service cancelled:**
   - Order metabox status should be "Cancelled"
   - Event logs show "Service cancelled"
   - PanelAlpha service terminated

**Expected Results:**
- ✅ Service status changed to "Cancelled"
- ✅ PanelAlpha service cancelled via API
- ✅ Event logged with context

---

### Test 4: Retry Failed Provisioning

**Objective:** Test the retry button for failed provisioning attempts.

1. **Simulate provisioning failure:**
   - Edit order
   - Set meta: `_panelalpha_status` = `failed`
   - Remove meta: `_panelalpha_service_id`

2. **Click Retry Provisioning button** in order metabox

3. **Verify retry:**
   - New provisioning job enqueued
   - Event logs show "Provisioning retried"
   - Workflow restarts from beginning

**Expected Results:**
- ✅ New provisioning job created
- ✅ Status resets to "Provisioning"
- ✅ Workflow completes successfully

---

### Test 5: Duplicate Order Prevention (Idempotency)

**Objective:** Ensure the same order cannot be provisioned twice.

1. **Complete an order** (use Test 1 steps)

2. **Wait for provisioning to complete**

3. **Try to trigger provisioning again:**
   - Change order status to "Pending" then back to "Completed"
   - Or manually trigger: `do_action('woocommerce_order_status_completed', <ORDER_ID>)`

4. **Verify duplicate prevention:**
   - Check event logs for "Order already processed" message
   - No new provisioning job created
   - Service ID remains unchanged

**Expected Results:**
- ✅ Duplicate provisioning blocked
- ✅ Warning logged in event log
- ✅ Existing service unaffected

---

### Test 6: Rate Limiting

**Objective:** Verify that the plugin respects the 55 requests/minute rate limit.

1. **Trigger multiple rapid API calls:**
   ```bash
   # Using WP-CLI, create 60 test orders quickly
   for i in {1..60}; do
       ./test-environment.sh wp wc order create \
           --user=2 \
           --product_id=<PRODUCT_ID> \
           --status=completed
   done
   ```

2. **Monitor event logs:**
   - Should see rate limiting messages
   - API calls should be throttled
   - No API errors from exceeding rate limit

3. **Check timing:**
   - 60 requests should take at least ~65 seconds (60/55 minutes)

**Expected Results:**
- ✅ All requests succeed
- ✅ Rate limit respected (no API errors)
- ✅ Requests spread out over time

---

### Test 7: Credential Encryption/Decryption

**Objective:** Verify credentials are properly encrypted and can be decrypted.

1. **Complete an order with provisioning**

2. **Check database directly:**
   - Go to http://localhost:8081/ (phpMyAdmin)
   - Database: `wordpress`
   - Table: `wp_postmeta`
   - Find row with `meta_key = '_panelalpha_credentials_encrypted'`
   - Value should be base64-encoded gibberish (encrypted)

3. **Reveal credentials in admin:**
   - Click "Reveal Credentials" in order metabox
   - Should display plaintext username/password

4. **Verify audit logging:**
   - Check event logs
   - Should see "Admin revealed credentials" with user ID

**Expected Results:**
- ✅ Credentials stored encrypted in database
- ✅ Decryption works correctly
- ✅ Credential access logged

---

### Test 8: Error Handling and Rollback

**Objective:** Test that failed provisioning triggers automatic rollback.

1. **Simulate API failure:**
   - Temporarily change API token to invalid value
   - Or disconnect from internet

2. **Complete an order**

3. **Wait for provisioning to fail**

4. **Verify rollback:**
   - Check event logs for error messages
   - Order status should show "Failed" or remain "Pending"
   - No partial services left in PanelAlpha

**Expected Results:**
- ✅ Error logged with detailed context
- ✅ Provisioning marked as failed
- ✅ No orphaned resources in PanelAlpha

---

### Test 9: Multi-Product Order

**Objective:** Verify handling of orders with multiple hosting products.

1. **Create order with 2 hosting products:**
   - Add "Shared Hosting - Basic"
   - Add "VPS Hosting - Standard"

2. **Complete order**

3. **Verify provisioning:**
   - Two separate services should be created
   - Two sets of credentials saved
   - Event logs show both provisioning workflows

**Expected Results:**
- ✅ Both products provisioned
- ✅ Each has separate service ID
- ✅ All events logged

---

### Test 10: Event Log Filtering

**Objective:** Test the event log filtering functionality.

1. **Create multiple orders** to generate logs

2. **Test filters:**
   - Filter by log level (Error, Warning, Info, Debug)
   - Filter by event type (API, Provisioning, Queue, etc.)
   - Filter by specific Order ID

3. **Test pagination:**
   - Verify pagination works with large result sets

4. **View context:**
   - Click "View Context" on any log entry
   - Modal should show formatted JSON

**Expected Results:**
- ✅ Filters work correctly
- ✅ Pagination displays properly
- ✅ Context modal shows detailed info

---

## 🔍 Database Inspection

### Check Integration Logs Table

```bash
./test-environment.sh wp db query "SELECT * FROM wp_boxi_integration_logs ORDER BY created_at DESC LIMIT 10"
```

### Check Order Meta

```bash
./test-environment.sh wp post meta list <ORDER_ID>
```

### Check Product Mappings

```bash
./test-environment.sh wp option get boxi_product_mappings
```

---

## 🛠️ Useful Commands

### Check Plugin Status

```bash
./test-environment.sh check
```

### View WordPress Logs

```bash
./test-environment.sh logs wordpress
```

### View Database Logs

```bash
./test-environment.sh logs db
```

### Run WP-CLI Commands

```bash
# List all orders
./test-environment.sh wp post list --post_type=shop_order

# List all products
./test-environment.sh wp post list --post_type=product

# Check Action Scheduler jobs
./test-environment.sh wp action-scheduler list
```

### Restart Environment

```bash
./test-environment.sh restart
```

### Clean and Start Fresh

```bash
./test-environment.sh clean
./test-environment.sh start
```

---

## 🐛 Troubleshooting

### WordPress not accessible

```bash
# Check if containers are running
./test-environment.sh status

# View logs for errors
./test-environment.sh logs wordpress
```

### Plugin not activated

```bash
# Manually activate plugin
./test-environment.sh wp plugin activate boxi-panelalpha-integration
```

### Database connection errors

```bash
# Restart database
docker-compose restart db

# Wait for health check
sleep 10
```

### Provisioning not starting

1. Check Action Scheduler:
   ```bash
   ./test-environment.sh wp action-scheduler list
   ```

2. Check event logs in admin panel

3. Verify product is mapped to PanelAlpha plan

4. Verify order has domain in custom field `_domain`

---

## 📊 Test Results Checklist

Copy this checklist to track your testing progress:

- [ ] **Test 1:** Order provisioning flow completed successfully
- [ ] **Test 2:** Payment failure suspends service
- [ ] **Test 3:** Subscription cancellation cancels service
- [ ] **Test 4:** Retry provisioning works
- [ ] **Test 5:** Duplicate prevention (idempotency) works
- [ ] **Test 6:** Rate limiting respected
- [ ] **Test 7:** Credential encryption/decryption works
- [ ] **Test 8:** Error handling and rollback work
- [ ] **Test 9:** Multi-product orders handled correctly
- [ ] **Test 10:** Event log filtering works

---

## 🎯 Next Steps After Testing

Once all tests pass:

1. **Security Audit:**
   - Review all capability checks
   - Verify CSRF protection on AJAX endpoints
   - Test credential encryption strength

2. **Performance Testing:**
   - Test with 100+ concurrent orders
   - Monitor database query performance
   - Check Action Scheduler queue handling

3. **Production Deployment:**
   - Update IMPLEMENTATION_STATUS.md
   - Deploy to staging environment (boxi.co.il)
   - Monitor live provisioning for 1 week
   - Collect customer feedback

---

## 📞 Support

If you encounter issues:

1. Check event logs in WordPress admin
2. Check Docker logs: `./test-environment.sh logs wordpress`
3. Inspect database in phpMyAdmin: http://localhost:8081/
4. Review WordPress debug.log (if WP_DEBUG enabled)

---

**Last Updated:** 2025-01-17
**Plugin Version:** 1.0.0
**Test Environment:** Docker Compose (WordPress 6.x, WooCommerce 8.x, MySQL 8.0)
