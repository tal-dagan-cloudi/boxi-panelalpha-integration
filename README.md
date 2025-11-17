# Boxi PanelAlpha Integration Plugin

Automates WordPress hosting provisioning by connecting WooCommerce orders to PanelAlpha hosting management platform.

## Overview

This WordPress plugin creates a seamless integration between:
- **WooCommerce** (e-commerce/order management)
- **PanelAlpha** (WordPress hosting provisioning and management)

When a customer purchases hosting through your WooCommerce store, this plugin automatically:
1. Creates a PanelAlpha user account (if needed)
2. Provisions a WordPress hosting instance
3. Retrieves login credentials
4. Emails credentials to the customer
5. Manages service lifecycle (suspend on payment failure, cancel on cancellation)

## Requirements

### System Requirements
- **WordPress**: 5.8 or higher
- **PHP**: 7.4 or higher (8.0+ recommended)
- **WooCommerce**: 5.0 or higher
- **HTTPS**: Required (SSL certificate installed)
- **MySQL/MariaDB**: 5.6 or higher

### External Services
- **PanelAlpha Installation**: Active PanelAlpha instance with API access
- **SMTP Service**: Configured email sending (Gmail, SendGrid, AWS SES, etc.)

## Quick Start (Testing)

### 🐳 Docker Test Environment (Recommended for Testing)

The fastest way to test the plugin is using our pre-configured Docker environment:

```bash
cd boxi-panelalpha-integration
./test-environment.sh start
```

This will:
- Start WordPress 6.x with WooCommerce
- Automatically install and activate the plugin
- Create test products and customer accounts
- Set up phpMyAdmin for database inspection

**Access:**
- WordPress Admin: http://localhost:8080/wp-admin/ (admin / admin123)
- phpMyAdmin: http://localhost:8081/

**Full Testing Guide:** See `TESTING_GUIDE.md` for complete testing scenarios and workflows.

---

## Installation (Production)

### Step 1: Upload Plugin

1. **Download** the `boxi-panelalpha-integration` folder
2. **Upload** to your WordPress installation:
   ```bash
   # Via SFTP/FTP
   Upload to: /wp-content/plugins/boxi-panelalpha-integration/

   # Via SSH
   cd /path/to/wordpress/wp-content/plugins/
   # Upload the folder here
   ```

3. **Set Permissions**:
   ```bash
   chmod 755 boxi-panelalpha-integration
   chmod 644 boxi-panelalpha-integration/*.php
   ```

### Step 2: Activate Plugin

1. Log in to **WordPress Admin**
2. Navigate to **Plugins → Installed Plugins**
3. Find "**Boxi PanelAlpha Integration**"
4. Click **Activate**

### Step 3: Generate API Credentials

#### WooCommerce API Credentials

1. Go to **WooCommerce → Settings → Advanced → REST API**
2. Click **Add Key**
3. Fill in:
   - **Description**: "Boxi PanelAlpha Integration"
   - **User**: Select your admin user
   - **Permissions**: **Read/Write**
4. Click **Generate API Key**
5. **Copy** and save:
   - **Consumer Key** (starts with `ck_`)
   - **Consumer Secret** (starts with `cs_`)

   ⚠️ **Important**: You'll only see the secret once! Save it securely.

#### PanelAlpha API Token

1. Log in to **PanelAlpha Admin Panel**
2. Navigate to **Configuration → Admins → API Tokens**
3. Click **Generate New Token**
4. Fill in:
   - **Name**: "WooCommerce Integration"
   - **Permissions**: **Full Admin Access**
5. **Copy** the generated token (starts with long alphanumeric string)

#### PanelAlpha Plan IDs

1. In **PanelAlpha Admin**, go to **Plans**
2. Note the **Plan ID** for each hosting tier:
   - Example: "Basic WordPress Hosting" → `plan_basic_001`
   - Example: "Pro WordPress Hosting" → `plan_pro_001`
3. You'll need these to map to WooCommerce products

### Step 4: Configure Plugin

1. In **WordPress Admin**, go to **Boxi Integration → Settings**
2. Fill in the **PanelAlpha Settings** section:
   - **API URL**: `https://your-panelalpha-domain.com/api` (no trailing slash)
   - **API Token**: Paste the token from Step 3
   - Click **Test Connection** to verify

