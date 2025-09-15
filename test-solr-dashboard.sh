#!/bin/bash

echo "🧪 SOLR Dashboard Error Handling Test Script"
echo "=============================================="

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo ""
echo "📋 Test Plan:"
echo "1. Test with SOLR running (should work normally)"
echo "2. Stop SOLR service (should show error state)"  
echo "3. Test retry functionality"
echo "4. Restart SOLR (should recover)"
echo ""

# Function to check SOLR container status
check_solr_status() {
    cd ~/nextcloud-docker-dev
    if docker-compose ps master-solr-1 | grep -q "Up"; then
        echo -e "${GREEN}✅ SOLR is running${NC}"
        return 0
    else
        echo -e "${RED}❌ SOLR is stopped${NC}"
        return 1
    fi
}

echo "🔍 Current SOLR Status:"
check_solr_status

echo ""
echo "🌐 Access the dashboard at: http://nextcloud.local/settings/admin/openregister"
echo ""
echo "📝 Manual Test Steps:"
echo "1. Open the dashboard URL above"
echo "2. Navigate to SOLR Dashboard section"
echo "3. Run: ./test-solr-dashboard.sh stop"
echo "4. Refresh dashboard - should show error state"
echo "5. Test retry button"
echo "6. Run: ./test-solr-dashboard.sh start"
echo "7. Click retry - should show dashboard"
echo ""

# Handle command line arguments
case "${1}" in
    "stop")
        echo -e "${YELLOW}🛑 Stopping SOLR service...${NC}"
        cd ~/nextcloud-docker-dev
        docker-compose stop master-solr-1
        echo "✅ SOLR stopped. Refresh your dashboard to see error handling."
        ;;
    "start")
        echo -e "${GREEN}▶️ Starting SOLR service...${NC}"
        cd ~/nextcloud-docker-dev
        docker-compose start master-solr-1
        echo "✅ SOLR started. Click retry in dashboard to see recovery."
        ;;
    "status")
        check_solr_status
        ;;
    *)
        echo "Usage: $0 {stop|start|status}"
        echo "  stop   - Stop SOLR service to test error handling"
        echo "  start  - Start SOLR service to test recovery"
        echo "  status - Check SOLR container status"
        ;;
esac
