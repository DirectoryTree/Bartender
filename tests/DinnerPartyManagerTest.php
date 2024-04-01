<?php

namespace DirectoryTree\Bartender\Tests;

use stdClass;
use DirectoryTree\Bartender\BartenderManager;
use DirectoryTree\Bartender\Facades\Bartender;

class DinnerPartyManagerTest extends TestCase
{
    public function testItCanRegisterHandlers()
    {
        $manager = new BartenderManager;

        $manager->register('foo', stdClass::class);

        $this->assertEquals(['foo' =>  stdClass::class], $manager->handlers());
    }

    public function testItIsBoundToFacade()
    {
        $this->assertInstanceOf(BartenderManager::class, Bartender::getFacadeRoot());
    }
}