<?php

class Shopfully_Entity_Clickout
{
    private string $clickout;
    private int $pageNumber;
    private string $width;
    private string $height;
    private string $x;
    private string $y;

    /**
     * @return string
     */
    public function getClickout(): string
    {
        return $this->clickout;
    }

    /**
     * @param string $clickout
     */
    public function setClickout(string $clickout): void
    {
        $this->clickout = $clickout;
    }

    /**
     * @return int
     */
    public function getPageNumber(): int
    {
        return $this->pageNumber;
    }

    /**
     * @param int $pageNumber
     */
    public function setPageNumber(int $pageNumber): void
    {
        $this->pageNumber = $pageNumber;
    }

    /**
     * @return string
     */
    public function getWidth(): string
    {
        return $this->width;
    }

    /**
     * @param string $width
     */
    public function setWidth(string $width): void
    {
        $this->width = $width;
    }

    /**
     * @return string
     */
    public function getHeight(): string
    {
        return $this->height;
    }

    /**
     * @param string $height
     */
    public function setHeight(string $height): void
    {
        $this->height = $height;
    }

    /**
     * @return string
     */
    public function getX(): string
    {
        return $this->x;
    }

    /**
     * @param string $x
     */
    public function setX(string $x): void
    {
        $this->x = $x;
    }

    /**
     * @return string
     */
    public function getY(): string
    {
        return $this->y;
    }

    /**
     * @param string $y
     */
    public function setY(string $y): void
    {
        $this->y = $y;
    }
}
