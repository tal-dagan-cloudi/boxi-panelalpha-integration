# Boxi PanelAlpha Integration - Quick Start

## 🚀 5-Minute Setup

### Start Test Environment

```bash
cd /Users/tald/Projects/Boxi/boxi-panelalpha-integration
./test-environment.sh start
```

**Wait 30-60 seconds**, then open: **http://localhost:8080/wp-admin/**

### Login Credentials

| Service | URL | Username | Password |
|---------|-----|----------|----------|
| WordPress Admin | http://localhost:8080/wp-admin/ | `admin` | `admin123` |
| phpMyAdmin | http://localhost:8081/ | `wordpress` | `wordpress` |
| Test Customer | - | `testcustomer` | `customer123` |

### Configure Plugin (First Time Only)

1. **Go to:** Boxi Integration → Settings → API Credentials

2. **Enter:**
   - API URL: `https://panel.boxi.co.il:8443/`
   - API Token: `1|zfHscGuXRbGWhKKZR28WWSuD9rHZPcG6KpvxugXS`

3. **Click:** Test Connection ✓

4. **Click:** Save Credentials

5. **Go to:** Product Mappings tab

6. **Map products** to PanelAlpha plans (ID: 1, 2, 3)

---

## ✅ Test Order Provisioning

### Create Test Order

1. **WooCommerce → Orders → Add New**
2. **Add product:** Shared Hosting - Basic
3. **Customer details:**
   - Email: `test@example.com`
   - Name: Test Customer
4. **Add custom field:**
   - Key: `_domain`
   - Value: `test-site.com`
5. **Status:** Completed
6. **Click:** Create

### Verify Provisioning

1. **Scroll to:** Boxi PanelAlpha Integration metabox
2. **Status:** Should show "Provisioning" → "Active" (1-5 min)
3. **Click:** Reveal Credentials
4. **Check:** Event Logs page for detailed workflow

---

## 🛠️ Useful Commands

```bash
# View all commands
./test-environment.sh help

# Check service status
./test-environment.sh status

# View WordPress logs
./test-environment.sh logs wordpress

# Stop environment
./test-environment.sh stop

# Start fresh (deletes all data!)
./test-environment.sh clean
./test-environment.sh start

# Run WP-CLI commands
./test-environment.sh wp plugin list
./test-environment.sh wp post list --post_type=shop_order
```

---

## 📊 What's Included

- ✅ WordPress 6.x with WooCommerce 8.x
- ✅ MySQL 8.0 database
- ✅ phpMyAdmin for database inspection
- ✅ Boxi PanelAlpha Integration plugin (auto-activated)
- ✅ 3 test products (Shared, VPS, Dedicated)
- ✅ Test customer account
- ✅ Full provisioning workflow ready to test

---

## 📖 Full Documentation

- **Complete Testing Guide:** `TESTING_GUIDE.md`
- **Implementation Status:** `IMPLEMENTATION_STATUS.md`
- **Plugin README:** `README.md`

---

## 🐛 Troubleshooting

| Problem | Solution |
|---------|----------|
| Port 8080 already in use | Stop other services or edit `docker-compose.yml` |
| WordPress not loading | Wait 60 seconds, check `./test-environment.sh status` |
| Plugin not active | Run `./test-environment.sh wp plugin activate boxi-panelalpha-integration` |
| Database errors | Run `./test-environment.sh restart` |

---

**Ready to test!** 🎉

For detailed testing scenarios, see `TESTING_GUIDE.md`
