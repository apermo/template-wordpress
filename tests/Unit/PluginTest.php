<?php

declare(strict_types=1);

namespace Plugin_Name\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Plugin_Name\Plugin;

/**
 * Tests for the Plugin class.
 */
class PluginTest extends TestCase {

	/**
	 * Verify the Plugin class exists and has an init method.
	 *
	 * @return void
	 */
	public function test_init_method_exists(): void {
		$this->assertTrue( method_exists( Plugin::class, 'init' ) );
	}
}
