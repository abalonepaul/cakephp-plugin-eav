<?php
declare(strict_types=1);

namespace Eav\Test\TestCase\Controller\Component;

use Cake\Cache\Cache;
use Cake\Controller\ComponentRegistry;
use Cake\Controller\Controller;
use Cake\Core\Configure;
use Cake\TestSuite\TestCase;
use Eav\Controller\Component\EavComponent;

class EavComponentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Clear memoized config
        Cache::delete('Eav.eav_config');
    }

    public function testGetDataTypeOptionsFromConfigure(): void
    {
        Configure::write('Eav.defaultTypes', ['string', 'integer', 'uuid']);
        $controller = new Controller(new \Cake\Http\ServerRequest());
        $registry = new ComponentRegistry($controller);
        $comp = new EavComponent($registry);

        $options = $comp->getDataTypeOptions();
        $this->assertArrayHasKey('string', $options);
        $this->assertArrayHasKey('integer', $options);
        $this->assertArrayHasKey('uuid', $options);
    }

    public function testGetAdminPrefixFromCachedConfig(): void
    {
        // Seed component’s per-request cache via Cache::remember path
        Cache::write('Eav.eav_config', ['adminPrefix' => '/admin']);

        $controller = new Controller(new \Cake\Http\ServerRequest());
        $registry = new ComponentRegistry($controller);
        $comp = new EavComponent($registry);

        $this->assertSame('/admin', $comp->getAdminPrefix());
    }
}
