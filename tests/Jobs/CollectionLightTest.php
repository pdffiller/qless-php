<?php

namespace Qless\Tests\Jobs;

use Qless\Exceptions\UnsupportedMethodException;
use Qless\Tests\Support\LightClientTrait;

class CollectionLightTest extends CollectionTest
{
    use LightClientTrait;

    /**
     * @test
     */
    public function shouldGetTaggedJobs(): void
    {
        $this->expectException(UnsupportedMethodException::class);

        parent::shouldGetTaggedJobs();
    }

    public function testTagsList(): void
    {
        $this->expectException(UnsupportedMethodException::class);

        parent::testTagsList();
    }
}
