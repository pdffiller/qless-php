<?php

namespace Qless\Jobs\Reservers;

class DefaultReserver extends AbstractReserver implements ReserverInterface
{
    /**
     * {@inheritdoc}
     *
     * @var string
     */
    const TYPE_DESCRIPTION = 'default';
}
