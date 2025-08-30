<?php
declare(strict_types=1);

/**
 * Simple Test Runner for OpenWishlist
 * Lightweight alternative to PHPUnit for basic API testing
 */
final class TestRunner
{
    private array $tests = [];
    private int $passed = 0;
    private int $failed = 0;
    private array $failures = [];

    public function addTest(string $name, callable $test): void
    {
        $this->tests[$name] = $test;
    }

    public function run(): void
    {
        echo "🧪 OpenWishlist API Test Suite\n";
        echo str_repeat('=', 50) . "\n\n";

        foreach ($this->tests as $name => $test) {
            try {
                echo "Running: $name ... ";
                $test();
                echo "✅ PASS\n";
                $this->passed++;
            } catch (AssertionFailedException $e) {
                echo "❌ FAIL\n";
                echo "   └─ " . $e->getMessage() . "\n";
                $this->failed++;
                $this->failures[] = "$name: " . $e->getMessage();
            } catch (Throwable $e) {
                echo "💥 ERROR\n";
                echo "   └─ " . $e->getMessage() . "\n";
                $this->failed++;
                $this->failures[] = "$name: ERROR - " . $e->getMessage();
            }
        }

        echo "\n" . str_repeat('=', 50) . "\n";
        echo "Results: {$this->passed} passed, {$this->failed} failed\n";

        if (!empty($this->failures)) {
            echo "\nFailures:\n";
            foreach ($this->failures as $failure) {
                echo "• $failure\n";
            }
            exit(1);
        }

        echo "\n🎉 All tests passed!\n";
    }
}

final class AssertionFailedException extends Exception {}

final class Assert
{
    public static function equals($expected, $actual, string $message = ''): void
    {
        if ($expected !== $actual) {
            $msg = $message ?: "Expected " . json_encode($expected) . ", got " . json_encode($actual);
            throw new AssertionFailedException($msg);
        }
    }

    public static function true($value, string $message = ''): void
    {
        if ($value !== true) {
            $msg = $message ?: "Expected true, got " . json_encode($value);
            throw new AssertionFailedException($msg);
        }
    }

    public static function false($value, string $message = ''): void
    {
        if ($value !== false) {
            $msg = $message ?: "Expected false, got " . json_encode($value);
            throw new AssertionFailedException($msg);
        }
    }

    public static function null($value, string $message = ''): void
    {
        if ($value !== null) {
            $msg = $message ?: "Expected null, got " . json_encode($value);
            throw new AssertionFailedException($msg);
        }
    }

    public static function notNull($value, string $message = ''): void
    {
        if ($value === null) {
            $msg = $message ?: "Expected non-null value";
            throw new AssertionFailedException($msg);
        }
    }

    public static function arrayHasKey(string $key, array $array, string $message = ''): void
    {
        if (!array_key_exists($key, $array)) {
            $msg = $message ?: "Array does not have key '$key'";
            throw new AssertionFailedException($msg);
        }
    }

    public static function contains($needle, $haystack, string $message = ''): void
    {
        if (is_array($haystack)) {
            if (!in_array($needle, $haystack, true)) {
                $msg = $message ?: "Array does not contain " . json_encode($needle);
                throw new AssertionFailedException($msg);
            }
        } else {
            if (strpos((string)$haystack, (string)$needle) === false) {
                $msg = $message ?: "String does not contain '$needle'";
                throw new AssertionFailedException($msg);
            }
        }
    }
}