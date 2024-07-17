<?php
/**
 * Beinhaltet Funktionen zum Prüfen / Umwandeln von Urls
 */
class Marktjagd_Service_Text_Url
{
    /**
     * Prüft, ob eine URL eine interne URL ist
     *
     * @param $url
     * @return bool
     */
    public function isInternalUrl($url)
    {
        if (preg_match('#\/opt\/crawler\/framework\/#', $url)
            || preg_match('#https\:\/\/gui\.di\.marktjagd\.de#', $url)
        ) {
            return true;
        }

        return false;
    }

    /**
     * Konvertiert eine interne URL in einen absoluten Pfad
     *
     * @param $url
     * @return string
     */
    public function convertInternalUrlToAbsolutePath($url)
    {
        return preg_replace('#https\:\/\/gui\.di\.marktjagd\.de\/(.*?)$#', '/opt/crawler/framework/public/$1', $url);
    }

    public function removeParameters(string $url): string
    {
        $urlParameters = parse_url($url);
        unset($urlParameters['query']);

        return $this->buildUrl($urlParameters);
    }

    public function addParameters(string $url, array $parameters): string
    {
        $urlParameters = parse_url($url);
        $queryString = $urlParameters['query'];
        parse_str($queryString, $queryParams);
        $queryParams = array_merge($queryParams, $parameters);
        $urlParameters['query'] = http_build_query($queryParams);

        return $this->buildUrl($urlParameters);
    }

    public function addParametersFromUrl(string $url, string $otherUrl): string
    {
        $parameters = $this->getQueryParameters($otherUrl);

        return $this->addParameters($url, $parameters);
    }

    public function getQueryParameters(string $url): array
    {
        $urlParameters = parse_url($url);
        $queryString = $urlParameters['query'];
        parse_str($queryString, $queryParams);

        return $queryParams;
    }

    public function changeBaseUrl(string $url, string $baseUrl): string
    {
        $urlParameters = parse_url($url);
        $baseUrlParameters = parse_url($baseUrl);

        $urlParameters['scheme'] = $baseUrlParameters['scheme'];
        $urlParameters['host'] = $baseUrlParameters['host'];
        $urlParameters['port'] = $baseUrlParameters['port'];

        return $this->buildUrl($urlParameters);
    }

    private function buildUrl(array $parsedUrl): string
    {
        return $parsedUrl['scheme'] . '://'
            . $parsedUrl['host']
            . (isset($parsedUrl['port']) ? ':' . $parsedUrl['port'] : '')
            . ($parsedUrl['path'] ?? '')
            . (isset($parsedUrl['query']) ? '?' . $parsedUrl['query'] : '')
            . (isset($parsedUrl['fragment']) ? '#' . $parsedUrl['fragment'] : '');
    }
}
