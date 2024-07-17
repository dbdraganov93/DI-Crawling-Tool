<?php

class Marktjagd_Entity_Pinterest_Pin
{
    /**
     * Pattern for the map search term
     */
    protected const DEFAULT_SEARCH_TERM_PATTERN = 'nearby %s';

    /**
     * Used for the tab in the campaign spreadsheet and the folder on AWS
     */
    protected string $companyName;

    /**
     * The ID of the company
     */
    protected int $companyId;

    /**
     * A unique identifier for the pin
     */
    protected string $pinNumber;

    /**
     * URL to a jpeg or jpg image
     */
    protected string $coverURL = '';

    /**
     * Clickout URL of the cover image
     */
    protected string $coverClickoutURL = '';

    /**
     * The text of the CTA button
     */
    protected string $ctaText = '';

    /**
     * The URL destination of the CTA button
     */
    protected string $ctaURL = '';

    /**
     * The order of the categories separated by commas like "cat1, cat2, cat3"
     */
    protected string $categoryOrder = '';

    /**
     * The search term for the map
     */
    protected string $searchTerm;

    public function __construct(string $companyName, int $companyId, string $pinNumber)
    {
        $this->companyName = $companyName;
        $this->companyId = $companyId;
        $this->pinNumber = $pinNumber;
        $this->searchTerm = sprintf(self::DEFAULT_SEARCH_TERM_PATTERN, $companyName);
    }


    public function getCompanyName(): string
    {
        return $this->companyName;
    }

    public function getCompanyId(): int
    {
        return $this->companyId;
    }

    public function getPinNumber(): string
    {
        return $this->pinNumber;
    }

    public function setCoverURL(string $newCoverImageUrl): Marktjagd_Entity_Pinterest_Pin
    {
        $this->coverURL = $newCoverImageUrl;
        return $this;
    }

    public function getCoverUrl(): string
    {
        return $this->coverURL;
    }

    public function setCoverClickout(string $newClickoutUrl): Marktjagd_Entity_Pinterest_Pin
    {
        $this->coverClickoutURL = $newClickoutUrl;
        return $this;
    }

    public function getCoverClickout(): string
    {
        return $this->coverClickoutURL;
    }

    public function setCTAText(string $newCTAText): Marktjagd_Entity_Pinterest_Pin
    {
        $this->ctaText = $newCTAText;
        return $this;
    }

    public function getCTAText(): string
    {
        return $this->ctaText;
    }

    public function setCTAUrl(string $newCTAUrl): Marktjagd_Entity_Pinterest_Pin
    {
        $this->ctaURL = $newCTAUrl;
        return $this;
    }

    public function getCTAUrl(): string
    {
        return $this->ctaURL;
    }

    public function setSearchTerm(string $newSearchTerm): Marktjagd_Entity_Pinterest_Pin
    {
        $this->searchTerm = $newSearchTerm;
        return $this;
    }

    public function getSearchTerm(): string
    {
        return $this->searchTerm;
    }

    public function setCategoryOrder(string $categoryOrder): Marktjagd_Entity_Pinterest_Pin
    {
        $this->categoryOrder = $categoryOrder;
        return $this;
    }

    public function getCategoryOrder(): string
    {
        return $this->categoryOrder;
    }
}
