#!/bin/bash
# Boxi PanelAlpha Integration - Test Environment Manager
# This script helps you manage the Docker test environment

set -e

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Print colored message
print_message() {
    echo -e "${GREEN}[Boxi Test]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[Warning]${NC} $1"
}

print_error() {
    echo -e "${RED}[Error]${NC} $1"
}

# Check if Docker is running
check_docker() {
    if ! docker info > /dev/null 2>&1; then
        print_error "Docker is not running. Please start Docker and try again."
        exit 1
    fi
}

# Start the test environment
start_environment() {
    print_message "Starting WordPress test environment..."

    check_docker

    # Build and start containers
    docker-compose up -d

    print_message "Waiting for services to be ready..."
    sleep 10

    # Wait for WordPress to be accessible
    print_message "Waiting for WordPress to finish setup..."
    max_attempts=30
    attempt=0

    while [ $attempt -lt $max_attempts ]; do
        if curl -s http://localhost:8080 > /dev/null 2>&1; then
            print_message "WordPress is ready!"
            break
        fi
        sleep 2
        attempt=$((attempt + 1))
    done

    if [ $attempt -eq $max_attempts ]; then
        print_warning "WordPress took longer than expected to start."
    fi

    echo ""
    echo "=========================================="
    echo "Test Environment Started Successfully!"
    echo "=========================================="
    echo ""
    echo "Access URLs:"
    echo "  WordPress Admin: http://localhost:8080/wp-admin/"
    echo "  Username: admin"
    echo "  Password: admin123"
    echo ""
    echo "  phpMyAdmin: http://localhost:8081/"
    echo ""
    echo "Next steps:"
    echo "1. Wait 30-60 seconds for WordPress setup to complete"
    echo "2. Open http://localhost:8080/wp-admin/ in your browser"
    echo "3. Login with admin / admin123"
    echo "4. Navigate to Boxi Integration → Settings"
    echo "5. Configure PanelAlpha API credentials"
    echo ""
}

# Stop the test environment
stop_environment() {
    print_message "Stopping WordPress test environment..."
    docker-compose down
    print_message "Environment stopped."
}

# Restart the test environment
restart_environment() {
    print_message "Restarting WordPress test environment..."
    stop_environment
    sleep 2
    start_environment
}

# Clean all data (WARNING: This deletes all data!)
clean_environment() {
    print_warning "This will DELETE all WordPress data, database, and volumes!"
    read -p "Are you sure? (yes/no): " confirm

    if [ "$confirm" != "yes" ]; then
        print_message "Cancelled."
        exit 0
    fi

    print_message "Cleaning test environment..."
    docker-compose down -v
    print_message "All data cleaned. Run './test-environment.sh start' to create fresh environment."
}

# Show logs
show_logs() {
    service=${1:-wordpress}
    print_message "Showing logs for $service (Ctrl+C to exit)..."
    docker-compose logs -f "$service"
}

# Show status
show_status() {
    print_message "Service Status:"
    docker-compose ps

    echo ""
    if curl -s http://localhost:8080 > /dev/null 2>&1; then
        print_message "WordPress is accessible at http://localhost:8080"
    else
        print_warning "WordPress is not accessible yet"
    fi
}

# Run WP-CLI commands
run_wpcli() {
    print_message "Running WP-CLI command: $@"
    docker-compose run --rm wpcli wp "$@" --allow-root --path=/var/www/html
}

# Check plugin activation
check_plugin() {
    print_message "Checking Boxi PanelAlpha Integration plugin status..."
    docker-compose run --rm wpcli wp plugin list --allow-root --path=/var/www/html | grep boxi-panelalpha
}

# Show help
show_help() {
    echo "Boxi PanelAlpha Integration - Test Environment Manager"
    echo ""
    echo "Usage: ./test-environment.sh [command]"
    echo ""
    echo "Commands:"
    echo "  start       - Start the test environment"
    echo "  stop        - Stop the test environment"
    echo "  restart     - Restart the test environment"
    echo "  clean       - Delete all data and start fresh (WARNING: destructive!)"
    echo "  status      - Show status of all services"
    echo "  logs [svc]  - Show logs (default: wordpress, options: db, wpcli, phpmyadmin)"
    echo "  wp <cmd>    - Run WP-CLI command"
    echo "  check       - Check plugin activation status"
    echo "  help        - Show this help message"
    echo ""
    echo "Examples:"
    echo "  ./test-environment.sh start"
    echo "  ./test-environment.sh logs wordpress"
    echo "  ./test-environment.sh wp plugin list"
    echo "  ./test-environment.sh wp user list"
    echo ""
}

# Main command dispatcher
case "${1:-help}" in
    start)
        start_environment
        ;;
    stop)
        stop_environment
        ;;
    restart)
        restart_environment
        ;;
    clean)
        clean_environment
        ;;
    status)
        show_status
        ;;
    logs)
        show_logs "${2:-wordpress}"
        ;;
    wp)
        shift
        run_wpcli "$@"
        ;;
    check)
        check_plugin
        ;;
    help|--help|-h)
        show_help
        ;;
    *)
        print_error "Unknown command: $1"
        echo ""
        show_help
        exit 1
        ;;
esac
