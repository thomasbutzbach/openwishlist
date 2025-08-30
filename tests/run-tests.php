#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * OpenWishlist API Test Suite Runner
 * 
 * Usage: php tests/run-tests.php [test-class-name]
 * 
 * Examples:
 *   php tests/run-tests.php                    # Run all tests
 *   php tests/run-tests.php AuthApiTest        # Run only auth tests
 *   php tests/run-tests.php WishlistApiTest    # Run only wishlist tests
 */

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/AuthApiTest.php';
require_once __DIR__ . '/WishlistApiTest.php';
require_once __DIR__ . '/WishApiTest.php';
require_once __DIR__ . '/PublicApiTest.php';

function getTestMethods(object $testInstance): array
{
    $reflection = new ReflectionClass($testInstance);
    $methods = [];
    
    foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        if (str_starts_with($method->getName(), 'test')) {
            $methods[] = $method->getName();
        }
    }
    
    return $methods;
}

function addTestClass(TestRunner $runner, string $className): void
{
    $testInstance = new $className();
    $methods = getTestMethods($testInstance);
    
    foreach ($methods as $methodName) {
        $testName = $className . '::' . $methodName;
        $runner->addTest($testName, function() use ($testInstance, $methodName) {
            // Create fresh instance for each test to avoid state pollution
            $freshInstance = new (get_class($testInstance))();
            try {
                $freshInstance->$methodName();
            } finally {
                // Always cleanup, even if test fails
                $freshInstance->cleanup();
            }
        });
    }
}

function main(): void
{
    $targetTest = $argv[1] ?? null;
    
    echo "🚀 Starting OpenWishlist API Test Suite\n";
    
    // Check if server is running
    $healthCheck = @file_get_contents('http://127.0.0.1:8080/health');
    if ($healthCheck === false) {
        echo "❌ ERROR: OpenWishlist server is not running on http://127.0.0.1:8080\n";
        echo "Please start the server with: composer start\n";
        exit(1);
    }
    
    $runner = new TestRunner();
    
    // Available test classes
    $testClasses = [
        'AuthApiTest' => AuthApiTest::class,
        'WishlistApiTest' => WishlistApiTest::class, 
        'WishApiTest' => WishApiTest::class,
        'PublicApiTest' => PublicApiTest::class
    ];
    
    if ($targetTest) {
        if (!isset($testClasses[$targetTest])) {
            echo "❌ ERROR: Test class '$targetTest' not found.\n";
            echo "Available test classes:\n";
            foreach (array_keys($testClasses) as $className) {
                echo "  - $className\n";
            }
            exit(1);
        }
        
        echo "Running only: $targetTest\n\n";
        addTestClass($runner, $testClasses[$targetTest]);
    } else {
        echo "Running all test classes\n\n";
        foreach ($testClasses as $className) {
            addTestClass($runner, $className);
        }
    }
    
    $runner->run();
}

// Handle CLI execution
if (php_sapi_name() === 'cli') {
    main();
} else {
    echo "This script must be run from the command line.\n";
    exit(1);
}