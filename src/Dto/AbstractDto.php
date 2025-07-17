<?php

namespace App\Dto;

abstract class AbstractDto
{
    public static function fromArray(array $data): static
    {
        $instance = new static();

        foreach ($data as $key => $value) {
            $method = 'set' . ucfirst($key);
            if (method_exists($instance, $method)) {
                $instance->$method($value);
            }
        }

        return $instance;
    }

    abstract public function toArray(): array;
}
