<?php
/**
 * Class Marktjagd_Service_Input_HtmlParser
 */
class Marktjagd_Service_Input_HtmlParser
{
    private DOMDocument $dom;

    public function __construct()
    {
        $this->dom = new DOMDocument();
        libxml_use_internal_errors(true);
    }

    public function parseHtml(string $content): DOMDocument
    {
        $this->dom->loadHTML('<?xml encoding="UTF-8">' . $content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        return $this->dom;
    }
}
