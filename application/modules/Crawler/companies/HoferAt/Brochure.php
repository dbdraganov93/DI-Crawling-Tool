<?php

/*
 * Prospekt Crawler für Hofer AT (ID: 72982)
 */

class Crawler_Company_HoferAt_Brochure extends Crawler_Generic_Company
{
    private const BROCHURE_URL = 'https://www.hofer.at/de/angebote/aktuelle-flugblaetter-und-broschuren.html';
    private const WEEK = 'this';
    private const NEXT_WEEK = 'next';
    private const DATE_FORMAT = 'd.m.Y';

    private Marktjagd_Service_Text_Times $timesService;
    private Marktjagd_Service_Input_Page $pageService;
    
    private string $year;
    private string $weekNr;
    private string $nextWeekNr;

    public function __construct()
    {
        parent::__construct();

        $this->timesService = new Marktjagd_Service_Text_Times();
        $this->pageService = new Marktjagd_Service_Input_Page();
    }

    public function crawl($companyId)
    {
        $this->pageService->open(self::BROCHURE_URL);
        $page = $this->pageService->getPage()->getResponseBody();

        $this->weekNr = $this->timesService->getWeekNr(self::WEEK);
        $this->year = $this->timesService->getWeeksYear(self::WEEK, true);
        $this->nextWeekNr = $this->timesService->getWeekNr(self::NEXT_WEEK);

        $pattern = '#https:\/\/(s7g10.scene7.com\/is\/content\/aldi\/)+([a-zA-Z]+)lipbook_kw' . $this->nextWeekNr . '_' . $this->year . '(?:_\d+)?\/?\b#';
        if (!preg_match_all($pattern, $page, $brochureMatches)) {
            throw new Exception($companyId . ': unable to get any brochures.');
        }

        $brochuresCollection = new Marktjagd_Collection_Api_Brochure();
        foreach (reset($brochureMatches) as $brochureUrl) {
            $brochure = $this->generateBrochure($brochureUrl);
            $brochuresCollection->addElement($brochure);
        }

        return $this->getResponse($brochuresCollection, $companyId);
    }

    private function generateBrochure(string $brochureUrl, string $numberSuffix = ''): Marktjagd_Entity_Api_Brochure
    {
        $eBrochure = new Marktjagd_Entity_Api_Brochure();

        $eBrochure->setTitle('HOFER: Flugblatt')
            ->setUrl($brochureUrl.'.pdf')
            ->setVisibleStart($this->timesService->findDateForWeekday($this->year, $this->weekNr, 'Mi'))
            ->setVisibleEnd(date(self::DATE_FORMAT, strtotime($eBrochure->getVisibleStart() . ' + 8 days')))
            ->setStart($this->timesService->findDateForWeekday($this->year, $this->weekNr, 'Fr'))
            ->setEnd(date(self::DATE_FORMAT, strtotime($eBrochure->getStart() . ' + 6 days')))
            ->setVariety('leaflet')
            ->setBrochureNumber('KW' . $this->weekNr . "_" . $this->nextWeekNr . '_20' . $this->year . $numberSuffix);

        return $eBrochure;
    }
}
