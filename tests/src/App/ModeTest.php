<?php

namespace Friendica\Test\src\App;

use Friendica\App\Mode;
use Friendica\Core\Config\Cache\ConfigCache;
use Friendica\Database\Database;
use Friendica\Test\MockedTest;
use Mockery\MockInterface;

class ModeTest extends MockedTest
{
	/** @var Database|MockInterface */
	private $dba;
	/** @var ConfigCache|MockInterface */
	private $configCache;

	public function setUp()
	{
		parent::setUp();

		$this->dba         = \Mockery::mock(Database::class);
		$this->configCache = \Mockery::mock(ConfigCache::class);

	}

	public function testItEmpty()
	{
		$mode = new Mode($this->dba, $this->configCache);
		$this->assertTrue($mode->isInstall());
		$this->assertFalse($mode->isNormal());
	}

	public function testWithoutConfig()
	{
		$this->configCache->shouldReceive('get')->with('database', \Mockery::any())->andReturn(null)->once();

		$mode = new Mode($this->dba, $this->configCache);

		$mode->determine();

		$this->assertTrue($mode->isInstall());
		$this->assertFalse($mode->isNormal());

		$this->assertFalse($mode->has(Mode::LOCALCONFIGPRESENT));
	}

	public function testWithoutDatabase()
	{
		$this->configCache->shouldReceive('get')->with('database', \Mockery::any())->andReturn(true);
		$this->dba->shouldReceive('connected')->andReturn(false)->once();

		$mode = new Mode($this->dba, $this->configCache);

		$mode->determine();

		$this->assertFalse($mode->isNormal());
		$this->assertTrue($mode->isInstall());

		$this->assertTrue($mode->has(Mode::LOCALCONFIGPRESENT));
		$this->assertFalse($mode->has(Mode::DBAVAILABLE));
	}

	public function testWithoutDatabaseSetup()
	{
		$this->configCache->shouldReceive('get')->with('database', \Mockery::any())->andReturn(true);
		$this->dba->shouldReceive('connected')->andReturn(true)->once();
		$this->dba->shouldReceive('fetchFirst')
		          ->with('SHOW TABLES LIKE \'config\'')
		          ->andReturn(false)->once();

		$mode = new Mode($this->dba, $this->configCache);

		$mode->determine();

		$this->assertFalse($mode->isNormal());
		$this->assertTrue($mode->isInstall());

		$this->assertTrue($mode->has(Mode::LOCALCONFIGPRESENT));
	}

	public function testWithMaintenanceModeCache()
	{
		$this->configCache->shouldReceive('get')->with('database', \Mockery::any())->andReturn(true);
		$this->dba->shouldReceive('connected')->andReturn(true)->once();
		$this->dba->shouldReceive('fetchFirst')
		          ->with('SHOW TABLES LIKE \'config\'')
		          ->andReturn(true)->once();
		$this->configCache->shouldReceive('get')->with('system', 'maintenance')->andReturn(true)->once();

		$mode = new Mode($this->dba, $this->configCache);
		$mode->determine();

		$this->assertFalse($mode->isNormal());
		$this->assertFalse($mode->isInstall());

		$this->assertTrue($mode->has(Mode::DBCONFIGAVAILABLE));
		$this->assertFalse($mode->has(Mode::MAINTENANCEDISABLED));
	}

	public function testWithMaintenanceModeDB()
	{
		$this->configCache->shouldReceive('get')->with('database', \Mockery::any())->andReturn(true);
		$this->dba->shouldReceive('connected')->andReturn(true)->once();
		$this->dba->shouldReceive('fetchFirst')
		          ->with('SHOW TABLES LIKE \'config\'')
		          ->andReturn(true)->once();
		$this->configCache->shouldReceive('get')->with('system', 'maintenance')->andReturn(false)->once();
		$this->dba->shouldReceive('selectFirst')
		          ->with('config', ['v'], ['cat' => 'system', 'k' => 'maintenance'])
		          ->andReturn(['v' => '1'])->once();

		$mode = new Mode($this->dba, $this->configCache);
		$mode->determine();

		$this->assertFalse($mode->isNormal());
		$this->assertFalse($mode->isInstall());

		$this->assertTrue($mode->has(Mode::DBCONFIGAVAILABLE));
		$this->assertFalse($mode->has(Mode::MAINTENANCEDISABLED));
	}

	public function testNormalMode()
	{
		$this->configCache->shouldReceive('get')->with('database', \Mockery::any())->andReturn(true);
		$this->dba->shouldReceive('connected')->andReturn(true)->once();
		$this->dba->shouldReceive('fetchFirst')
		          ->with('SHOW TABLES LIKE \'config\'')
		          ->andReturn(true)->once();
		$this->configCache->shouldReceive('get')->with('system', 'maintenance')->andReturn(false)->once();
		$this->dba->shouldReceive('selectFirst')
		          ->with('config', ['v'], ['cat' => 'system', 'k' => 'maintenance'])
		          ->andReturn(['v' => '0'])->once();

		$mode = new Mode($this->dba, $this->configCache);
		$mode->determine();

		$this->assertTrue($mode->isNormal());
		$this->assertFalse($mode->isInstall());

		$this->assertTrue($mode->has(Mode::DBCONFIGAVAILABLE));
		$this->assertTrue($mode->has(Mode::MAINTENANCEDISABLED));
	}
}