3. Scroll to **Product Mappings** section
4. For each hosting product:
   - **Select WooCommerce Product** from dropdown
   - **Enter PanelAlpha Plan ID** (e.g., `plan_basic_001`)
   - **Enable Auto-Provisioning** checkbox
   - Click **Add Mapping**

5. Configure **Email Settings**:
   - **From Name**: "Boxi Hosting" (or your company name)
   - **From Email**: support@boxi.co.il (or your support email)

6. Click **Save All Settings**

### Step 5: Test Configuration

#### Test SMTP Email

1. In **WordPress Admin**, go to **Tools → Site Health → Info → Email**
2. Send a test email to yourself
3. Verify it arrives (check spam folder if not)

#### Test Integration (Staging Environment Recommended)

1. Create a **test WooCommerce product** (e.g., "Test Hosting - $1")
2. Map it to a PanelAlpha plan in **Boxi Integration → Settings**
3. Place a test order:
   - Add product to cart
   - Checkout as a new customer (use a real email you can check)
   - Complete payment (use test payment gateway if available)
4. Verify:
   - ✅ Order status changes to "Completed"
   - ✅ Check **Boxi Integration → Event Log** for provisioning events
   - ✅ Check the order in **WooCommerce → Orders** for "Boxi Provisioning" metabox
   - ✅ Customer receives email with WordPress credentials (within 5 minutes)
   - ✅ PanelAlpha shows new user and service

## Configuration Reference

### Required Settings

| Setting | Description | Example |
|---------|-------------|---------|
| **PanelAlpha API URL** | Base URL for PanelAlpha API (no trailing slash) | `https://panel.boxi.co.il/api` |
| **PanelAlpha API Token** | Admin-level bearer token from PanelAlpha | `abc123def456...` |
| **Product Mappings** | Links WooCommerce products to PanelAlpha plans | Product ID 123 → `plan_basic_001` |

### Optional Settings

| Setting | Default | Description |
|---------|---------|-------------|
| **Rate Limit** | 55 req/min | Max API calls to PanelAlpha per minute |
| **Provisioning Timeout** | 5 minutes | Max time to wait for WordPress instance creation |
| **Retry Attempts** | 5 | Number of times to retry failed provisions |
| **Log Retention** | 90 days | How long to keep integration event logs |

## Usage

### Normal Operation

Once configured, the plugin operates **automatically**:

1. **Customer Orders Hosting** → Plugin detects order completion
2. **Plugin Creates Account** → PanelAlpha user created (if new customer)
3. **Plugin Provisions Service** → WordPress instance created in background
4. **Customer Receives Email** → Credentials sent within 5 minutes

**No manual intervention required!**

### Admin Monitoring

#### View Event Logs

**Boxi Integration → Event Log**

- See all integration events (account created, service provisioned, emails sent)
- Filter by date, event type, status (success/error)
- Click on events for detailed information

#### View Order Provisioning Status

1. Go to **WooCommerce → Orders**
2. Click on any hosting order
3. See **"Boxi Provisioning Status"** metabox on right side:
   - PanelAlpha Service ID (linked to PanelAlpha)
   - Provisioning status (Pending/Active/Error)
   - Customer PanelAlpha User ID
   - WordPress credentials (click "Reveal" to view)
   - **Retry Provisioning** button (if failed)

### Manual Retry

If provisioning fails:

1. Go to order in **WooCommerce → Orders**
2. Find **"Boxi Provisioning Status"** metabox
3. Click **"Retry Provisioning"** button
4. Plugin will re-queue the job
5. Check **Event Log** for results

### Subscription Management

The plugin automatically handles WooCommerce Subscriptions:

- **Payment Fails** → Service suspended in PanelAlpha
- **Payment Succeeds (Renewal)** → Service unsuspended
- **Subscription Cancelled** → Service cancelled (data retained for 30 days)

## Troubleshooting

### Common Issues

#### 1. Credentials Email Not Received

**Possible Causes**:
- Email in spam folder
- SMTP not configured correctly
- Email quota exceeded

