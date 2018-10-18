<?php

namespace Qless\Events\User\Queue;

class AfterEnqueue extends AbstractQueueEvent
{
    private $jid;
    private $data;
    private $className;

    public static function getHappening(): string
    {
        return 'afterEnqueue';
    }

    /** @inheritdoc */
    public function __construct($source, string $jid, array $data, string $className)
    {
        parent::__construct($source);
        $this->jid = $jid;
        $this->data = $data;
        $this->className = $className;
    }

    public function getJid(): string
    {
        return $this->jid;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getClassName(): string
    {
        return $this->className;
    }
}
