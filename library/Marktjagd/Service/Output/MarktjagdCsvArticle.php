<?php

/**
 * Service zum Generieren der Marktjagd-CSV
 */
class Marktjagd_Service_Output_MarktjagdCsvArticle extends Marktjagd_Service_Output_MarktjagdCsvAbstract {

    public function __construct($companyId, $modus = 'w') {
        $this->_type = 'articles';
        parent::__construct($companyId, $modus);
    }

    /**
     * @param Marktjagd_Collection_Api_Article $collection
     * @return string
     */
    public function generateContent($collection) {
        $elements = $collection->getElements();
        $headline = $collection->getHeadline();

        $csvString = $headline . "\n";
        foreach ($elements as $element) {
            $csvString .= $this->generateContentLine($element);
        }

        return $csvString;
    }

    /**
     * @param Marktjagd_Entity_Api_Article $element
     * @return string
     */
    public function generateContentLine($element) {
        $csvLine = '"' . $element->getArticleNumber() . '";'
                . '"' . str_replace('"', '""', $element->getTitle()) . '";'
                . '"' . str_replace('"', '""', $element->getPrice()) . '";'
                . '"' . str_replace('"', '""', $element->getPriceIsVariable()) . '";'
                . '"' . str_replace('"', '""', $element->getText()) . '";'
                . '"' . str_replace('"', '""', $element->getEan()) . '";'
                . '"' . str_replace('"', '""', $element->getManufacturer()) . '";'
                . '"' . str_replace('"', '""', $element->getArticleNumberManufacturer()) . '";'
                . '"' . str_replace('"', '""', $element->getSuggestedRetailPrice()) . '";'
                . '"' . str_replace('"', '""', $element->getTrademark()) . '";'
                . '"' . str_replace('"', '""', $element->getTags()) . '";'
                . '"' . str_replace('"', '""', $element->getColor()) . '";'
                . '"' . str_replace('"', '""', $element->getSize()) . '";'
                . '"' . str_replace('"', '""', $element->getAmount()) . '";'
                . '"' . str_replace('"', '""', $element->getStart()) . '";'
                . '"' . str_replace('"', '""', $element->getEnd()) . '";'
                . '"' . str_replace('"', '""', $element->getVisibleStart()) . '";'
                . '"' . str_replace('"', '""', $element->getVisibleEnd()) . '";'
                . '"' . str_replace('"', '""', $element->getUrl()) . '";'
                . '"' . str_replace('"', '""', $element->getShipping()) . '";'
                . '"' . str_replace('"', '""', $element->getImage()) . '";'
                . '"' . str_replace('"', '""', $element->getStoreNumber()) . '";'
                . '"' . str_replace('"', '""', $element->getDistribution()) . '";'
                . '"' . str_replace('"', '""', $element->getNational()) . '";'
                . '"' . str_replace('"', '""', $element->getLanguageCode()) . '";'
                . '"' . str_replace('"', '""', $element->getTitleDe()) . '";'
                . '"' . str_replace('"', '""', $element->getTitleFr()) . '";'
                . '"' . str_replace('"', '""', $element->getTitleIt()) . '";'
                . '"' . str_replace('"', '""', $element->getTextDe()) . '";'
                . '"' . str_replace('"', '""', $element->getTextFr()) . '";'
                . '"' . str_replace('"', '""', $element->getTextIt()) . '";'
                . '"' . str_replace('"', '""', $element->getSizeDe()) . '";'
                . '"' . str_replace('"', '""', $element->getSizeFr()) . '";'
                . '"' . str_replace('"', '""', $element->getSizeIt()) . '";'
                . '"' . str_replace('"', '""', $element->getAmountDe()) . '";'
                . '"' . str_replace('"', '""', $element->getAmountFr()) . '";'
                . '"' . str_replace('"', '""', $element->getAmountIt()) . '";'
                . '"' . str_replace('"', '""', $element->getColorDe()) . '";'
                . '"' . str_replace('"', '""', $element->getColorFr()) . '";'
                . '"' . str_replace('"', '""', $element->getColorIt()) . '";'
                . '"' . str_replace('"', '""', $element->getShippingDe()) . '";'
                . '"' . str_replace('"', '""', $element->getShippingFr()) . '";'
                . '"' . str_replace('"', '""', $element->getShippingIt()) . '";'
                . '"' . str_replace('"', '""', $element->getUrlDe()) . '";'
                . '"' . str_replace('"', '""', $element->getUrlFr()) . '";'
                . '"' . str_replace('"', '""', $element->getUrlIt()) . '";'
                . $element->getAdditionalProperties()
                . "\n";

        return $csvLine;
    }
}
