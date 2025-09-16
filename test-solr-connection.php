<?php

/**
 * Test script for SOLR connectivity using GuzzleSolrService
 */

require_once '/var/www/html/lib/base.php';

use OCA\OpenRegister\Service\GuzzleSolrService;
use OCA\OpenRegister\Setup\SolrSetup;

echo "🧪 Testing OpenRegister SOLR Connectivity\n";
echo "==========================================\n\n";

try {
    $container = \OC::$server->getContainer();
    $config = $container->get('OCP\IConfig');
    $logger = $container->get('OCP\ILogger');
    
    // Get SOLR configuration
    $solrConfig = [
        'host' => $config->getAppValue('openregister', 'solr_host', 'master-solr-1'),
        'port' => $config->getAppValue('openregister', 'solr_port', '8983'),
        'scheme' => 'http',
        'path' => '/solr'
    ];
    
    echo "🔧 SOLR Configuration:\n";
    echo "   Host: " . $solrConfig['host'] . "\n";
    echo "   Port: " . $solrConfig['port'] . "\n";
    echo "   Scheme: " . $solrConfig['scheme'] . "\n";
    echo "   Path: " . $solrConfig['path'] . "\n\n";
    
    // Test 1: GuzzleSolrService connectivity test
    echo "📋 Test 1: GuzzleSolrService Connection Test\n";
    echo "---------------------------------------------\n";
    
    $solrService = new GuzzleSolrService($solrConfig, $logger);
    $testResult = $solrService->testConnection();
    
    echo "Result: " . json_encode($testResult, JSON_PRETTY_PRINT) . "\n\n";
    
    // Test 2: SolrSetup connectivity test (our fixed code)
    echo "📋 Test 2: SolrSetup Connectivity Test (Fixed Code)\n";
    echo "---------------------------------------------------\n";
    
    $solrSetup = new SolrSetup($solrConfig, $logger);
    $setupResult = $solrSetup->setupSolr();
    
    if ($setupResult) {
        echo "✅ SOLR Setup: SUCCESS\n";
    } else {
        echo "❌ SOLR Setup: FAILED\n";
    }
    
    echo "\n🎯 Test Summary:\n";
    echo "================\n";
    echo "- GuzzleSolrService test: " . ($testResult['success'] ? "✅ PASS" : "❌ FAIL") . "\n";
    echo "- SolrSetup test: " . ($setupResult ? "✅ PASS" : "❌ FAIL") . "\n";
    
    if ($testResult['success'] && $setupResult) {
        echo "\n🎉 All tests PASSED! The GuzzleHttp Client fix is working correctly.\n";
    } else {
        echo "\n⚠️ Some tests failed. Check the logs for more details.\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error during testing: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
