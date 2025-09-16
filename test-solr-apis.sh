#!/bin/bash

echo "🧪 Testing OpenRegister SOLR APIs"
echo "================================="

# Test SOLR connectivity test API
echo ""
echo "📋 Testing SOLR Connection Test API..."
echo "---------------------------------------"
docker exec -it master-nextcloud-1 bash -c "
cd /var/www/html/apps-extra/openregister && 
php -r '
require_once \"../../lib/base.php\";
\OC::handleRequest();
' <<< 'POST /index.php/apps/openregister/api/settings/solr/test HTTP/1.1
Host: localhost
Content-Type: application/json
Content-Length: 2

{}'
"

echo ""
echo "📋 Testing SOLR Setup API..."  
echo "-----------------------------"
docker exec -it master-nextcloud-1 bash -c "
cd /var/www/html/apps-extra/openregister &&
php -r '
require_once \"../../lib/base.php\";
\OC::handleRequest();
' <<< 'POST /index.php/apps/openregister/api/solr/setup HTTP/1.1
Host: localhost
Content-Type: application/json
Content-Length: 2

{}'
"

echo ""
echo "✅ API tests completed!"