**Solutions**:
1. Check spam/junk folder
2. Test WordPress email sending (**Tools → Site Health**)
3. Check **Boxi Integration → Event Log** for email send errors
4. Manually retrieve credentials from order metabox

#### 2. Provisioning Stuck in "Pending"

**Possible Causes**:
- PanelAlpha server overloaded
- Network connectivity issues
- PanelAlpha plan quota exceeded

**Solutions**:
1. Check **PanelAlpha Admin** for service status
2. Check **Boxi Integration → Event Log** for API errors
3. Verify PanelAlpha plan has available quota
4. Click **"Retry Provisioning"** in order metabox

#### 3. "API Authentication Failed"

**Possible Causes**:
- Invalid PanelAlpha API token
- Token expired or revoked
- Incorrect API URL

**Solutions**:
1. Regenerate PanelAlpha API token
2. Verify API URL is correct (no trailing slash)
3. Click **"Test Connection"** in plugin settings

#### 4. "Product Not Mapped" Warning

**Possible Causes**:
- WooCommerce product not mapped to PanelAlpha plan
- Mapping was deleted

**Solutions**:
1. Go to **Boxi Integration → Settings**
2. Add product mapping in **Product Mappings** section
3. Save settings

### Debug Mode

To enable verbose logging:

1. Edit `wp-config.php`
2. Add before `/* That's all, stop editing! */`:
   ```php
   define('BOXI_DEBUG', true);
   ```
3. Check **Boxi Integration → Event Log** for detailed API request/response logs

⚠️ **Warning**: Debug mode logs may contain sensitive data. Disable after troubleshooting.

## Security Best Practices

### Protect API Credentials

- ✅ API tokens are encrypted in WordPress database
- ✅ Never share PanelAlpha API token
- ✅ Rotate API tokens quarterly
- ✅ Use HTTPS for WordPress and PanelAlpha

### Limit Access

- Only WordPress **Administrators** can access plugin settings
- Review **Event Log** regularly for unauthorized access attempts
- Enable **WordPress two-factor authentication** (recommended)

### Secure Email

- Configure **SPF** and **DKIM** for your domain
- Use established **SMTP service** (not default PHP `mail()`)
- Credentials emails should be sent over **TLS/SSL**

## Performance

### Expected Performance

- **Provisioning Time**: 2-5 minutes (depends on PanelAlpha server load)
- **Email Delivery**: 10-30 seconds
- **API Rate Limit**: 55 requests/minute (configurable)

### Handling High Volume

For **100+ orders/day**:

1. Monitor **queue depth** in Event Log
2. Increase **PanelAlpha server resources** if provisioning slows
3. Consider **load balancing** PanelAlpha instances
4. Contact support for **rate limit increase**

## Support

### Getting Help

1. **Check Event Logs**: **Boxi Integration → Event Log**
2. **Check WooCommerce Logs**: **WooCommerce → Status → Logs**
3. **Check PanelAlpha Logs**: In PanelAlpha admin panel

### Reporting Issues

When reporting issues, include:

- WordPress version
- PHP version
- WooCommerce version
- Error message from Event Log
- Screenshot of order metabox (if applicable)

## Uninstallation

### Deactivate Plugin

1. **Boxi Integration → Settings**
2. **Export configuration** (optional - for backup)
3. **Plugins → Installed Plugins**
4. **Deactivate** Boxi PanelAlpha Integration
5. **Delete** plugin (optional - removes all settings and logs)

⚠️ **Warning**: Deactivating stops automatic provisioning. Existing services in PanelAlpha are **not** affected.

## Changelog

### Version 1.0.0 (Initial Release)
- ✅ Automatic PanelAlpha account creation
- ✅ WordPress hosting provisioning on order completion
- ✅ Credential email delivery
- ✅ Subscription lifecycle management (suspend/unsuspend/cancel)
- ✅ Admin UI with event logging
- ✅ Product-to-plan mapping configuration
- ✅ Retry logic for failed provisions
- ✅ Rate limiting for PanelAlpha API

## License

Proprietary - Copyright © 2025 Boxi Hosting

---

**Need Help?** Contact support@boxi.co.il
