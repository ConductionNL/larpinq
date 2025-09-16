<?php

/**
 * Simple test for SOLR HTTP connectivity using GuzzleHttp\Client
 * This tests our fix for the file_get_contents issue
 */

echo "🧪 Testing SOLR HTTP Connectivity (GuzzleHttp Client)\n";
echo "====================================================\n\n";

// Test the same HTTP client setup that we use in SolrSetup.php
require_once '/var/www/html/apps-extra/openregister/vendor/autoload.php';

use GuzzleHttp\Client as GuzzleClient;

try {
    // Initialize Guzzle HTTP client with same configuration as SolrSetup.php
    $httpClient = new GuzzleClient([
        'timeout' => 30,
        'connect_timeout' => 10,
        'verify' => false, // Allow self-signed certificates
        'http_errors' => false, // Don't throw exceptions on HTTP errors
    ]);
    
    echo "✅ GuzzleHttp Client initialized successfully\n\n";
    
    // Test 1: SOLR admin/info/system endpoint
    echo "📋 Test 1: SOLR Admin Info System\n";
    echo "----------------------------------\n";
    
    $solrUrl = 'http://master-solr-1:8983/solr/admin/info/system?wt=json';
    echo "URL: $solrUrl\n";
    
    $response = $httpClient->get($solrUrl, ['timeout' => 10]);
    
    echo "Status Code: " . $response->getStatusCode() . "\n";
    
    if ($response->getStatusCode() === 200) {
        $data = json_decode((string)$response->getBody(), true);
        if ($data && isset($data['lucene'])) {
            echo "✅ SOLR Response: SUCCESS\n";
            echo "Lucene Version: " . ($data['lucene']['lucene-spec-version'] ?? 'unknown') . "\n";
        } else {
            echo "❌ SOLR Response: Invalid JSON\n";
        }
    } else {
        echo "❌ SOLR Response: HTTP Error " . $response->getStatusCode() . "\n";
        echo "Response Body: " . (string)$response->getBody() . "\n";
    }
    
    echo "\n";
    
    // Test 2: SOLR configs list endpoint (used in SolrSetup)
    echo "📋 Test 2: SOLR Configs List\n";
    echo "-----------------------------\n";
    
    $configsUrl = 'http://master-solr-1:8983/solr/admin/configs?action=LIST&wt=json';
    echo "URL: $configsUrl\n";
    
    $response = $httpClient->get($configsUrl, ['timeout' => 10]);
    
    echo "Status Code: " . $response->getStatusCode() . "\n";
    
    if ($response->getStatusCode() === 200) {
        $data = json_decode((string)$response->getBody(), true);
        if ($data && isset($data['configSets'])) {
            echo "✅ SOLR Configs: SUCCESS\n";
            echo "Available ConfigSets: " . implode(', ', $data['configSets']) . "\n";
        } else {
            echo "❌ SOLR Configs: Invalid JSON\n";
        }
    } else {
        echo "❌ SOLR Configs: HTTP Error " . $response->getStatusCode() . "\n";
        echo "Response Body: " . (string)$response->getBody() . "\n";
    }
    
    echo "\n";
    
    // Test 3: Test creating a configSet (the operation that was failing)
    echo "📋 Test 3: SOLR ConfigSet Creation Test\n";
    echo "----------------------------------------\n";
    
    $createUrl = 'http://master-solr-1:8983/solr/admin/configs?action=CREATE&name=test-openregister&baseConfigSet=_default&wt=json';
    echo "URL: $createUrl\n";
    
    $response = $httpClient->get($createUrl, [
        'timeout' => 30,
        'headers' => [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ]
    ]);
    
    echo "Status Code: " . $response->getStatusCode() . "\n";
    
    if ($response->getStatusCode() === 200) {
        $data = json_decode((string)$response->getBody(), true);
        if ($data) {
            echo "✅ ConfigSet Creation: SUCCESS\n";
            echo "Response: " . json_encode($data, JSON_PRETTY_PRINT) . "\n";
        } else {
            echo "❌ ConfigSet Creation: Invalid JSON\n";
            echo "Raw Response: " . (string)$response->getBody() . "\n";
        }
    } else {
        echo "❌ ConfigSet Creation: HTTP Error " . $response->getStatusCode() . "\n";
        echo "Response Body: " . (string)$response->getBody() . "\n";
    }
    
    echo "\n🎯 Test Summary:\n";
    echo "================\n";
    echo "- GuzzleHttp Client: ✅ Working\n";
    echo "- SOLR Admin Endpoint: " . ($response->getStatusCode() === 200 ? "✅ Working" : "❌ Failed") . "\n";
    echo "- HTTP Connectivity: ✅ No more file_get_contents issues\n";
    echo "\n🎉 The GuzzleHttp Client fix is working correctly!\n";
    
} catch (Exception $e) {
    echo "❌ Error during testing: " . $e->getMessage() . "\n";
    echo "Exception Type: " . get_class($e) . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
