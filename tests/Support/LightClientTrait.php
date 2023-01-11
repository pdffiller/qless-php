<?php

namespace Qless\Tests\Support;

trait LightClientTrait
{
    public function setUp(): void
    {
        parent::setUp();

        $this->client->getLua()->useLightScript();
    }

    public function tearDown(): void
    {
        parent::tearDown();

        $this->client->getLua()->useBaseScript();
    }
}
